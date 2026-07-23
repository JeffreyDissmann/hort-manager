<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\DepartureMethod;
use App\Enums\TimeQualifier;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\HomeworkDefault;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Assembles the Monday „Wochenüberblick" for one parent: the Hort-wide week program
 * (food + activities + homework) plus a per-child summary of what's planned for their
 * own child(ren) this week (pickup per day, absences, excursions they're on). Modeled
 * on {@see CompanionNotes::for()} and the assembly in WeeklyOverviewController.
 */
class WeeklyDigestBuilder
{
    /**
     * @return array{
     *   week_label: string,
     *   program: array<int, array{weekday: string, lunch: ?string, activity: ?string, homework: ?string}>,
     *   excursions: array<int, array{name: string, day: string}>,
     *   children: array<int, array{name: string, days: array<int, array{weekday: string, summary: string}>}>,
     * }
     */
    public static function for(User $parent, Carbon $weekStart): array
    {
        $monday = $weekStart->copy()->startOfDay();
        $weekDays = collect(range(0, 4))->map(fn (int $i) => $monday->copy()->addDays($i));
        $weekDates = $weekDays->map(fn (Carbon $d) => $d->toDateString())->all();
        $friday = $monday->copy()->addDays(4);

        $locale = $parent->preferredLocale() ?? app()->getLocale();
        $weekdayLabel = fn (Carbon $d): string => $d->copy()->locale($locale)->isoFormat('dddd');

        // Hort-wide program per weekday (lunch + activity + effective homework).
        $programs = DailyProgram::query()
            ->whereIn('date', $weekDates)
            ->get()
            ->keyBy(fn (DailyProgram $p) => $p->date->toDateString());
        $homeworkDefaults = HomeworkDefault::all()->keyBy('weekday');

        $program = $weekDays->map(function (Carbon $day, int $i) use ($programs, $homeworkDefaults, $weekdayLabel) {
            $p = $programs->get($day->toDateString());
            [$hwStart, $hwEnd] = DailyProgram::effectiveHomework($p, $homeworkDefaults->get($i + 1));

            $homework = null;
            if ($hwStart) {
                $homework = self::short($hwStart).($hwEnd ? '–'.self::short($hwEnd) : '');
            }

            return [
                'weekday' => $weekdayLabel($day),
                'lunch' => $p?->lunch,
                'activity' => $p?->activity,
                'homework' => $homework,
            ];
        })->all();

        // This week's excursions (Hort-wide list).
        $excursions = Excursion::query()
            ->whereIn('date', $weekDates)
            ->orderBy('date')
            ->orderBy('depart_at')
            ->get()
            ->map(fn (Excursion $e) => [
                'name' => $e->name,
                'day' => $weekdayLabel($e->date),
            ])
            ->all();

        // Per-child summary — this parent's own children enrolled during the week.
        $children = $parent->children()
            ->activeBetween($weekDays->first(), $weekDays->last())
            ->orderBy('name')->get();
        $childIds = $children->pluck('id')->all();

        $plans = EffectivePlan::forMany($childIds, $weekDates);

        $absences = Absence::query()
            ->whereIn('child_id', $childIds)
            ->whereIn('date', $weekDates)
            ->get()
            ->keyBy(fn (Absence $a) => $a->child_id.'|'.$a->date->toDateString());

        // Which trips each of this parent's children actually joins, per date.
        $excursionByChildDate = [];
        Excursion::query()
            ->with('participants:id')
            ->whereIn('date', $weekDates)
            ->get()
            ->each(function (Excursion $e) use (&$excursionByChildDate, $childIds): void {
                foreach ($e->participants as $participant) {
                    if (in_array($participant->id, $childIds, true)) {
                        $excursionByChildDate[$participant->id.'|'.$e->date->toDateString()] = $e->name;
                    }
                }
            });

        $childNames = Child::query()->whereKey(
            collect($plans)->pluck('companion_child_id')->filter()->all()
        )->pluck('name', 'id');

        $childSummaries = $children->map(function (Child $child) use ($weekDays, $plans, $absences, $excursionByChildDate, $childNames, $weekdayLabel) {
            $days = $weekDays->map(function (Carbon $day) use ($child, $plans, $absences, $excursionByChildDate, $childNames, $weekdayLabel) {
                $date = $day->toDateString();

                $absence = $absences->get($child->id.'|'.$date);
                if ($absence) {
                    return ['weekday' => $weekdayLabel($day), 'summary' => $absence->reason->label()];
                }

                $plan = $plans[$child->id.'|'.$date] ?? null;
                $summary = self::planSummary($plan, $date, $childNames);

                if (isset($excursionByChildDate[$child->id.'|'.$date])) {
                    $trip = $excursionByChildDate[$child->id.'|'.$date];
                    $summary = "🚌 {$trip}".($summary ? " · {$summary}" : '');
                }

                return ['weekday' => $weekdayLabel($day), 'summary' => $summary ?: '–'];
            })->all();

            return ['name' => $child->name, 'days' => $days];
        })->all();

        $weekLabel = $monday->copy()->locale($locale)->isoFormat('D.M.').'–'.$friday->copy()->locale($locale)->isoFormat('D.M.YYYY');

        return [
            'week_label' => $weekLabel,
            'program' => $program,
            'excursions' => $excursions,
            'children' => $childSummaries,
        ];
    }

    /**
     * A one-line pickup description for a resolved effective plan.
     *
     * @param  array{time: ?string, method: ?string, qualifier: ?string, companion_child_id: ?int}|null  $plan
     * @param  Collection<int, string>  $childNames
     */
    private static function planSummary(?array $plan, string $date, $childNames): string
    {
        if ($plan === null || $plan['method'] === null) {
            return '';
        }

        // „geht mit … mit": mirror the companion (time is resolved one level up).
        if ($plan['method'] === DepartureMethod::WithChild->value && $plan['companion_child_id']) {
            $companion = $childNames[$plan['companion_child_id']] ?? '';
            $mirror = EffectivePlan::for($plan['companion_child_id'], $date);

            return trim("geht mit {$companion} mit".($mirror['time'] ? " ({$mirror['time']})" : ''));
        }

        $time = $plan['time'];
        $method = DepartureMethod::from($plan['method'])->label();

        if ($plan['method'] === DepartureMethod::SentHome->value && $time) {
            $prefix = $plan['qualifier'] && $plan['qualifier'] !== TimeQualifier::At->value
                ? TimeQualifier::from($plan['qualifier'])->prefix().' '
                : '';

            return "{$method} {$prefix}{$time}";
        }

        return trim($method.($time ? " {$time}" : ''));
    }

    private static function short(?string $time): ?string
    {
        return $time ? substr((string) $time, 0, 5) : null;
    }
}
