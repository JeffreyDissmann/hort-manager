<?php

declare(strict_types=1);

use App\Enums\StandbySwapStatus;
use App\Models\Child;
use App\Models\HolidayPeriod;
use App\Models\StandbyDuty;
use App\Models\StandbySwap;
use App\Models\User;
use App\Notifications\StandbyReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;

// The Slack side of the Springerdienst: Annehmen/Ablehnen straight from the DM, and
// the reminder the afternoon before.

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    Http::fake();
    config(['services.slack.notifications.bot_user_oauth_token' => 'xoxb-test']);
    $this->travelTo(Carbon::parse('2027-02-26 16:00')); // Friday
});

/** A signed interaction, as Slack posts it when someone clicks a button. */
function clickInSlack(string $slackId, string $value): TestResponse
{
    config(['services.slack.signing_secret' => 'test-secret']);
    $json = json_encode([
        'user' => ['id' => $slackId],
        'response_url' => 'https://hooks.slack.com/answer',
        'actions' => [['value' => $value]],
    ]);
    $body = 'payload='.urlencode($json);
    $timestamp = (string) time();

    return test()->call('POST', '/slack/interactions', ['payload' => $json], [], [], [
        'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        'HTTP_X-Slack-Request-Timestamp' => $timestamp,
        'HTTP_X-Slack-Signature' => 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", 'test-secret'),
    ], $body);
}

/** Emma's family (A) asks Paul's family (B) for Paul's Friday, offering Emma's Tuesday. */
function pendingStandbySwap(): array
{
    $parentA = User::factory()->slackLinked()->create();
    $parentB = User::factory()->slackLinked()->create();
    $emma = Child::factory()->withGuardian($parentA)->create(['name' => 'Emma']);
    $paul = Child::factory()->withGuardian($parentB)->create(['name' => 'Paul']);
    $dutyA = StandbyDuty::factory()->for($emma)->onDay('2027-03-02')->create();
    $dutyB = StandbyDuty::factory()->for($paul)->onDay('2027-03-05')->create();

    $swap = StandbySwap::create([
        'offered_duty_id' => $dutyA->id, 'offered_child_id' => $emma->id,
        'wanted_duty_id' => $dutyB->id, 'wanted_child_id' => $paul->id,
        'requested_by' => $parentA->id,
    ]);

    return [$swap, $parentA, $parentB, $dutyA, $dutyB];
}

it('swaps the dates when the other family clicks Annehmen in Slack', function () {
    [$swap, , $parentB, $dutyA, $dutyB] = pendingStandbySwap();

    clickInSlack($parentB->slack_id, "standby_swap|{$swap->id}|1")->assertNoContent();

    expect($swap->refresh()->status)->toBe(StandbySwapStatus::Accepted)
        ->and($swap->answered_by)->toBe($parentB->id)
        ->and($dutyA->refresh()->child_id)->toBe($swap->wanted_child_id)
        ->and($dutyB->refresh()->child_id)->toBe($swap->offered_child_id);
});

it('leaves the list alone on Ablehnen in Slack', function () {
    [$swap, , $parentB, $dutyA] = pendingStandbySwap();

    clickInSlack($parentB->slack_id, "standby_swap|{$swap->id}|0")->assertNoContent();

    expect($swap->refresh()->status)->toBe(StandbySwapStatus::Declined)
        ->and($dutyA->refresh()->child_id)->toBe($swap->offered_child_id);
});

it('does not let the asking family answer its own request from Slack', function () {
    [$swap, $parentA] = pendingStandbySwap();

    clickInSlack($parentA->slack_id, "standby_swap|{$swap->id}|1")->assertNoContent();

    expect($swap->refresh()->status)->toBe(StandbySwapStatus::Pending);
    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.com/answer'
        && str_contains($request['text'], 'nicht (mehr) möglich'));
});

it('says in the DM why a stale request could not be accepted', function () {
    [$swap, , $parentB, , $dutyB] = pendingStandbySwap();
    $dutyB->update(['child_id' => Child::factory()->withGuardian(User::factory()->create())->create()->id]);

    clickInSlack($parentB->slack_id, "standby_swap|{$swap->id}|1")->assertNoContent();

    expect($swap->refresh()->status)->toBe(StandbySwapStatus::Expired);
    Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.com/answer'
        && str_contains($request['text'], 'gilt nicht mehr'));
});

it('reminds on Friday afternoon the families on standby on Monday', function () {
    $parent = User::factory()->slackLinked()->create();
    $emma = Child::factory()->withGuardian($parent)->create(['name' => 'Emma']);
    StandbyDuty::factory()->for($emma)->onDay('2027-03-01')->create();   // Monday
    $other = User::factory()->slackLinked()->create();
    StandbyDuty::factory()->for(Child::factory()->withGuardian($other))->onDay('2027-03-02')->create();

    $this->artisan('standby:remind')->assertSuccessful();

    Notification::assertSentTo($parent, StandbyReminder::class, fn (StandbyReminder $n) => $n->date->toDateString() === '2027-03-01'
        && $n->children === ['Emma']);
    Notification::assertNotSentTo($other, StandbyReminder::class);
});

it('names every own child on standby that day in one reminder', function () {
    $this->travelTo(Carbon::parse('2027-03-05 16:00')); // Friday; Monday is a Ferienbetreuung day with two slots
    $period = HolidayPeriod::factory()->care()->between('2027-03-08', '2027-03-08')->create();
    $period->generateCareDays();
    $parent = User::factory()->slackLinked()->create();
    $mia = Child::factory()->withGuardian($parent)->create(['name' => 'Mia']);
    $ben = Child::factory()->withGuardian($parent)->create(['name' => 'Ben']);
    StandbyDuty::factory()->for($mia)->onDay('2027-03-08', 1)->create();
    StandbyDuty::factory()->for($ben)->onDay('2027-03-08', 2)->create();

    $this->artisan('standby:remind')->assertSuccessful();

    Notification::assertSentToTimes($parent, StandbyReminder::class, 1);
    Notification::assertSentTo($parent, StandbyReminder::class, fn (StandbyReminder $n) => $n->children === ['Ben', 'Mia']);
});

it('sends nothing for a next weekday the Hort is closed', function () {
    HolidayPeriod::factory()->between('2027-03-01', '2027-03-01')->create();
    $parent = User::factory()->slackLinked()->create();
    StandbyDuty::factory()->for(Child::factory()->withGuardian($parent))->onDay('2027-03-01')->create();

    $this->artisan('standby:remind')->assertSuccessful();

    Notification::assertNothingSent();
});

it('leaves out a child who has left by then', function () {
    $parent = User::factory()->slackLinked()->create();
    $child = Child::factory()->withGuardian($parent)->create(['active_until' => '2027-02-28']);
    StandbyDuty::factory()->for($child)->onDay('2027-03-01')->create();

    $this->artisan('standby:remind --dry-run')->assertSuccessful();
    $this->artisan('standby:remind')->assertSuccessful();

    Notification::assertNothingSent();
});
