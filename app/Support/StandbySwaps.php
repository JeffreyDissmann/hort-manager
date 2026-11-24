<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\StandbySwapStatus;
use App\Models\Child;
use App\Models\StandbyDuty;
use App\Models\StandbySwap;
use App\Models\User;
use App\Notifications\StandbySwapAnswered;
use App\Notifications\StandbySwapCompleted;
use App\Notifications\StandbySwapRequested;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Swapping Springerdienst dates between two families: one asks (offering one of its
 * own dates for one of theirs), any guardian of the other child answers. Accepting
 * exchanges the two children on the two dates; the coordinator is told afterwards.
 *
 * A refused request surfaces as a validation error on `swap`, so the page can show
 * why; acting for a child that isn't yours is a 403.
 */
class StandbySwaps
{
    public function request(User $user, StandbyDuty $offered, StandbyDuty $wanted): StandbySwap
    {
        $offeredChild = $offered->child;
        $wantedChild = $wanted->child;

        abort_unless($offeredChild !== null && $offeredChild->isGuardedBy($user), HttpResponse::HTTP_FORBIDDEN);

        $this->refuseIf($wantedChild === null, 'swap_open_date');
        $this->refuseIf(! $this->isAhead($offered) || ! $this->isAhead($wanted), 'swap_past');
        $this->refuseIf(! $offeredChild->isActiveOn($offered->date) || ! $wantedChild->isActiveOn($wanted->date), 'swap_open_date');
        $this->refuseIf($this->sameFamily($offeredChild, $wantedChild), 'swap_own_family');
        $this->refuseIf(
            StandbySwap::query()->pending()->where('offered_duty_id', $offered->id)->get()->contains->isStillValid(),
            'swap_already_offered',
        );

        $swap = StandbySwap::create([
            'offered_duty_id' => $offered->id,
            'offered_child_id' => $offeredChild->id,
            'wanted_duty_id' => $wanted->id,
            'wanted_child_id' => $wantedChild->id,
            'requested_by' => $user->id,
        ]);

        Notification::send($wantedChild->guardians, new StandbySwapRequested($swap));

        return $swap;
    }

    public function accept(User $user, StandbySwap $swap): void
    {
        abort_unless($swap->wantedChild?->isGuardedBy($user), HttpResponse::HTTP_FORBIDDEN);

        // Outside the transaction, so marking a stale request expired isn't rolled back
        // together with the refusal.
        if ($swap->status === StandbySwapStatus::Pending && ! $swap->isStillValid()) {
            $this->expire($swap);
        }

        DB::transaction(function () use ($user, $swap): void {
            $swap->refresh()->load(['offeredDuty', 'wantedDuty']);

            $this->refuseIf(! $swap->isStillValid(), 'swap_expired');

            $swap->offeredDuty->update(['child_id' => $swap->wanted_child_id]);
            $swap->wantedDuty->update(['child_id' => $swap->offered_child_id]);
            $swap->update(['status' => StandbySwapStatus::Accepted, 'answered_by' => $user->id, 'answered_at' => now()]);

            // Every other open request on either date no longer describes the list.
            StandbySwap::query()->pending()
                ->whereKeyNot($swap->id)
                ->where(fn ($q) => $q->touching($swap->offered_duty_id)->orWhere(fn ($q) => $q->touching($swap->wanted_duty_id)))
                ->update(['status' => StandbySwapStatus::Expired]);
        });

        Notification::send($swap->offeredChild->guardians, new StandbySwapAnswered($swap, accepted: true));
        Notification::send(User::query()->where('manages_standby', true)->get(), new StandbySwapCompleted($swap));
    }

    public function decline(User $user, StandbySwap $swap): void
    {
        abort_unless($swap->wantedChild?->isGuardedBy($user), HttpResponse::HTTP_FORBIDDEN);
        $this->refuseIf($swap->status !== StandbySwapStatus::Pending, 'swap_expired');

        $swap->update(['status' => StandbySwapStatus::Declined, 'answered_by' => $user->id, 'answered_at' => now()]);

        Notification::send($swap->offeredChild->guardians, new StandbySwapAnswered($swap, accepted: false));
    }

    public function cancel(User $user, StandbySwap $swap): void
    {
        abort_unless($swap->offeredChild?->isGuardedBy($user), HttpResponse::HTTP_FORBIDDEN);
        $this->refuseIf($swap->status !== StandbySwapStatus::Pending, 'swap_expired');

        $swap->update(['status' => StandbySwapStatus::Cancelled, 'answered_by' => $user->id, 'answered_at' => now()]);
    }

    /**
     * The viewer's open requests, both directions, dropping (and closing) any that no
     * longer describe the list.
     *
     * @return array{incoming: list<array<string, mixed>>, outgoing: list<array<string, mixed>>}
     */
    public function openFor(User $user): array
    {
        $ownChildIds = $user->children()->pluck('children.id')->all();

        if ($ownChildIds === []) {
            return ['incoming' => [], 'outgoing' => []];
        }

        $swaps = StandbySwap::query()->pending()
            ->where(fn ($q) => $q->whereIn('offered_child_id', $ownChildIds)->orWhereIn('wanted_child_id', $ownChildIds))
            ->with(['offeredDuty', 'wantedDuty', 'offeredChild:id,name', 'wantedChild:id,name', 'requester:id,name'])
            ->orderBy('created_at')
            ->get();

        $valid = $swaps->filter(function (StandbySwap $swap): bool {
            if ($swap->isStillValid()) {
                return true;
            }
            $this->expire($swap);

            return false;
        });

        $shape = fn (StandbySwap $swap): array => [
            'id' => $swap->id,
            'offered' => ['duty_id' => $swap->offered_duty_id, 'date' => $swap->offeredDuty->date->toDateString(), 'child' => $swap->offeredChild->name],
            'wanted' => ['duty_id' => $swap->wanted_duty_id, 'date' => $swap->wantedDuty->date->toDateString(), 'child' => $swap->wantedChild->name],
            'requested_by' => $swap->requester?->name,
        ];

        return [
            'incoming' => $valid->filter(fn (StandbySwap $s): bool => in_array($s->wanted_child_id, $ownChildIds, true))->map($shape)->values()->all(),
            'outgoing' => $valid->filter(fn (StandbySwap $s): bool => in_array($s->offered_child_id, $ownChildIds, true))->map($shape)->values()->all(),
        ];
    }

    /** Two children are one family when they share a guardian (or are the same child). */
    public function sameFamily(Child $a, Child $b): bool
    {
        if ($a->is($b)) {
            return true;
        }

        return $a->guardians()->pluck('users.id')->intersect($b->guardians()->pluck('users.id'))->isNotEmpty();
    }

    /** A swap only makes sense for a date that hasn't come yet. */
    private function isAhead(StandbyDuty $duty): bool
    {
        return $duty->date->toDateString() > Carbon::today()->toDateString();
    }

    private function expire(StandbySwap $swap): void
    {
        $swap->update(['status' => StandbySwapStatus::Expired]);
    }

    private function refuseIf(bool $condition, string $reason): void
    {
        if ($condition) {
            $this->refuse($reason);
        }
    }

    private function refuse(string $reason): never
    {
        throw ValidationException::withMessages(['swap' => __("standby.{$reason}")]);
    }
}
