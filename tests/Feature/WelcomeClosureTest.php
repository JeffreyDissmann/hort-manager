<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\HolidayPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The Urlaubsschirm: while the Hort is shut, the landing page leads with the holiday
 * and says when it opens again. Guest-accessible, like the page itself — closures are
 * open information.
 */
class WelcomeClosureTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->travelTo(Carbon::parse('2026-08-03 09:00')); // Monday
    }

    public function test_an_open_day_shows_no_holiday_screen(): void
    {
        HolidayPeriod::factory()->between('2026-08-10', '2026-08-14')->create();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->component('Welcome')
            ->where('closure', null)
            ->where('nextOpen', null));
    }

    public function test_a_closed_day_leads_with_the_holiday(): void
    {
        HolidayPeriod::factory()->between('2026-08-03', '2026-08-07')->create([
            'name' => 'Sommerferien',
            'note' => 'Wir sind ab dem 10. wieder da',
        ]);

        // Shut Mo–Fr, so the answer is the Monday after — not „ends_on + 1", which
        // would be a Saturday.
        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('closure.name', 'Sommerferien')
            ->where('closure.note', 'Wir sind ab dem 10. wieder da')
            ->where('closure.days_left', 5)
            ->where('nextOpen.date', '2026-08-10')
            ->where('nextOpen.care', null));
    }

    public function test_a_ferienbetreuung_counts_as_open_and_is_named(): void
    {
        HolidayPeriod::factory()->between('2026-08-03', '2026-08-07')->create();

        $care = HolidayPeriod::factory()->care()->create([
            'name' => 'Sommer-Ferienbetreuung',
            'starts_on' => '2026-08-10',
            'ends_on' => '2026-08-14',
        ]);
        $care->generateCareDays();

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('nextOpen.date', '2026-08-10')
            ->where('nextOpen.care', 'Sommer-Ferienbetreuung'));
    }

    public function test_a_second_closure_is_skipped_on_the_way_to_the_next_open_day(): void
    {
        HolidayPeriod::factory()->between('2026-08-03', '2026-08-07')->create();
        HolidayPeriod::factory()->onDay('2026-08-10')->create(['name' => 'Brückentag']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('nextOpen.date', '2026-08-11'));
    }

    public function test_the_last_closed_day_says_only_today(): void
    {
        HolidayPeriod::factory()->onDay('2026-08-03')->create(['name' => 'Fortbildung']);

        $this->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('closure.days_left', 1)
            ->where('nextOpen.date', '2026-08-04'));
    }
}
