<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Excursion;
use App\Models\HolidayPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/** An Ausflug can't be scheduled on a day the Hort is shut. */
class ClosedExcursionTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-03 09:00')); // Monday
        $this->staff = User::factory()->create(['role' => UserRole::Staff]);
    }

    public function test_an_excursion_cannot_be_created_on_a_closed_day(): void
    {
        HolidayPeriod::factory()->onDay('2026-08-05')->create(['name' => 'Fortbildung']);

        $this->actingAs($this->staff)
            ->post(route('excursions.store'), [
                'name' => 'Zoo',
                'date' => '2026-08-05',
                'rsvp_deadline' => '2026-08-04',
            ])
            ->assertSessionHasErrors('date');

        $this->assertDatabaseEmpty('excursions');
    }

    public function test_an_excursion_on_an_open_day_is_fine(): void
    {
        HolidayPeriod::factory()->onDay('2026-08-05')->create();

        $this->actingAs($this->staff)
            ->post(route('excursions.store'), [
                'name' => 'Zoo',
                'date' => '2026-08-06',
                'rsvp_deadline' => '2026-08-04',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('excursions', ['name' => 'Zoo']);
    }

    public function test_an_excursion_cannot_be_moved_onto_a_closed_day(): void
    {
        $excursion = Excursion::create(['name' => 'Zoo', 'date' => '2026-08-06', 'rsvp_deadline' => '2026-08-04']);
        HolidayPeriod::factory()->onDay('2026-08-05')->create();

        $this->actingAs($this->staff)
            ->patch(route('excursions.update', $excursion), [
                'name' => 'Zoo',
                'date' => '2026-08-05',
                'rsvp_deadline' => '2026-08-04',
            ])
            ->assertSessionHasErrors('date');

        $this->assertSame('2026-08-06', $excursion->fresh()->date->toDateString());
    }
}
