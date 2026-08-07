<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AbsenceReason;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** „Statistik" — admin-only aggregates over what the app already records. */
class StatisticsTest extends TestCase
{
    use RefreshDatabase;

    private Child $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-03 09:00')); // Monday
        $this->child = Child::factory()->create();
    }

    private function departure(string $date, ?string $time): DailyDeparture
    {
        return DailyDeparture::create([
            'child_id' => Child::factory()->create()->id,
            'date' => $date,
            'planned_time' => $time,
        ]);
    }

    public function test_it_is_closed_to_everyone_but_admins(): void
    {
        $this->actingAs(User::factory()->staff()->create())
            ->get(route('statistics'))
            ->assertForbidden();

        $this->actingAs(User::factory()->create())
            ->get(route('statistics'))
            ->assertForbidden();
    }

    public function test_it_buckets_pickups_into_half_hours(): void
    {
        // 15:00 and 15:17 share a slot; 15:42 belongs to the next one.
        $this->departure('2026-07-20', '15:00');
        $this->departure('2026-07-21', '15:17');
        $this->departure('2026-07-22', '15:42');

        $this->actingAs(User::factory()->staff()->admin()->create())
            ->get(route('statistics'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Statistics/Index')
                ->where('pickupTimes', [
                    // Two of three left by 15:00, so a third is still there after it.
                    ['time' => '15:00', 'count' => 2, 'remaining' => 33.3],
                    ['time' => '15:30', 'count' => 1, 'remaining' => 0],
                ]));
    }

    public function test_a_day_the_child_was_away_becomes_the_leftmost_bucket(): void
    {
        // The row exists (the board seeded it before the absence was reported), but the
        // child was not picked up at 15:00 — they were never there.
        $departure = $this->departure('2026-07-20', '15:00');
        Absence::create([
            'child_id' => $departure->child_id,
            'date' => '2026-07-20',
            'reason' => AbsenceReason::Sick,
            'comment' => 'Fieber',
        ]);
        $this->departure('2026-07-21', '16:00');

        $this->actingAs(User::factory()->staff()->admin()->create())
            ->get(route('statistics'))
            ->assertInertia(fn (Assert $page) => $page->where('pickupTimes', [
                // Half of the two days were „never came", so the curve starts at 50.
                ['time' => null, 'count' => 1, 'remaining' => 50],
                ['time' => '16:00', 'count' => 1, 'remaining' => 0],
            ]));
    }

    public function test_actual_times_read_when_the_child_was_marked_off(): void
    {
        // Planned 15:00, actually gone at 15:41 — and a day nobody marked off at all.
        $this->departure('2026-07-20', '15:00')->update(['left_at' => '2026-07-20 15:41:00']);
        $this->departure('2026-07-21', '15:00');

        $admin = User::factory()->staff()->admin()->create();

        $this->actingAs($admin)
            ->get(route('statistics', ['basis' => 'actual']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('basis', 'actual')
                // Only the marked-off day counts, in its real slot — an unmarked day is
                // not quietly filled in with its plan.
                ->where('pickupTimes', [['time' => '15:30', 'count' => 1, 'remaining' => 0]]));

        // The plan reading still sees both days, unchanged.
        $this->actingAs($admin)
            ->get(route('statistics'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('basis', 'planned')
                ->where('pickupTimes', [['time' => '15:00', 'count' => 2, 'remaining' => 0]]));
    }

    public function test_it_keeps_sick_and_away_apart_per_month(): void
    {
        foreach ([['2026-07-06', AbsenceReason::Sick], ['2026-07-07', AbsenceReason::Sick], ['2026-07-08', AbsenceReason::Away]] as [$date, $reason]) {
            Absence::create([
                'child_id' => Child::factory()->create()->id,
                'date' => $date,
                'reason' => $reason,
                'comment' => 'x',
            ]);
        }

        $this->actingAs(User::factory()->staff()->admin()->create())
            ->get(route('statistics'))
            ->assertInertia(fn (Assert $page) => $page
                // Every month of the range is present, so a quiet month keeps its gap.
                ->where('absences', [
                    ['month' => '2026-05', 'sick' => 0, 'away' => 0],
                    ['month' => '2026-06', 'sick' => 0, 'away' => 0],
                    ['month' => '2026-07', 'sick' => 2, 'away' => 1],
                    ['month' => '2026-08', 'sick' => 0, 'away' => 0],
                ]));
    }

    public function test_only_the_selected_period_counts(): void
    {
        $this->departure('2026-07-20', '15:00');   // inside the last 3 months
        $this->departure('2026-01-15', '16:00');   // this calendar year, but older

        $admin = User::factory()->staff()->admin()->create();

        $this->actingAs($admin)
            ->get(route('statistics'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('range', 'quarter')
                ->where('pickupTimes', [['time' => '15:00', 'count' => 1, 'remaining' => 0]]));

        $this->actingAs($admin)
            ->get(route('statistics', ['range' => 'year']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('from', '2026-01-01')
                ->where('pickupTimes', [
                    ['time' => '15:00', 'count' => 1, 'remaining' => 50],
                    ['time' => '16:00', 'count' => 1, 'remaining' => 0],
                ]));
    }

    public function test_an_unknown_range_falls_back_to_the_default(): void
    {
        $this->actingAs(User::factory()->staff()->admin()->create())
            ->get(route('statistics', ['range' => 'forever']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('range', 'quarter')
                ->where('from', '2026-05-03'));
    }
}
