<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackUserImportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Staff, 'is_admin' => true]);
    }

    private function fakeMembers(): void
    {
        Http::fake(['slack.com/api/users.list*' => Http::response([
            'ok' => true,
            'members' => [
                ['id' => 'U1', 'name' => 'mama', 'profile' => ['real_name' => 'Mama Muster', 'email' => 'mama@hort.test', 'image_192' => 'https://img/192.png']],
                ['id' => 'U2', 'is_bot' => true, 'profile' => ['email' => 'bot@hort.test']],
                ['id' => 'USLACKBOT', 'profile' => ['email' => 'slackbot@hort.test']],
                ['id' => 'U3', 'deleted' => true, 'profile' => ['email' => 'gone@hort.test']],
                ['id' => 'U4', 'profile' => []], // no email
            ],
            'response_metadata' => ['next_cursor' => ''],
        ])]);
    }

    public function test_admin_can_import_slack_members_as_parents(): void
    {
        $this->fakeMembers();

        $this->actingAs($this->admin())
            ->post(route('users.sync'))
            ->assertRedirect();

        $this->assertDatabaseHas('users', [
            'slack_id' => 'U1',
            'email' => 'mama@hort.test',
            'name' => 'Mama Muster',
            'role' => 'parent',
        ]);
        // Bots, Slackbot, deactivated and email-less members are skipped.
        $this->assertDatabaseMissing('users', ['email' => 'bot@hort.test']);
        $this->assertDatabaseMissing('users', ['slack_id' => 'USLACKBOT']);
        $this->assertDatabaseMissing('users', ['email' => 'gone@hort.test']);
    }

    public function test_import_refreshes_but_keeps_role_and_admin(): void
    {
        $existing = User::factory()->create([
            'role' => UserRole::Staff,
            'is_admin' => true,
            'slack_id' => 'U1',
            'name' => 'Alter Name',
        ]);
        $this->fakeMembers();

        $this->artisan('hort:sync-slack-users')->assertSuccessful();

        $existing->refresh();
        $this->assertSame('Mama Muster', $existing->name); // refreshed
        $this->assertSame(UserRole::Staff, $existing->role); // preserved
        $this->assertTrue($existing->is_admin); // preserved
        $this->assertSame(1, User::count()); // matched by slack_id, no duplicate
    }

    public function test_non_admins_cannot_import(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Parent]))
            ->post(route('users.sync'))
            ->assertForbidden();
    }
}
