<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\DailyProgram;
use App\Models\User;
use App\Notifications\ProgramMissing;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Staff are nudged an hour before the parent digest when a lunch is still missing. */
class ProgramMissingReminderTest extends TestCase
{
    use RefreshDatabase;

    private User $staff;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->travelTo(Carbon::parse('2026-06-22 11:00')); // Monday

        $this->staff = User::factory()->create(['role' => UserRole::Staff, 'slack_id' => 'U-STAFF']);
    }

    /** @param  list<string>  $dates */
    private function fillLunch(array $dates): void
    {
        foreach ($dates as $date) {
            DailyProgram::create(['date' => $date, 'lunch' => 'Nudeln']);
        }
    }

    /** @return list<string> */
    private function week(): array
    {
        return ['2026-06-22', '2026-06-23', '2026-06-24', '2026-06-25', '2026-06-26'];
    }

    public function test_it_reminds_staff_when_a_lunch_is_missing(): void
    {
        $this->fillLunch(['2026-06-22', '2026-06-23']);

        $this->artisan('program:remind-missing')->assertSuccessful();

        Notification::assertSentTo($this->staff, ProgramMissing::class, function (ProgramMissing $notification) {
            $dates = array_map(fn (Carbon $d): string => $d->toDateString(), $notification->missingDays);

            return $dates === ['2026-06-24', '2026-06-25', '2026-06-26'];
        });
    }

    public function test_it_stays_quiet_when_every_weekday_has_lunch(): void
    {
        $this->fillLunch($this->week());

        $this->artisan('program:remind-missing')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_an_activity_alone_does_not_count_as_filled_in(): void
    {
        $this->fillLunch(['2026-06-23', '2026-06-24', '2026-06-25', '2026-06-26']);
        DailyProgram::create(['date' => '2026-06-22', 'activity' => 'Basteln']);

        $this->artisan('program:remind-missing')->assertSuccessful();

        Notification::assertSentTo($this->staff, ProgramMissing::class);
    }

    public function test_it_ignores_the_weekend_and_other_weeks(): void
    {
        $this->fillLunch($this->week());
        // Saturday of this week and the next Monday stay empty — neither counts.

        $this->artisan('program:remind-missing')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_parents_are_not_reminded(): void
    {
        $parent = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U-PARENT']);

        $this->artisan('program:remind-missing')->assertSuccessful();

        Notification::assertNothingSentTo($parent);
        Notification::assertSentTo($this->staff, ProgramMissing::class);
    }

    public function test_staff_who_switched_the_category_off_are_skipped(): void
    {
        $this->staff->notification_preferences = ['program_missing' => ['slack' => false, 'push' => false]];
        $this->staff->save();

        $this->artisan('program:remind-missing')->assertSuccessful();

        Notification::assertNothingSentTo($this->staff);
    }

    public function test_dry_run_sends_nothing(): void
    {
        $this->artisan('program:remind-missing --dry-run')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_dry_run_lists_the_missing_days(): void
    {
        $this->fillLunch(['2026-06-22']);

        $this->artisan('program:remind-missing --dry-run')
            ->expectsOutputToContain('2026-06-23')
            ->assertSuccessful();
    }
}
