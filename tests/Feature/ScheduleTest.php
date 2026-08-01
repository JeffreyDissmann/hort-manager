<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Setting;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schedule as ScheduleFacade;
use Tests\TestCase;

/**
 * The scheduled jobs are the one part of the app with no UI feedback — if a cron
 * expression silently drifts, the digest just stops arriving. These pin the wiring.
 */
class ScheduleTest extends TestCase
{
    use RefreshDatabase;

    /** The cron expression of the scheduled command whose signature contains `$needle`. */
    private function expressionFor(string $needle): ?string
    {
        return collect(app(Schedule::class)->events())
            ->first(fn (Event $event): bool => str_contains($event->command ?? '', $needle))
            ?->expression;
    }

    public function test_the_digest_and_its_staff_reminder_run_on_monday_at_the_configured_time(): void
    {
        $this->assertSame('0 12 * * 1', $this->expressionFor('weekly:digest'));
        $this->assertSame('30 11 * * 1', $this->expressionFor('program:remind-missing'));
    }

    public function test_changing_the_digest_time_moves_both_jobs(): void
    {
        Setting::set(Setting::WeeklyDigestTime, '15:30');

        // schedule:run re-registers the schedule on every tick, so the new time applies
        // without a deploy — replay that by binding a fresh Schedule and re-reading the
        // routes file, rather than rebooting (which would drop the :memory: database).
        $this->reregisterSchedule();

        $this->assertSame('30 15 * * 1', $this->expressionFor('weekly:digest'));
        $this->assertSame('0 15 * * 1', $this->expressionFor('program:remind-missing'));
    }

    private function reregisterSchedule(): void
    {
        $this->app->instance(Schedule::class, new Schedule);
        ScheduleFacade::clearResolvedInstance(Schedule::class);

        require base_path('routes/console.php');
    }

    public function test_the_other_scheduled_jobs_keep_their_fixed_times(): void
    {
        $this->assertSame('0 8 * * *', $this->expressionFor('excursions:remind-rsvps'));
        $this->assertSame('0 3 * * *', $this->expressionFor('hort:prune-old-data'));
    }
}
