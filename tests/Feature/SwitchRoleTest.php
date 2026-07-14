<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** The admin self-service role switch (staff ↔ parent) from the menu. */
class SwitchRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_admin_can_switch_their_own_role(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Staff, 'is_admin' => true]);

        // Stays on the page the switch was made from.
        $this->actingAs($admin)
            ->from(route('board'))
            ->post(route('role.update'), ['role' => 'parent'])
            ->assertRedirect(route('board'));

        $admin->refresh();
        $this->assertSame(UserRole::Parent, $admin->role);
        $this->assertTrue($admin->is_admin); // admin status is untouched
    }

    public function test_a_non_admin_cannot_switch_roles(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff, 'is_admin' => false]);

        $this->actingAs($staff)
            ->post(route('role.update'), ['role' => 'parent'])
            ->assertForbidden();

        $this->assertSame(UserRole::Staff, $staff->refresh()->role);
    }

    public function test_an_invalid_role_is_rejected(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Staff, 'is_admin' => true]);

        $this->actingAs($admin)
            ->post(route('role.update'), ['role' => 'wizard'])
            ->assertSessionHasErrors('role');
    }
}
