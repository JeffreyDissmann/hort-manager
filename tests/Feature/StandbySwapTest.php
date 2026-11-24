<?php

declare(strict_types=1);

use App\Enums\StandbySwapStatus;
use App\Models\Child;
use App\Models\HolidayPeriod;
use App\Models\StandbyDuty;
use App\Models\StandbySwap;
use App\Models\User;
use App\Notifications\StandbySwapAnswered;
use App\Notifications\StandbySwapCompleted;
use App\Notifications\StandbySwapRequested;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia;
use NotificationChannels\WebPush\WebPushChannel;

uses(RefreshDatabase::class);

beforeEach(function () {
    Notification::fake();
    // Slack-linked users with a bot token, so the notifications have a channel to go to;
    // the interactive request DM goes straight to the Slack API.
    config(['services.slack.notifications.bot_user_oauth_token' => 'xoxb-test']);
    Http::fake(['slack.com/api/*' => Http::response(['ok' => true, 'channel' => 'D1', 'ts' => '1.1'])]);
    $this->travelTo(Carbon::parse('2027-02-26 09:00')); // Friday

    // Family A (Emma) holds Tue 2 March, family B (Paul) holds Fri 5 March.
    $this->parentA = User::factory()->slackLinked()->create();
    $this->parentB = User::factory()->slackLinked()->create();
    $this->emma = Child::factory()->withGuardian($this->parentA)->create(['name' => 'Emma']);
    $this->paul = Child::factory()->withGuardian($this->parentB)->create(['name' => 'Paul']);
    $this->dutyA = StandbyDuty::factory()->for($this->emma)->onDay('2027-03-02')->create();
    $this->dutyB = StandbyDuty::factory()->for($this->paul)->onDay('2027-03-05')->create();
});

function askSwap(User $user, StandbyDuty $offered, StandbyDuty $wanted)
{
    return test()->actingAs($user)->post('/standby/swaps', ['offered_duty_id' => $offered->id, 'wanted_duty_id' => $wanted->id]);
}

it('lets a family ask another to swap and tells the other family', function () {
    askSwap($this->parentA, $this->dutyA, $this->dutyB)->assertRedirect()->assertSessionHasNoErrors();

    $swap = StandbySwap::sole();
    expect($swap->status)->toBe(StandbySwapStatus::Pending)
        ->and($swap->wanted_child_id)->toBe($this->paul->id)
        ->and($swap->slackMessages()->sole()->user_id)->toBe($this->parentB->id);
    Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage'
        && $request['channel'] === $this->parentB->slack_id
        && str_contains(json_encode($request['blocks']), "standby_swap|{$swap->id}|1"));
    Http::assertSentCount(1);
});

it('asks by web push too, and leaves Slack to the button DM', function () {
    $this->parentB->updatePushSubscription('https://push.example/b', 'k', 'a');

    askSwap($this->parentA, $this->dutyA, $this->dutyB);

    Notification::assertSentTo($this->parentB, StandbySwapRequested::class, fn ($n, array $channels) => $channels === [WebPushChannel::class]);
    Notification::assertNotSentTo($this->parentA, StandbySwapRequested::class);
});

it('replaces the buttons in the DM once the request is answered', function () {
    askSwap($this->parentA, $this->dutyA, $this->dutyB);

    $this->actingAs($this->parentB)->patch('/standby/swaps/'.StandbySwap::sole()->id.'/decline');

    Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.update'
        && $request['ts'] === '1.1'
        && str_contains($request['text'], 'Abgelehnt'));
});

it('tells the DM when the asking family withdraws', function () {
    askSwap($this->parentA, $this->dutyA, $this->dutyB);

    $this->actingAs($this->parentA)->delete('/standby/swaps/'.StandbySwap::sole()->id);

    Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.update'
        && str_contains($request['text'], 'zurückgezogen'));
});

it('exchanges the two dates when the other family accepts', function () {
    askSwap($this->parentA, $this->dutyA, $this->dutyB);
    $coordinator = User::factory()->standbyCoordinator()->slackLinked()->create();

    $this->actingAs($this->parentB)->patch('/standby/swaps/'.StandbySwap::sole()->id.'/accept')->assertRedirect();

    expect($this->dutyA->refresh()->child_id)->toBe($this->paul->id)
        ->and($this->dutyB->refresh()->child_id)->toBe($this->emma->id)
        ->and(StandbySwap::sole()->status)->toBe(StandbySwapStatus::Accepted);
    Notification::assertSentTo($this->parentA, StandbySwapAnswered::class, fn ($n) => $n->accepted);
    Notification::assertSentTo($coordinator, StandbySwapCompleted::class);
});

it('leaves the list alone when the other family declines', function () {
    askSwap($this->parentA, $this->dutyA, $this->dutyB);

    $this->actingAs($this->parentB)->patch('/standby/swaps/'.StandbySwap::sole()->id.'/decline');

    expect($this->dutyA->refresh()->child_id)->toBe($this->emma->id)
        ->and(StandbySwap::sole()->status)->toBe(StandbySwapStatus::Declined);
    Notification::assertSentTo($this->parentA, StandbySwapAnswered::class, fn ($n) => ! $n->accepted);
});

