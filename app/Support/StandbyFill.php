<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Child;
use App\Models\StandbyDuty;
use Illuminate\Support\Carbon;

/**
 * „Fair zufällig auffüllen" — proposes a child for every empty Springerdienst slot in a
 * range. Nothing is saved: the coordinator sees the proposal, adjusts it and saves.
 *
 * Per slot, in date order:
 * - only children enrolled that day with at least one guardian;
 * - never a family already on that day (two children sharing a guardian are one family);
 * - not a family on the neighbouring Hort day either, unless nobody else is left;
 * - the fewest Springerdienste in the date's school year first — counting what is
 *   already in the list plus what this fill handed out so far — ties broken at random.
 */
class StandbyFill
{
    /** Hort days looked at around the range, so its edges respect their neighbours. */
    private const CONTEXT_DAYS = 7;

    /** @var array<int, list<int>> child id → guardian ids */
    private array $guardians = [];

    /** @var array<string, array<int, int>> school-year start → child id → duty count */
    private array $counts = [];

    /** @var array<string, list<int>> `Y-m-d` → child ids on that day (saved + proposed) */
    private array $byDate = [];

    /**
     * @return list<array{date: string, slot: int, child_id: int}>
     */
    public function propose(Carbon $from, Carbon $to): array
    {
        $slots = StandbyRoster::slotsBetween($from, $to);

        if ($slots === []) {
            return [];
        }

        $context = StandbyRoster::slotsBetween(
            $from->copy()->subDays(self::CONTEXT_DAYS),
            $to->copy()->addDays(self::CONTEXT_DAYS),
        );
        $hortDays = array_keys($context);

        $existing = StandbyDuty::query()
            ->between(array_key_first($context), array_key_last($context))
            ->whereNotNull('child_id')
            ->get(['date', 'slot', 'child_id']);

        $taken = [];
        foreach ($existing as $duty) {
            $date = $duty->date->toDateString();
            $this->byDate[$date][] = $duty->child_id;
            $taken[$date][$duty->slot] = true;
        }

        $this->loadGuardians();

        $proposals = [];

        foreach ($slots as $date => $count) {
            for ($slot = 1; $slot <= $count; $slot++) {
                if (isset($taken[$date][$slot])) {
                    continue;
                }

                $childId = $this->pick($date, $this->neighbours($date, $hortDays));

                if ($childId === null) {
                    continue;
                }

                $this->byDate[$date][] = $childId;
                $this->counts[$this->yearKey($date)][$childId] = ($this->counts[$this->yearKey($date)][$childId] ?? 0) + 1;
                $proposals[] = ['date' => $date, 'slot' => $slot, 'child_id' => $childId];
            }
        }

        return $proposals;
    }

    /** The fairest candidate for one slot, or null when nobody is eligible. */
    private function pick(string $date, array $neighbourDates): ?int
    {
        $sameDay = $this->byDate[$date] ?? [];
        $candidates = Child::query()
            ->activeOn($date)
            ->whereIn('id', array_keys($this->guardians))
            ->pluck('id')
            ->reject(fn (int $id): bool => $this->sharesFamilyWithAny($id, $sameDay))
            ->values();

        if ($candidates->isEmpty()) {
            return null;
        }

        $neighbours = collect($neighbourDates)->flatMap(fn (string $d): array => $this->byDate[$d] ?? [])->all();
        $rested = $candidates->reject(fn (int $id): bool => $this->sharesFamilyWithAny($id, $neighbours));

        $pool = $rested->isNotEmpty() ? $rested : $candidates;
        $counts = $this->countsFor($date);

        return $pool
            ->shuffle()
            ->sortBy(fn (int $id): int => $counts[$id] ?? 0)
            ->first();
    }

    /** Whether the child shares a guardian with any of the others (or is one of them). */
    private function sharesFamilyWithAny(int $childId, array $others): bool
    {
        foreach ($others as $other) {
            if ($other === $childId || array_intersect($this->guardians[$childId] ?? [], $this->guardians[$other] ?? []) !== []) {
                return true;
            }
        }

        return false;
    }

    /**
     * The Hort days right before and after the date.
     *
     * @param  list<string>  $hortDays
     * @return list<string>
     */
    private function neighbours(string $date, array $hortDays): array
    {
        $index = array_search($date, $hortDays, true);

        return array_values(array_filter([
            $hortDays[$index - 1] ?? null,
            $hortDays[$index + 1] ?? null,
        ]));
    }

    /** @return array<int, int> child id → duties in the date's school year so far */
    private function countsFor(string $date): array
    {
        $key = $this->yearKey($date);

        if (! isset($this->counts[$key])) {
            [$start, $end] = StandbyDuty::schoolYearOf($date);

            $this->counts[$key] = StandbyDuty::query()
                ->between($start, $end)
                ->whereNotNull('child_id')
                ->selectRaw('child_id, count(*) as aggregate')
                ->groupBy('child_id')
                ->pluck('aggregate', 'child_id')
                ->map(fn ($count): int => (int) $count)
                ->all();
        }

        return $this->counts[$key];
    }

    private function yearKey(string $date): string
    {
        return StandbyDuty::schoolYearOf($date)[0]->toDateString();
    }

    /** Children with at least one guardian, and who those guardians are. */
    private function loadGuardians(): void
    {
        $this->guardians = Child::query()
            ->whereHas('guardians')
            ->with('guardians:id')
            ->get(['id'])
            ->mapWithKeys(fn (Child $child): array => [$child->id => $child->guardians->pluck('id')->all()])
            ->all();
    }
}
