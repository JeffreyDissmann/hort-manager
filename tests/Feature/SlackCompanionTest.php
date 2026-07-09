<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\CompanionSlackMessage;
use App\Models\DailyDeparture;
use App\Models\User;
use App\Services\SlackCompanion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Direct coverage of the outbound Slack side of „geht mit einem anderen Kind mit"
 * (SlackCompanion): posting the interactive DM, remembering it, chat.update on re-ask
 * and on answer, the cancel note, and the no-token silent no-op.
 */
class SlackCompanionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.slack.notifications.bot_user_oauth_token' => 'xoxb-test']);
    }

    /** An awaiting „geht mit … mit" departure whose companion has one Slack guardian. */
    private function awaitingDeparture(?User $companionGuardian = null): DailyDeparture
    {
        $tom = Child::factory()->create(['name' => 'Tom']);
        $emma = Child::factory()->create(['name' => 'Emma']);

        $guardian = $companionGuardian ?? User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U-emma']);
        $emma->guardians()->attach($guardian);

        return DailyDeparture::create([
            'child_id' => $tom->id,
            'date' => '2026-06-24',
            'planned_method' => DepartureMethod::WithChild,
            'companion_child_id' => $emma->id,
            'companion_confirmed' => null,
            'status' => DepartureStatus::Present,
        ]);
    }

    public function test_ask_posts_an_interactive_dm_and_remembers_it(): void
    {
        Http::fake(['slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'channel' => 'D1', 'ts' => 'ts1'])]);

        $departure = $this->awaitingDeparture();

        app(SlackCompanion::class)->ask($departure);

        Http::assertSent(fn ($r) => $r->url() === 'https://slack.com/api/chat.postMessage'
            && $r['channel'] === 'U-emma'
            && str_contains(json_encode($r['blocks']), 'companion|'.$departure->id.'|1'));
        Http::assertSentCount(1);

        $this->assertDatabaseHas('companion_slack_messages', [
            'daily_departure_id' => $departure->id,
            'channel' => 'D1',
            'ts' => 'ts1',
        ]);
    }

    public function test_ask_updates_the_same_dm_on_re_ask_instead_of_posting_again(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'channel' => 'D1', 'ts' => 'ts1']),
            'slack.com/api/chat.update' => Http::response(['ok' => true]),
        ]);

        $departure = $this->awaitingDeparture();
        $service = app(SlackCompanion::class);

        $service->ask($departure); // first ask → postMessage
        $service->ask($departure); // re-ask (e.g. reopen) → chat.update on the same DM

        Http::assertSent(fn ($r) => $r->url() === 'https://slack.com/api/chat.update' && $r['ts'] === 'ts1');
        Http::assertSentCount(2);
        $this->assertSame(1, CompanionSlackMessage::where('daily_departure_id', $departure->id)->count());
    }

    public function test_sync_updates_every_remembered_dm_with_the_result(): void
    {
        Http::fake(['slack.com/api/chat.update' => Http::response(['ok' => true])]);

        $departure = $this->awaitingDeparture();
        // Two remembered guardian DMs.
        CompanionSlackMessage::create(['daily_departure_id' => $departure->id, 'user_id' => $departure->companion->guardians()->first()->id, 'channel' => 'D1', 'ts' => 'ts1']);
        $second = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U2']);
        $departure->companion->guardians()->attach($second);
        CompanionSlackMessage::create(['daily_departure_id' => $departure->id, 'user_id' => $second->id, 'channel' => 'D2', 'ts' => 'ts2']);

        $departure->update(['companion_confirmed' => true]);

        app(SlackCompanion::class)->sync($departure);

        Http::assertSent(fn ($r) => $r->url() === 'https://slack.com/api/chat.update' && $r['ts'] === 'ts1');
        Http::assertSent(fn ($r) => $r->url() === 'https://slack.com/api/chat.update' && $r['ts'] === 'ts2');
        Http::assertSentCount(2);
    }

    public function test_cancel_replaces_each_dm_with_a_note(): void
    {
        Http::fake(['slack.com/api/chat.update' => Http::response(['ok' => true])]);

        app(SlackCompanion::class)->cancel(
            [['channel' => 'D1', 'ts' => 'ts1']],
            'Tom',
            'Emma',
        );

        Http::assertSent(fn ($r) => $r->url() === 'https://slack.com/api/chat.update'
            && $r['ts'] === 'ts1'
            && str_contains($r['text'], 'Tom')
            && str_contains($r['text'], 'Emma'));
    }

    public function test_nothing_is_sent_without_a_bot_token(): void
    {
        config(['services.slack.notifications.bot_user_oauth_token' => null]);
        Http::fake();

        $departure = $this->awaitingDeparture();
        app(SlackCompanion::class)->ask($departure);

        Http::assertNothingSent();
        $this->assertDatabaseCount('companion_slack_messages', 0);
    }

    public function test_ask_is_a_no_op_when_not_awaiting_confirmation(): void
    {
        Http::fake();

        $departure = $this->awaitingDeparture();
        $departure->update(['companion_confirmed' => true]); // already answered

        app(SlackCompanion::class)->ask($departure);

        Http::assertNothingSent();
    }
}
