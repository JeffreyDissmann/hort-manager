<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/** The notification settings page: the type × channel opt-out matrix. */
class NotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_renders_the_matrix_defaulting_to_all_on(): void
    {
        $user = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);

        $this->actingAs($user)
            ->get(route('notifications.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Notifications/Edit')
                ->where('slackConnected', true)
                ->where('preferences.departures.slack', true)
                ->where('preferences.weekly_digest.push', true)
            );
    }

    public function test_edit_reports_slack_not_connected_without_a_slack_id(): void
    {
        $user = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => null]);

        $this->actingAs($user)
            ->get(route('notifications.edit'))
            ->assertInertia(fn (Assert $page) => $page->where('slackConnected', false));
    }

    public function test_update_persists_the_matrix(): void
    {
        $user = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);

        $preferences = [];
        foreach (['departures', 'excursions', 'companion', 'missing_plan', 'weekly_digest'] as $category) {
            $preferences[$category] = ['slack' => true, 'push' => true];
        }
        $preferences['departures']['slack'] = false;

        $this->actingAs($user)
            ->patch(route('notifications.update'), ['preferences' => $preferences])
            ->assertRedirect();

        $user->refresh();
        $this->assertFalse($user->wantsNotification('departures', 'slack'));
        $this->assertTrue($user->wantsNotification('departures', 'push'));
    }

    public function test_update_rejects_an_unknown_category(): void
    {
        $user = User::factory()->create(['role' => UserRole::Parent]);

        $preferences = ['made_up' => ['slack' => true, 'push' => true]];

        $this->actingAs($user)
            ->patch(route('notifications.update'), ['preferences' => $preferences])
            ->assertSessionHasErrors();
    }

    public function test_update_rejects_a_non_boolean_value(): void
    {
        $user = User::factory()->create(['role' => UserRole::Parent]);

        $preferences = [];
        foreach (['departures', 'excursions', 'companion', 'missing_plan', 'weekly_digest'] as $category) {
            $preferences[$category] = ['slack' => true, 'push' => true];
        }
        $preferences['departures']['slack'] = 'yes please';

        $this->actingAs($user)
            ->patch(route('notifications.update'), ['preferences' => $preferences])
            ->assertSessionHasErrors('preferences.departures.slack');
    }
}
