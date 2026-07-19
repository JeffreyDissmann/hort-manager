<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Ai\Agents\BookingCategorizer;
use App\Enums\BookingStatus;
use App\Enums\CategoryDirection;
use App\Models\Accounting\Booking;
use App\Models\Child;
use App\Models\User;
use App\Support\Accounting\CategoryOptions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Laravel\Ai\Enums\Lab;
use Throwable;

/**
 * Runs the local Ollama model over a single imported draft, writing a suggested
 * category and counterparty (child for income, user/free text for expenses) into
 * its real fields and moving it to the "suggested" state. Best-effort: any failure
 * (Ollama unreachable, bad response) leaves the booking a plain draft.
 */
class BookingSuggester
{
    public function suggest(Booking $booking): void
    {
        // Only unreviewed drafts get suggestions (the job may run late).
        if ($booking->status !== BookingStatus::Draft) {
            return;
        }

        $categories = CategoryOptions::flat();
        $categoryDirection = collect($categories)->pluck('direction', 'id');

        $children = Child::with('guardians:id,name')->orderBy('name')->get();
        $childIds = $children->pluck('id')->flip();
        $userIds = User::pluck('id')->flip();

        $row = [
            'amount' => round($booking->amount_cents / 100, 2),
            'direction' => $booking->amount_cents >= 0 ? CategoryDirection::Income->value : CategoryDirection::Expense->value,
            'purpose' => $booking->purpose,
        ];

        try {
            $response = (new BookingCategorizer(
                categories: $categories,
                children: $children->map($this->childContext(...))->all(),
                users: User::orderBy('name')->get(['id', 'name'])->toArray(),
            ))->prompt(
                json_encode($row, JSON_UNESCAPED_UNICODE),
                provider: Lab::Ollama,
                model: (string) config('ai.providers.ollama.model'),
            );
        } catch (Throwable $e) {
            Log::warning("Booking AI suggestion failed for #{$booking->id}: ".$e->getMessage());

            return;
        }

        $childId = $this->pickId($response['counterparty_child_id'] ?? null, $childIds);
        $userId = $childId ? null : $this->pickId($response['counterparty_user_id'] ?? null, $userIds);

        $booking->forceFill([
            'category_id' => $this->validCategory($response['category_id'] ?? null, $booking, $categoryDirection),
            'counterparty_child_id' => $childId,
            'counterparty_user_id' => $userId,
            'counterparty_name' => ($childId || $userId) ? null : ($response['counterparty_name'] ?? null),
            'status' => BookingStatus::Suggested,
        ])->save();
    }

    /**
     * The AI context shape for one child: name plus its guardians' names.
     *
     * @return array{id:int, name:string, guardians:string}
     */
    private function childContext(Child $child): array
    {
        return [
            'id' => $child->id,
            'name' => $child->name,
            'guardians' => $child->guardians->pluck('name')->join(', '),
        ];
    }

    /**
     * @param  Collection<int, int>  $known  known ids flipped to a lookup set
     */
    private function pickId(mixed $suggested, Collection $known): ?int
    {
        return ! empty($suggested) && $known->has((int) $suggested) ? (int) $suggested : null;
    }

    /**
     * A suggested category is only kept when it exists and its direction matches
     * the booking's cash-flow sign.
     *
     * @param  Collection<int, string>  $categoryDirection
     */
    private function validCategory(mixed $suggested, Booking $booking, Collection $categoryDirection): ?int
    {
        if (empty($suggested) || ! $categoryDirection->has((int) $suggested)) {
            return null;
        }

        $expected = $booking->amount_cents >= 0 ? CategoryDirection::Income->value : CategoryDirection::Expense->value;

        return $categoryDirection->get((int) $suggested) === $expected ? (int) $suggested : null;
    }
}
