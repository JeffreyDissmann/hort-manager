<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\HolidayPeriodType;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\HolidayCareDay;
use App\Models\HolidayPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * A period's type is fixed once created. Converting a Ferienbetreuung to a
 * Schließzeit un-offers every day and deletes every sign-up with it — from a toggle
 * at the top of an edit form, with no confirmation and nothing to restore.
 */
class HolidayPeriodTypeIsFixedTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    private HolidayPeriod $care;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-03 09:00'));
        $this->staff = User::factory()->create(['role' => UserRole::Staff]);

        $this->care = HolidayPeriod::factory()->care()->create([
            'name' => 'Sommer-Ferienbetreuung',
            'starts_on' => '2026-08-10',
            'ends_on' => '2026-08-14',
            'registration_deadline' => '2026-08-07',
        ]);
        $this->care->generateCareDays();

        $child = Child::factory()->create(['name' => 'Mia']);
        DailyDeparture::create([
            'child_id' => $child->id,
            'date' => '2026-08-10',
            'planned_time' => '16:30',
            'planned_method' => DepartureMethod::PickedUp,
            'status' => DepartureStatus::Present,
        ]);
    }

    /** @param  array<string, mixed>  $overrides */
    private function edit(array $overrides = []): TestResponse
    {
        return $this->actingAs($this->staff)->patch(route('closures.update', $this->care), array_merge([
            'name' => 'Sommer-Ferienbetreuung',
            'starts_on' => '2026-08-10',
            'ends_on' => '2026-08-14',
            'registration_deadline' => '2026-08-07',
        ], $overrides));
    }

    public function test_a_ferienbetreuung_cannot_be_turned_into_a_closure(): void
    {
        $this->edit(['type' => HolidayPeriodType::Closed->value])->assertRedirect();

        $this->care->refresh();
        $this->assertTrue($this->care->isCare());
        $this->assertSame(5, $this->care->careDays()->count());
        $this->assertDatabaseCount('daily_departures', 1);
    }

    public function test_omitting_the_type_does_not_convert_it_either(): void
    {
        // The dangerous case: a client PATCHing only the deadline used to fall through
        // to the „closed" default and take the whole Ferienbetreuung with it.
        $this->edit(['registration_deadline' => '2026-08-05'])->assertRedirect();

        $this->care->refresh();
        $this->assertTrue($this->care->isCare());
        $this->assertSame('2026-08-05', $this->care->registration_deadline->toDateString());
        $this->assertSame(5, $this->care->careDays()->count());
        $this->assertDatabaseCount('daily_departures', 1);
    }

    public function test_a_closure_cannot_be_turned_into_a_ferienbetreuung(): void
    {
        $closure = HolidayPeriod::factory()->between('2026-09-07', '2026-09-11')->create();

        $this->actingAs($this->staff)
            ->patch(route('closures.update', $closure), [
                'name' => $closure->name,
                'type' => HolidayPeriodType::Care->value,
                'starts_on' => '2026-09-07',
                'ends_on' => '2026-09-11',
                'registration_deadline' => '2026-09-01',
            ])
            ->assertRedirect();

        $closure->refresh();
        $this->assertFalse($closure->isCare());
        // Still a closure, so it has neither offered days nor a deadline.
        $this->assertSame(0, $closure->careDays()->count());
        $this->assertNull($closure->registration_deadline);
    }

    public function test_the_type_is_still_chosen_when_creating(): void
    {
        $this->actingAs($this->staff)
            ->post(route('closures.store'), [
                'name' => 'Herbst-Ferienbetreuung',
                'type' => HolidayPeriodType::Care->value,
                'starts_on' => '2026-10-05',
                'ends_on' => '2026-10-09',
                'registration_deadline' => '2026-10-01',
            ])
            ->assertRedirect();

        $this->assertTrue(HolidayPeriod::firstWhere('name', 'Herbst-Ferienbetreuung')->isCare());
    }

    public function test_editing_a_ferienbetreuung_still_moves_its_days(): void
    {
        // The type being fixed must not stop the range from being edited.
        $this->edit(['ends_on' => '2026-08-12'])->assertRedirect();

        $this->assertSame(3, $this->care->refresh()->careDays()->count());
        $this->assertFalse(HolidayCareDay::query()->onDate('2026-08-13')->exists());
    }
}
