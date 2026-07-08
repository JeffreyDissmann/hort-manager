<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Absence;
use App\Models\Child;
use App\Models\CompanionSlackMessage;
use App\Models\DailyDeparture;
use App\Models\DailyProgram;
use App\Models\Excursion;
use App\Models\ExcursionSlackMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class PruneOldDataTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_prunes_data_older_than_the_retention_period(): void
    {
        config(['hort.retention_weeks' => 4]);
        Carbon::setTestNow('2026-06-28'); // cutoff = 2026-05-31
        Http::fake();

        $child = Child::factory()->create();

        $oldDeparture = DailyDeparture::factory()->create(['child_id' => $child->id, 'date' => '2026-05-01']);
        $recentDeparture = DailyDeparture::factory()->create(['child_id' => $child->id, 'date' => '2026-06-25']);
        $oldProgram = DailyProgram::factory()->create(['date' => '2026-05-01']);
        $recentProgram = DailyProgram::factory()->create(['date' => '2026-06-26']);
        $oldExcursion = Excursion::factory()->create(['date' => '2026-05-01']);
        $recentExcursion = Excursion::factory()->create(['date' => '2026-06-27']);
        $oldAbsence = Absence::create(['child_id' => $child->id, 'date' => '2026-05-01', 'reason' => 'sick']);
        $recentAbsence = Absence::create(['child_id' => $child->id, 'date' => '2026-06-25', 'reason' => 'away']);

        // A tracked Slack DM for the old excursion (would be "cancelled" on a normal delete).
        ExcursionSlackMessage::create([
            'excursion_id' => $oldExcursion->id,
            'user_id' => User::factory()->create()->id,
            'channel' => 'D1',
            'ts' => '1.1',
        ]);

        // A tracked companion Slack DM on the old departure — must cascade away too.
        CompanionSlackMessage::create([
            'daily_departure_id' => $oldDeparture->id,
            'user_id' => User::factory()->create()->id,
            'channel' => 'D2',
            'ts' => '2.2',
        ]);

        $this->artisan('hort:prune-old-data')->assertSuccessful();

        $this->assertModelMissing($oldDeparture);
        $this->assertModelExists($recentDeparture);
        $this->assertModelMissing($oldProgram);
        $this->assertModelExists($recentProgram);
        $this->assertModelMissing($oldExcursion);
        $this->assertModelExists($recentExcursion);
        $this->assertModelMissing($oldAbsence);
        $this->assertModelExists($recentAbsence);

        // The old excursion's + old departure's tracked messages cascaded away with them.
        $this->assertDatabaseMissing('excursion_slack_messages', ['excursion_id' => $oldExcursion->id]);
        $this->assertDatabaseMissing('companion_slack_messages', ['daily_departure_id' => $oldDeparture->id]);
    }

    public function test_pruning_old_excursions_sends_no_slack_messages(): void
    {
        config(['hort.retention_weeks' => 4]);
        Carbon::setTestNow('2026-06-28');
        Http::fake();

        $excursion = Excursion::factory()->create(['date' => '2026-05-01']);
        ExcursionSlackMessage::create([
            'excursion_id' => $excursion->id,
            'user_id' => User::factory()->create()->id,
            'channel' => 'D1',
            'ts' => '1.1',
        ]);

        $this->artisan('hort:prune-old-data')->assertSuccessful();

        // No "Ausflug abgesagt" DM for a weeks-old trip (mass delete skips the observer).
        Http::assertNothingSent();
    }

    public function test_the_retention_period_is_configurable(): void
    {
        config(['hort.retention_weeks' => 1]);
        Carbon::setTestNow('2026-06-28'); // cutoff = 2026-06-21
        Http::fake();

        $child = Child::factory()->create();
        $justInside = DailyDeparture::factory()->create(['child_id' => $child->id, 'date' => '2026-06-22']);
        $justOutside = DailyDeparture::factory()->create(['child_id' => $child->id, 'date' => '2026-06-20']);

        $this->artisan('hort:prune-old-data')->assertSuccessful();

        $this->assertModelExists($justInside);
        $this->assertModelMissing($justOutside);
    }
}
