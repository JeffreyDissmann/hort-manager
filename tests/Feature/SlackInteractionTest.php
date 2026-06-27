<?php

namespace Tests\Feature;

use App\Models\Child;
use App\Models\Excursion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class SlackInteractionTest extends TestCase
{
    use RefreshDatabase;

    private const SECRET = 'slack-signing-secret';

    /** POST a Slack interaction payload with a valid (or tampered) signature. */
    private function postInteraction(array $payload, bool $validSignature = true): TestResponse
    {
        config(['services.slack.signing_secret' => self::SECRET]);

        $json = json_encode($payload);
        $body = 'payload='.urlencode($json);
        $timestamp = (string) time();
        $secret = $validSignature ? self::SECRET : 'wrong-secret';
        $signature = 'v0='.hash_hmac('sha256', "v0:{$timestamp}:{$body}", $secret);

        // `payload` as a param so input() works; `$body` as content so the
        // signature is verified against the exact bytes Slack would send.
        return $this->call('POST', '/slack/interactions', ['payload' => $json], [], [], [
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
            'HTTP_X-Slack-Request-Timestamp' => $timestamp,
            'HTTP_X-Slack-Signature' => $signature,
        ], $body);
    }

    private function pendingExcursionFor(User $guardian): array
    {
        // Callers fake Http first, so the ExcursionObserver's announcement DM is a no-op here.
        $child = Child::factory()->create();
        $child->guardians()->attach($guardian);

        $excursion = Excursion::factory()->create(['rsvp_deadline' => Carbon::tomorrow()]);
        $excursion->children()->attach($child->id); // response null

        return [$excursion, $child];
    }

    public function test_a_signed_yes_click_records_the_rsvp(): void
    {
        Http::fake();
        $guardian = User::factory()->create(['slack_id' => 'U1']);
        [$excursion, $child] = $this->pendingExcursionFor($guardian);

        $this->postInteraction([
            'user' => ['id' => 'U1'],
            'response_url' => 'https://hooks.slack.test/confirm',
            'actions' => [['value' => "rsvp|{$excursion->id}|{$child->id}|1"]],
        ])->assertNoContent();

        $this->assertTrue((bool) $excursion->children()->find($child->id)->pivot->response);
        $this->assertSame($guardian->id, $excursion->children()->find($child->id)->pivot->answered_by);
    }

    public function test_a_signed_no_click_records_a_decline(): void
    {
        Http::fake();
        $guardian = User::factory()->create(['slack_id' => 'U1']);
        [$excursion, $child] = $this->pendingExcursionFor($guardian);

        $this->postInteraction([
            'user' => ['id' => 'U1'],
            'response_url' => 'https://hooks.slack.test/confirm',
            'actions' => [['value' => "rsvp|{$excursion->id}|{$child->id}|0"]],
        ])->assertNoContent();

        $this->assertFalse((bool) $excursion->children()->find($child->id)->pivot->response);
    }

    public function test_an_invalid_signature_is_rejected(): void
    {
        Http::fake();
        $guardian = User::factory()->create(['slack_id' => 'U1']);
        [$excursion, $child] = $this->pendingExcursionFor($guardian);

        $this->postInteraction([
            'user' => ['id' => 'U1'],
            'actions' => [['value' => "rsvp|{$excursion->id}|{$child->id}|1"]],
        ], validSignature: false)->assertForbidden();

        $this->assertNull($excursion->children()->find($child->id)->pivot->response);
    }

    public function test_a_click_from_a_non_guardian_is_ignored(): void
    {
        Http::fake();
        $guardian = User::factory()->create(['slack_id' => 'U1']);
        $stranger = User::factory()->create(['slack_id' => 'U9']);
        [$excursion, $child] = $this->pendingExcursionFor($guardian);

        $this->postInteraction([
            'user' => ['id' => 'U9'], // not a guardian of the child
            'response_url' => 'https://hooks.slack.test/confirm',
            'actions' => [['value' => "rsvp|{$excursion->id}|{$child->id}|1"]],
        ])->assertNoContent();

        $this->assertNull($excursion->children()->find($child->id)->pivot->response);
    }
}
