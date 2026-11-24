<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\Child;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\StandbyDuty;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * The Springerdienst calendar: which dates need a Springer, how many, and who holds
 * each slot.
 *
 * Slots are derived from the Hort calendar, never stored: a weekday has one, a
 * Ferienbetreuung day two (the holiday group is staffed differently), and a weekend,
 * a Schließzeit or a weekday inside a Ferienbetreuung that isn't offered has none —
 * the Hort isn't open, so nobody needs to stand by.
 */
class StandbyRoster
{
    /** Springer per normal Hort day. */
    public const SLOTS_REGULAR = 1;

    /** Springer per Ferienbetreuung day. */
    public const SLOTS_CARE = 2;

    /**
     * The dates in the range that need a Springer, as `Y-m-d` => number of slots.
     * Dates without any are left out.
     *
     * @return array<string, int>
     */
    public static function slotsBetween(Carbon|string $from, Carbon|string $to): array
    {
        $start = $from instanceof Carbon ? $from->copy()->startOfDay() : Carbon::parse($from);
        $end = $to instanceof Carbon ? $to->copy()->startOfDay() : Carbon::parse($to);

        $closed = HolidayPeriod::closedDaysBetween($start, $end);
        $careDays = HolidayCareDay::betweenKeyed($start, $end);
        $carePeriods = HolidayPeriod::query()->care()->overlapping($start, $end)->get();

        $slots = [];

        foreach ($start->toPeriod($end) as $day) {
            $date = $day->toDateString();

            // A Schließzeit wins over a care day on the same date, as everywhere else.
            if ($day->isWeekend() || isset($closed[$date])) {
                continue;
            }

            if ($careDays->has($date)) {
                $slots[$date] = self::SLOTS_CARE;

                continue;
            }

            // Inside a Ferienbetreuung but not offered: no school, no care — no Hort.
            $inCarePeriod = $carePeriods->contains(
                fn (HolidayPeriod $period): bool => $date >= $period->starts_on->toDateString() && $date <= $period->ends_on->toDateString(),
            );

            if (! $inCarePeriod) {
                $slots[$date] = self::SLOTS_REGULAR;
            }
        }

        return $slots;
    }

    /**
     * The list for a range, one entry per date that needs a Springer, each slot with
     * its holder (or null = „offen"). `own` marks the viewer's own children.
     *
     * @return list<array{date: string, care: bool, past: bool, slots: list<array{slot: int, duty_id: ?int, child: array{id: int, name: string}|null, former: ?string, own: bool, note: ?string}>}>
     */
    public static function forRange(Carbon $from, Carbon $to, User $viewer): array
    {
        $ownChildIds = $viewer->children()->pluck('children.id')->all();

        $duties = StandbyDuty::query()
            ->between($from, $to)
            ->with('child:id,name,active_from,active_until')
            ->get()
            ->keyBy(fn (StandbyDuty $duty): string => $duty->date->toDateString().'#'.$duty->slot);

        $days = [];

        foreach (self::slotsBetween($from, $to) as $date => $count) {
            $slots = [];

            for ($slot = 1; $slot <= $count; $slot++) {
                $duty = $duties->get("{$date}#{$slot}");
                $holder = $duty?->child;
                // A child who has left by then holds nothing — the slot is open again,
                // with the old name kept so the coordinator sees whose date it was.
                $child = $holder?->isActiveOn($date) ? $holder : null;

                $slots[] = [
                    'slot' => $slot,
                    // Only a held slot can be swapped, so only a held slot names its duty.
                    'duty_id' => $child ? $duty->id : null,
                    'child' => $child ? ['id' => $child->id, 'name' => $child->name] : null,
                    'former' => $holder !== null && $child === null ? $holder->name : null,
                    'own' => $child !== null && in_array($child->id, $ownChildIds, true),
                    'note' => $duty?->note,
                ];
            }

            $days[] = [
                'date' => $date,
                'care' => $count === self::SLOTS_CARE,
                'past' => $date < Carbon::today()->toDateString(),
                'slots' => $slots,
            ];
        }

        return $days;
    }

    /**
     * One date's Springerdienst as `Y-m-d` => names, for the board and the Wochenplan:
     * each slot's holder (null = „offen"), own children marked. Dates without a slot
     * (weekend, Schließzeit) are left out.
     *
     * Empty while the Hort doesn't use the list yet — otherwise every board and
     * Wochenplan would announce „Springerdienst: offen" before anyone entered one.
     *
     * @return array<string, list<array{name: ?string, own: bool}>>
     */
    public static function namesBetween(Carbon|string $from, Carbon|string $to, User $viewer): array
    {
        if (! StandbyDuty::query()->exists()) {
            return [];
        }

        return collect(self::forRange(Carbon::parse($from), Carbon::parse($to), $viewer))
            ->mapWithKeys(fn (array $day): array => [
                $day['date'] => array_map(
                    fn (array $slot): array => ['name' => $slot['child']['name'] ?? null, 'own' => $slot['own']],
                    $day['slots'],
                ),
            ])
            ->all();
    }

    /**
     * The viewer's own Springerdienst dates from tomorrow on — what they can offer in
     * a swap — each with its duty and child.
     *
     * @return list<array{duty_id: int, date: string, child: string}>
     */
    public static function upcomingFor(User $viewer): array
    {
        $ownChildIds = $viewer->children()->pluck('children.id')->all();

        if ($ownChildIds === []) {
            return [];
        }

        return StandbyDuty::query()
            ->whereIn('child_id', $ownChildIds)
            ->whereDate('date', '>', Carbon::today())
            ->with('child:id,name,active_from,active_until')
            ->orderBy('date')
            ->get()
            ->filter(fn (StandbyDuty $duty): bool => $duty->child->isActiveOn($duty->date))
            ->map(fn (StandbyDuty $duty): array => [
                'duty_id' => $duty->id,
                'date' => $duty->date->toDateString(),
                'child' => $duty->child->name,
            ])
            ->values()
            ->all();
    }

    /**
     * Children who can hold a Springerdienst in the range: enrolled at some point in
     * it and with at least one guardian — a child nobody is linked to has nobody to
     * stand by.
     *
     * @return list<array{id: int, name: string}>
     */
    public static function eligibleChildren(Carbon $from, Carbon $to): array
    {
        return Child::query()
            ->activeBetween($from, $to)
            ->whereHas('guardians')
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Child $child): array => ['id' => $child->id, 'name' => $child->name])
            ->all();
    }
}
