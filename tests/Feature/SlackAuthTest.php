<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Contracts\User as SocialiteUser;
use Laravel\Socialite\Facades\Socialite;
use Mockery;
use Tests\TestCase;

class SlackAuthTest extends TestCase
{
    use RefreshDatabase;

    /** Make Socialite return a fake Slack identity for the callback. */
    private function fakeSlackUser(string $id, ?string $email, string $name = 'Papa Schmidt', ?string $teamId = null): void
    {
        $slackUser = Mockery::mock(SocialiteUser::class);
        $slackUser->shouldReceive('getId')->andReturn($id);
        $slackUser->shouldReceive('getName')->andReturn($name);
        $slackUser->shouldReceive('getEmail')->andReturn($email);
        $slackUser->shouldReceive('getAvatar')->andReturn('https://slack.test/avatar.png');
        $slackUser->shouldReceive('getRaw')->andReturn(['https://slack.com/team_id' => $teamId]);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('setUserScopes')->andReturnSelf();
        $provider->shouldReceive('user')->andReturn($slackUser);

        Socialite::shouldReceive('driver')->with('slack')->andReturn($provider);
    }

    public function test_redirect_sends_the_user_to_slack(): void
    {
        config([
            'services.slack.client_id' => 'test-id',
            'services.slack.client_secret' => 'test-secret',
            'services.slack.redirect' => 'http://localhost/auth/slack/callback',
            'services.slack.team' => 'T0HORT',
        ]);

        $response = $this->get(route('slack.redirect'));
        $location = $response->headers->get('Location');

        $response->assertRedirect();
        // OpenID Connect "Sign in with Slack" endpoint + scopes.
        $this->assertStringContainsString('slack.com/openid/connect/authorize', $location);
        $this->assertStringContainsString('openid', $location);
        // The configured workspace is pre-selected.
        $this->assertStringContainsString('team=T0HORT', $location);
    }

    public function test_redirect_routes_through_the_configured_workspace_subdomain(): void
    {
        config([
            'services.slack.client_id' => 'test-id',
            'services.slack.client_secret' => 'test-secret',
            'services.slack.redirect' => 'http://localhost/auth/slack/callback',
            'services.slack.workspace' => 'myhort',
        ]);

        $location = $this->get(route('slack.redirect'))->headers->get('Location');

        // Goes straight to the workspace subdomain, skipping Slack's picker.
        $this->assertStringContainsString('https://myhort.slack.com/openid/connect/', $location);
    }

    public function test_a_new_slack_user_is_provisioned_as_a_parent_and_logged_in(): void
    {
        $this->fakeSlackUser('U123', 'papa@example.test');

        $this->get(route('slack.callback'))->assertRedirect(route('dashboard'));

        $user = User::firstWhere('slack_id', 'U123');
        $this->assertNotNull($user);
        $this->assertSame('papa@example.test', $user->email);
        $this->assertSame(UserRole::Parent, $user->role);
        $this->assertNotNull($user->email_verified_at);
        $this->assertAuthenticatedAs($user);
    }

    public function test_an_existing_account_is_linked_by_email(): void
    {
        $existing = User::factory()->create([
            'email' => 'mama@example.test',
            'role' => UserRole::Parent,
            'slack_id' => null,
        ]);
        $this->fakeSlackUser('U999', 'mama@example.test');

        $this->get(route('slack.callback'))->assertRedirect(route('dashboard'));

        $this->assertSame('U999', $existing->refresh()->slack_id);
        $this->assertSame(1, User::count()); // no duplicate
        $this->assertAuthenticatedAs($existing);
    }

    public function test_a_known_slack_id_logs_the_same_user_in(): void
    {
        $existing = User::factory()->create(['slack_id' => 'U555', 'email' => 'a@b.test']);
        $this->fakeSlackUser('U555', 'a@b.test');

        $this->get(route('slack.callback'))->assertRedirect(route('dashboard'));

        $this->assertSame(1, User::count());
        $this->assertAuthenticatedAs($existing);
    }

    public function test_slack_login_without_an_email_fails_gracefully(): void
    {
        $this->fakeSlackUser('Unoemail', null);

        $this->get(route('slack.callback'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_sign_in_is_rejected_for_a_different_workspace(): void
    {
        config(['services.slack.team' => 'T0HORT']);
        $this->fakeSlackUser('Uoutsider', 'x@y.test', teamId: 'T0OTHER');

        $this->get(route('slack.callback'))->assertRedirect(route('login'));

        $this->assertGuest();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_a_member_of_the_configured_workspace_may_sign_in(): void
    {
        config(['services.slack.team' => 'T0HORT']);
        $this->fakeSlackUser('Uinsider', 'in@hort.test', teamId: 'T0HORT');

        $this->get(route('slack.callback'))->assertRedirect(route('dashboard'));

        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['slack_id' => 'Uinsider']);
    }
}
