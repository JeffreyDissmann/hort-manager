<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\TimeQualifier;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Setting;
use App\Models\User;
use App\Support\LateChange;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** The DM body and the „is this late?" rule, pinned line by line. */
class LateChangeDescriptionTest extends TestCase
{
    use RefreshDatabase;

    private function departure(array $attributes): DailyDeparture
    {
        return new DailyDeparture($attributes);
    }

    public function test_it_describes_a_pickup_with_its_time(): void
    {
        $this->assertSame('Wird abgeholt 15:30', LateChange::describePlan($this->departure([
            'planned_method' => DepartureMethod::PickedUp,
            'planned_time' => '15:30:00',
        ])));
    }

    public function test_it_describes_each_time_qualifier(): void
    {
        $sentHome = fn (?TimeQualifier $qualifier): string => LateChange::describePlan($this->departure([
            'planned_method' => DepartureMethod::SentHome,
            'planned_time' => '16:00:00',
            'time_qualifier' => $qualifier,
        ]));

        $this->assertSame('Geht allein nach Hause ab 16:00', $sentHome(TimeQualifier::From));
        $this->assertSame('Geht allein nach Hause bis 16:00', $sentHome(TimeQualifier::By));
        $this->assertSame('Geht allein nach Hause um 16:00', $sentHome(TimeQualifier::At));
        // A null qualifier is the implicit „genau um".
        $this->assertSame('Geht allein nach Hause um 16:00', $sentHome(null));
    }

    public function test_it_names_the_companion(): void
    {
        $companion = Child::factory()->create(['name' => 'Ben']);

        $this->assertSame('geht mit Ben mit', LateChange::describePlan($this->departure([
            'planned_method' => DepartureMethod::WithChild,
            'companion_child_id' => $companion->id,
        ])));
    }

    public function test_it_falls_back_when_the_companion_is_gone(): void
    {
        $this->assertSame('geht mit einem anderen Kind mit', LateChange::describePlan($this->departure([
            'planned_method' => DepartureMethod::WithChild,
            'companion_child_id' => 999,
        ])));
    }

    public function test_it_falls_back_when_there_is_no_time(): void
    {
        $this->assertSame('Wird abgeholt', LateChange::describePlan($this->departure([
            'planned_method' => DepartureMethod::PickedUp,
        ])));

        $this->assertSame('kein Plan', LateChange::describePlan($this->departure([])));
    }

    public function test_the_cutoff_minute_itself_already_counts_as_late(): void
    {
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        Setting::set(Setting::LateChangeCutoff, '12:00');

        $this->travelTo(Carbon::parse('2026-06-22 11:59'));
        $this->assertFalse(LateChange::applies($parent, '2026-06-22'));

        $this->travelTo(Carbon::parse('2026-06-22 12:00'));
        $this->assertTrue(LateChange::applies($parent, '2026-06-22'));
    }

    public function test_yesterday_is_not_late_however_late_it_is(): void
    {
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $this->travelTo(Carbon::parse('2026-06-22 23:59'));

        $this->assertFalse(LateChange::applies($parent, '2026-06-21'));
        $this->assertFalse(LateChange::applies($parent, '2026-06-23'));
    }
}
