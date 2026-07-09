<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Child;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => UserRole::Staff, 'is_admin' => true]);
    }

    public function test_non_admins_cannot_view_user_management(): void
    {
        $this->actingAs(User::factory()->create(['role' => UserRole::Parent]))
            ->get(route('users.index'))
            ->assertForbidden();

        // Even a (non-admin) staff member has no access.
        $this->actingAs(User::factory()->create(['role' => UserRole::Staff, 'is_admin' => false]))
            ->get(route('users.index'))
            ->assertForbidden();
    }

    public function test_admin_can_view_user_management(): void
    {
        $this->actingAs($this->admin())
            ->get(route('users.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Users/Index')->has('users'));
    }

    public function test_the_user_list_shows_each_users_children(): void
    {
        $parent = User::factory()->create(['name' => 'Parent', 'role' => UserRole::Parent]);
        $parent->children()->attach(Child::factory()->create(['name' => 'Emma']));
        $parent->children()->attach(Child::factory()->create(['name' => 'Ben']));

        $this->actingAs($this->admin())
            ->get(route('users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('users', fn ($users) => collect($users)
                    ->firstWhere('name', 'Parent')['children'] === ['Ben', 'Emma']) // sorted
            );
    }

    public function test_admin_can_change_a_users_role(): void
    {
        $parent = User::factory()->create(['role' => UserRole::Parent]);

        $this->actingAs($this->admin())
            ->patch(route('users.update', $parent), ['role' => 'staff', 'is_admin' => false]);

        $this->assertSame(UserRole::Staff, $parent->refresh()->role);
        $this->assertFalse($parent->is_admin);
    }

    public function test_admin_can_grant_admin_independent_of_role(): void
    {
        $parent = User::factory()->create(['role' => UserRole::Parent]);

        $this->actingAs($this->admin())
            ->patch(route('users.update', $parent), ['role' => 'parent', 'is_admin' => true]);

        $parent->refresh();
        $this->assertTrue($parent->is_admin);
        // A parent can be an admin — the role is untouched.
        $this->assertSame(UserRole::Parent, $parent->role);
    }

    public function test_the_last_admin_cannot_be_demoted(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->patch(route('users.update', $admin), ['role' => 'staff', 'is_admin' => false]);

        $this->assertTrue($admin->refresh()->is_admin);
    }

    public function test_an_admin_can_step_down_when_another_admin_remains(): void
    {
        $admin = $this->admin();
        $other = $this->admin();

        $this->actingAs($other)
            ->patch(route('users.update', $admin), ['role' => 'staff', 'is_admin' => false]);

        $this->assertFalse($admin->refresh()->is_admin);
    }

    public function test_admin_can_delete_a_user_and_their_guardian_links(): void
    {
        $admin = $this->admin();
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $child = Child::factory()->create();
        $child->guardians()->attach($parent);

        $this->actingAs($admin)->delete(route('users.destroy', $parent));

        $this->assertModelMissing($parent);
        $this->assertDatabaseMissing('child_user', ['user_id' => $parent->id]);
    }

    public function test_an_admin_cannot_delete_themselves(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->delete(route('users.destroy', $admin));

        $this->assertModelExists($admin);
    }

    public function test_non_admins_cannot_delete_users(): void
    {
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $victim = User::factory()->create(['role' => UserRole::Parent]);

        $this->actingAs($parent)->delete(route('users.destroy', $victim))->assertForbidden();
        $this->assertModelExists($victim);
    }

    public function test_make_admin_command_grants_admin_keeping_role(): void
    {
        $parent = User::factory()->create(['role' => UserRole::Parent, 'email' => 'jeff@hort.test']);

        $this->artisan('hort:make-admin', ['email' => 'jeff@hort.test'])
            ->assertSuccessful();

        $parent->refresh();
        $this->assertTrue($parent->is_admin);
        $this->assertSame(UserRole::Parent, $parent->role);
    }
}
