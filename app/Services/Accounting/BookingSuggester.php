<?php

declare(strict_types=1);

namespace App\Services\Accounting;

use App\Ai\Agents\BookingCategorizer;
use App\Enums\BookingStatus;
use App\Enums\CategoryDirection;
use App\Enums\SuggestionConfidence;
use App\Models\Accounting\Booking;
use App\Models\Child;
use App\Models\User;
use App\Support\Accounting\CategoryOptions;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
        $childNames = $children->pluck('name', 'id');
        $users = User::orderBy('name')->get(['id', 'name']);
        $userNames = $users->pluck('name', 'id');
        $childIds = $childNames->keys()->flip();
        $userIds = $userNames->keys()->flip();

        $row = [
            'amount' => round($booking->amount_cents / 100, 2),
            'direction' => $booking->amount_cents >= 0 ? CategoryDirection::Income->value : CategoryDirection::Expense->value,
            'purpose' => $booking->purpose,
        ];

        try {
            $response = (new BookingCategorizer(
                categories: $categories,
                children: $children->map($this->childContext(...))->all(),
                users: $users->toArray(),
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
        // Always keep the model's own validated pick — the keyword logic below never
        // changes the category, it only decides how much to trust it.
        $categoryId = $this->validCategory($response['category_id'] ?? null, $booking, $categoryDirection);

        [$chosenNamed, $otherNamed] = $this->categoryKeywords($categoryId, $categories, (string) $booking->purpose);
        $confidence = $this->confidence(
            categoryValid: $categoryId !== null,
            chosenCategoryNamed: $chosenNamed,
            otherCategoryNamed: $otherNamed,
            modelConfidence: $response['confidence'] ?? null,
        );

        // Write only while the booking is STILL a draft. The Ollama call takes
        // seconds, so a reviewer may have confirmed it meanwhile — this conditional
        // update guarantees a confirmed (or already re-suggested) booking is never
        // overwritten by a stale AI result.
        Booking::whereKey($booking->id)
            ->where('status', BookingStatus::Draft)
            ->update([
                'category_id' => $categoryId,
                'counterparty_child_id' => $childId,
                'counterparty_user_id' => $userId,
                'counterparty_name' => ($childId || $userId) ? null : ($response['counterparty_name'] ?? null),
                'confidence' => $confidence,
                'status' => BookingStatus::Suggested,
            ]);
    }

    /**
     * The model's category pick is always kept; this only decides how much to trust it:
     *  - no category → low (nothing to confirm against);
     *  - a DIFFERENT category is literally named in the purpose than the one chosen →
     *    low, flagged for review (a likely mistake — e.g. „Vereinsbeitrag" booked as
     *    Elternbeitrag — or bank noise like „INTERNET" clashing with the Internet
     *    category; either way we keep the model's pick and let a human check it);
     *  - the chosen category is itself named in the purpose → high (a certain match);
     *  - otherwise the model's own self-assessment (high / medium / low) is trusted.
     */
    private function confidence(bool $categoryValid, bool $chosenCategoryNamed, bool $otherCategoryNamed, ?string $modelConfidence): SuggestionConfidence
    {
        if (! $categoryValid) {
            return SuggestionConfidence::Low;
        }

        if ($otherCategoryNamed && ! $chosenCategoryNamed) {
            return SuggestionConfidence::Low;
        }

        if ($chosenCategoryNamed) {
            return SuggestionConfidence::High; // the chosen category is literally in the text
        }

        return match ($modelConfidence) {
            'high' => SuggestionConfidence::High,
            'low' => SuggestionConfidence::Low,
            default => SuggestionConfidence::Medium,
        };
    }

    /**
     * Whether the chosen category's name — and/or any OTHER same-direction category's
     * name — appears verbatim in the purpose. Only reasonably distinctive names
     * (≥ 5 chars) are matched, to avoid false hits on short generic words.
     *
     * @param  list<array{id:int, name:string, direction:string}>  $categories
     * @return array{0:bool, 1:bool} [chosenNamed, otherNamed]
     */
    private function categoryKeywords(?int $chosenId, array $categories, string $purpose): array
    {
        if ($chosenId === null) {
            return [false, false];
        }

        $haystack = Str::lower($purpose);
        $chosenDirection = collect($categories)->firstWhere('id', $chosenId)['direction'] ?? null;
        $chosenNamed = false;
        $otherNamed = false;

        foreach ($categories as $category) {
            if ($category['direction'] !== $chosenDirection || Str::length($category['name']) < 5) {
                continue;
            }
            if (! str_contains($haystack, Str::lower($category['name']))) {
                continue;
            }
            $category['id'] === $chosenId ? $chosenNamed = true : $otherNamed = true;
        }

        return [$chosenNamed, $otherNamed];
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