it('lets the asking family withdraw, and nobody else', function () {
    askSwap($this->parentA, $this->dutyA, $this->dutyB);
    $swap = StandbySwap::sole();

    $this->actingAs($this->parentB)->delete("/standby/swaps/{$swap->id}")->assertForbidden();
    $this->actingAs($this->parentA)->delete("/standby/swaps/{$swap->id}")->assertRedirect();

    expect($swap->refresh()->status)->toBe(StandbySwapStatus::Cancelled);
});

it('only lets the other family answer', function () {
    askSwap($this->parentA, $this->dutyA, $this->dutyB);
    $swap = StandbySwap::sole();

    $this->actingAs($this->parentA)->patch("/standby/swaps/{$swap->id}/accept")->assertForbidden();
    $this->actingAs(User::factory()->create())->patch("/standby/swaps/{$swap->id}/accept")->assertForbidden();

    expect($this->dutyA->refresh()->child_id)->toBe($this->emma->id);
});

it('refuses to offer a date that is not yours', function () {
    askSwap(User::factory()->create(), $this->dutyA, $this->dutyB)->assertForbidden();
});

it('refuses a swap within one family', function () {
    $sister = Child::factory()->withGuardian($this->parentA)->create();
    $sisterDuty = StandbyDuty::factory()->for($sister)->onDay('2027-03-03')->create();

    askSwap($this->parentA, $this->dutyA, $sisterDuty)->assertSessionHasErrors('swap');
});

it('refuses a date that has already come', function () {
    $today = StandbyDuty::factory()->for($this->paul)->onDay('2027-02-26')->create();

    askSwap($this->parentA, $this->dutyA, $today)->assertSessionHasErrors('swap');
});

it('allows one open request per offered date', function () {
    $other = StandbyDuty::factory()->for(Child::factory()->withGuardian(User::factory()->create()))->onDay('2027-03-04')->create();
    askSwap($this->parentA, $this->dutyA, $this->dutyB);

    askSwap($this->parentA, $this->dutyA, $other)->assertSessionHasErrors('swap');
});

it('allows a swap that puts one family on both slots of a Ferienbetreuung day', function () {
    $period = HolidayPeriod::factory()->care()->between('2027-03-08', '2027-03-08')->create();
    $period->generateCareDays();
    $sister = Child::factory()->withGuardian($this->parentA)->create();
    StandbyDuty::factory()->for($sister)->onDay('2027-03-08', 1)->create();
    $careDuty = StandbyDuty::factory()->for($this->paul)->onDay('2027-03-08', 2)->create();

    askSwap($this->parentA, $this->dutyA, $careDuty)->assertSessionHasNoErrors();
    $this->actingAs($this->parentB)->patch('/standby/swaps/'.StandbySwap::sole()->id.'/accept')->assertSessionHasNoErrors();

    expect($careDuty->refresh()->child_id)->toBe($this->emma->id);
});

it('voids a request when a date changed hands before the answer', function () {
    askSwap($this->parentA, $this->dutyA, $this->dutyB);
    $swap = StandbySwap::sole();
    // The coordinator moves Paul's Friday to a third family.
    $third = Child::factory()->withGuardian(User::factory()->create())->create();
    $this->dutyB->update(['child_id' => $third->id]);

    $this->actingAs($this->parentB)->patch("/standby/swaps/{$swap->id}/accept")->assertSessionHasErrors('swap');

    expect($this->dutyB->refresh()->child_id)->toBe($third->id)
        ->and($this->dutyA->refresh()->child_id)->toBe($this->emma->id)
        ->and($swap->refresh()->status)->toBe(StandbySwapStatus::Expired);
});

it('expires other open requests on the same dates once one is accepted', function () {
    $carl = Child::factory()->withGuardian($parentC = User::factory()->create())->create();
    $dutyC = StandbyDuty::factory()->for($carl)->onDay('2027-03-04')->create();
    askSwap($this->parentA, $this->dutyA, $this->dutyB);
    askSwap($parentC, $dutyC, $this->dutyB);

    $first = StandbySwap::where('offered_duty_id', $this->dutyA->id)->sole();
    $this->actingAs($this->parentB)->patch("/standby/swaps/{$first->id}/accept");

    expect(StandbySwap::where('offered_duty_id', $dutyC->id)->sole()->status)->toBe(StandbySwapStatus::Expired);
});

it('gives the coordinator — and only them — a toggle for the swap note', function () {
    $this->actingAs(User::factory()->standbyCoordinator()->create())
        ->get('/notifications')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('categories', fn ($c) => collect($c)->contains('standby_swaps')));

    $this->actingAs(User::factory()->admin()->create())
        ->get('/notifications')
        ->assertInertia(fn (AssertableInertia $page) => $page->where('categories', fn ($c) => ! collect($c)->contains('standby_swaps')));
});

it('shows each family its own requests on the page', function () {
    askSwap($this->parentA, $this->dutyA, $this->dutyB);

    $this->actingAs($this->parentB)
        ->get('/standby?month=2027-03')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('swaps.incoming', 1)
            ->where('swaps.incoming.0.offered.child', 'Emma')
            ->has('swaps.outgoing', 0)
            ->where('ownUpcoming.0.duty_id', $this->dutyB->id));

    $this->actingAs($this->parentA)
        ->get('/standby?month=2027-03')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->has('swaps.incoming', 0)
            ->has('swaps.outgoing', 1));
});
