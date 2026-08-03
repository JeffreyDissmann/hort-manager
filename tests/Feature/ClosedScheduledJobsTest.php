<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\AbsenceReason;
use App\Enums\DepartureMethod;
use App\Enums\UserRole;
use App\Models\Absence;
use App\Models\Child;
use App\Models\DailyProgram;
use App\Models\HolidayPeriod;
use App\Models\User;
use App\Models\WeeklySchedule;
use App\Notifications\ProgramMissing;
use App\Notifications\WeeklyDigest;
use App\Support\WeeklyDigestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * The two Monday jobs against every closure shape: none, one day, part of the week,
 * the whole week, and periods that only overlap it at one end.
 */
class ClosedScheduledJobsTest extends TestCase
{
    use RefreshDatabase;

    /** Mo–Fr of the test week. */
    private const WEEK = ['2026-08-03', '2026-08-04', '2026-08-05', '2026-08-06', '2026-08-07'];

    private User $staff;

    private User $parent;

    private Child $child;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo(Carbon::parse('2026-08-03 11:30')); // Monday

        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'slack_id' => 'U-STAFF']);
        $this->parent = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U-PARENT']);

        $this->child = Child::factory()->create(['name' => 'Mia']);
        $this->parent->children()->attach($this->child);

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            WeeklySchedule::create([
                'child_id' => $this->child->id,
                'weekday' => $weekday,
                'planned_time' => '15:00',
                'method' => DepartureMethod::PickedUp,
            ]);
        }
    }

    /** @param  list<string>  $dates */
    private function fillLunch(array $dates): void
    {
        foreach ($dates as $date) {
            DailyProgram::create(['date' => $date, 'lunch' => 'Nudeln']);
        }
    }

    private function close(string $from, string $to, string $name = 'Ferien'): HolidayPeriod
    {
        return HolidayPeriod::factory()->between($from, $to)->create(['name' => $name]);
    }

    /** @return list<string> the weekdays the reminder considers still missing */
    private function missingDays(): array
    {
        return array_map(
            fn (Carbon $day): string => $day->toDateString(),
            DailyProgram::weekdaysWithoutLunch(Carbon::today()),
        );
    }

    // --- „Wochenprogramm fehlt" ------------------------------------------------

    public function test_with_no_closure_every_empty_weekday_is_missing(): void
    {
        $this->assertSame(self::WEEK, $this->missingDays());
    }

    public function test_a_single_closed_day_drops_out_of_the_missing_list(): void
    {
        $this->close('2026-08-05', '2026-08-05');

        $this->assertSame(['2026-08-03', '2026-08-04', '2026-08-06', '2026-08-07'], $this->missingDays());
    }

    public function test_a_half_closed_week_leaves_only_the_open_days(): void
    {
        $this->close('2026-08-05', '2026-08-07');

        $this->assertSame(['2026-08-03', '2026-08-04'], $this->missingDays());
    }

    public function test_a_fully_closed_week_reminds_nobody(): void
    {
        $this->close('2026-08-03', '2026-08-07', 'Sommerferien');

        $this->assertSame([], $this->missingDays());

        $this->artisan('program:remind-missing')->assertSuccessful();
        Notification::assertNothingSent();
    }

    public function test_a_closure_reaching_in_from_the_previous_week_only_closes_its_days(): void
    {
        // Ends Tuesday of the test week.
        $this->close('2026-07-27', '2026-08-04');

        $this->assertSame(['2026-08-05', '2026-08-06', '2026-08-07'], $this->missingDays());
    }

    public function test_a_closure_reaching_into_the_next_week_only_closes_its_days(): void
    {
        // Starts Thursday and runs past Friday.
        $this->close('2026-08-06', '2026-08-14');

        $this->assertSame(['2026-08-03', '2026-08-04', '2026-08-05'], $this->missingDays());
    }

    public function test_a_closure_entirely_outside_the_week_changes_nothing(): void
    {
        $this->close('2026-08-10', '2026-08-14');

        $this->assertSame(self::WEEK, $this->missingDays());
    }

    public function test_a_weekend_only_closure_changes_nothing(): void
    {
        $this->close('2026-08-08', '2026-08-09');

        $this->assertSame(self::WEEK, $this->missingDays());
    }

    public function test_two_closures_in_one_week_both_count(): void
    {
        $this->close('2026-08-03', '2026-08-03', 'Brückentag');
        $this->close('2026-08-07', '2026-08-07', 'Fortbildung');

        $this->assertSame(['2026-08-04', '2026-08-05', '2026-08-06'], $this->missingDays());
    }

    public function test_the_reminder_still_fires_when_only_open_days_lack_lunch(): void
    {
        $this->close('2026-08-05', '2026-08-07');
        $this->fillLunch(['2026-08-03']); // Tuesday still empty

        $this->artisan('program:remind-missing')->assertSuccessful();

        Notification::assertSentTo($this->staff, ProgramMissing::class, function (ProgramMissing $n) {
            $dates = array_map(fn (Carbon $d): string => $d->toDateString(), $n->missingDays);

            return $dates === ['2026-08-04'];
        });
    }

    public function test_the_reminder_stays_quiet_when_every_open_day_has_lunch(): void
    {
        $this->close('2026-08-05', '2026-08-07');
        $this->fillLunch(['2026-08-03', '2026-08-04']);

        $this->artisan('program:remind-missing')->assertSuccessful();

        Notification::assertNothingSent();
    }

    // --- Wochenüberblick -------------------------------------------------------

    public function test_no_digest_is_sent_when_the_whole_week_is_closed(): void
    {
        $this->close('2026-08-03', '2026-08-07', 'Sommerferien');

        $this->artisan('weekly:digest')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_digest_is_still_sent_when_one_day_is_open(): void
    {
        $this->close('2026-08-03', '2026-08-06', 'Fast alles zu');

        $this->artisan('weekly:digest')->assertSuccessful();

        Notification::assertSentTo($this->parent, WeeklyDigest::class);
    }

    public function test_a_closure_spanning_the_weekend_does_not_count_as_a_full_week(): void
    {
        // Sat–Sun plus Monday: only one weekday is shut, so the digest still goes out.
        $this->close('2026-08-01', '2026-08-03');

        $this->artisan('weekly:digest')->assertSuccessful();

        Notification::assertSentTo($this->parent, WeeklyDigest::class);
    }

    public function test_the_digest_marks_closed_days_and_keeps_the_open_ones(): void
    {
        $this->close('2026-08-05', '2026-08-05', 'Fortbildung');
        $this->fillLunch(['2026-08-04']);

        $digest = WeeklyDigestBuilder::for($this->parent, Carbon::parse('2026-08-03'));

        // Program: Wednesday is the closure, Tuesday keeps its lunch.
        $this->assertSame('Fortbildung', $digest['program'][2]['closed']);
        $this->assertNull($digest['program'][2]['lunch']);
        $this->assertSame('Nudeln', $digest['program'][1]['lunch']);
        $this->assertNull($digest['program'][1]['closed']);

        // The child's own week: no pickup on the closed day, normal either side.
        $days = $digest['children'][0]['days'];
        $this->assertSame('🚫 Fortbildung', $days[2]['summary']);
        $this->assertStringContainsString('15:00', $days[1]['summary']);
        $this->assertStringContainsString('15:00', $days[3]['summary']);
    }

    public function test_a_closed_day_beats_a_reported_absence_in_the_digest(): void
    {
        Absence::report($this->child, '2026-08-05', AbsenceReason::Sick, $this->staff->id);
        $this->close('2026-08-05', '2026-08-05', 'Fortbildung');

        $digest = WeeklyDigestBuilder::for($this->parent, Carbon::parse('2026-08-03'));

        $this->assertSame('🚫 Fortbildung', $digest['children'][0]['days'][2]['summary']);
    }
}
