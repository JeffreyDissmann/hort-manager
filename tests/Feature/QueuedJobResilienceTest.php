<?php

declare(strict_types=1);

use App\Jobs\AskCompanionConfirmation;
use App\Jobs\SyncExcursionRsvp;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Excursion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

// Slack DMs are queued, and what they are about can be gone by the time the worker
// picks them up — a trip deleted right after it was created, an arrangement unwound.
// Such a job has nothing left to do; it must not pile up in failed_jobs, where a real
// delivery failure would then be hard to spot.

uses(RefreshDatabase::class);

beforeEach(function () {
    Http::fake();
    config(['queue.default' => 'database']);
});

/** Work off everything that is queued (creating/deleting a trip queues DMs too). */
function workOne(): void
{
    test()->artisan('queue:work', ['--stop-when-empty' => true, '--tries' => 3])->assertSuccessful();
}

it('drops the RSVP sync when the trip is gone', function () {
    $child = Child::factory()->create();
    $excursion = Excursion::factory()->create();

    SyncExcursionRsvp::dispatch($excursion, $child);
    $excursion->delete();

    workOne();

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('drops the companion ask when the day is gone', function () {
    $departure = DailyDeparture::factory()->create();

    AskCompanionConfirmation::dispatch($departure);
    $departure->delete();

    workOne();

    expect(DB::table('failed_jobs')->count())->toBe(0)
        ->and(DB::table('jobs')->count())->toBe(0);
});
