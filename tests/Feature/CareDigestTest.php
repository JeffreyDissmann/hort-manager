<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\DailyProgram;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\HomeworkDefault;
use App\Models\User;
use App\Models\WeeklySchedule;
use App\Support\WeeklyDigestBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The Monday Wochenüberblick during a Ferienbetreuung week. */
class CareDigestTest extends TestCase
{
    use RefreshDatabase;

    private User $parent;

    private Child $child;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-03 12:00')); // Monday

        $this->parent = User::factory()->create(['role' => UserRole::Parent]);
        $this->child = Child::factory()->create(['name' => 'Mia']);
        $this->parent->children()->attach($this->child);

        foreach ([1, 2, 3, 4, 5] as $weekday) {
            WeeklySchedule::create([
                'child_id' => $this->child->id,
                'weekday' => $weekday,
                'planned_time' => '15:00',
                'method' => DepartureMethod::PickedUp,
            ]);
            HomeworkDefault::create(['weekday' => $weekday, 'start_time' => '14:00', 'end_time' => '15:00']);
        }
    }

    /** Ferienbetreuung Mon–Tue of this week. */
    private function careDays(): void
    {
        $period = HolidayPeriod::factory()->care()->create([
            'name' => 'Sommer-Ferienbetreuung',
            'starts_on' => '2026-08-03',
            'ends_on' => '2026-08-04',
        ]);
        $period->generateCareDays();
    }

    private function signUp(string $date): void
    {
        $day = HolidayCareDay::firstWhere('date', $date);

        DailyDeparture::create([
            'child_id' => $this->child->id,
            'date' => $date,
            'planned_time' => $day->ends_at,
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);
    }

    private function digest(): array
    {
        return WeeklyDigestBuilder::for($this->parent, Carbon::parse('2026-08-03'));
    }

    public function test_a_care_day_reports_the_betreuungszeit_instead_of_homework(): void
    {
        $this->careDays();

        $digest = $this->digest();

        $this->assertSame('08:30–16:30', $digest['program'][0]['care']);
        $this->assertNull($digest['program'][0]['homework']);
        // Wednesday is a normal day again.
        $this->assertNull($digest['program'][2]['care']);
        $this->assertSame('14:00–15:00', $digest['program'][2]['homework']);
    }

    public function test_food_and_activity_still_come_through_on_a_care_day(): void
    {
        $this->careDays();
        DailyProgram::create(['date' => '2026-08-03', 'lunch' => 'Nudeln', 'activity' => 'Schwimmbad']);

        $digest = $this->digest();

        $this->assertSame('Nudeln', $digest['program'][0]['lunch']);
        $this->assertSame('Schwimmbad', $digest['program'][0]['activity']);
    }

    public function test_a_child_without_a_sign_up_is_reported_as_not_registered(): void
    {
        $this->careDays();

        $days = $this->digest()['children'][0]['days'];

        // The Stammplan's 15:00 must not be reported for a holiday they aren't at.
        $this->assertSame('nicht angemeldet', $days[0]['summary']);
        $this->assertSame('nicht angemeldet', $days[1]['summary']);
    }

    public function test_a_signed_up_child_gets_their_actual_plan(): void
    {
        $this->careDays();
        $this->signUp('2026-08-03');

        $days = $this->digest()['children'][0]['days'];

        $this->assertStringContainsString('16:30', $days[0]['summary']);
        $this->assertSame('nicht angemeldet', $days[1]['summary']);
    }

    public function test_the_normal_days_of_the_week_keep_their_stammplan(): void
    {
        $this->careDays();

        $days = $this->digest()['children'][0]['days'];

        $this->assertStringContainsString('15:00', $days[2]['summary']);
    }

    public function test_a_week_without_care_is_unchanged(): void
    {
        $digest = $this->digest();

        $this->assertNull($digest['program'][0]['care']);
        $this->assertStringContainsString('15:00', $digest['children'][0]['days'][0]['summary']);
    }
}
