<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Child;
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
        foreach (['departures', 'excursions', 'companion', 'missing_plan', 'care_registration', 'weekly_digest'] as $category) {
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

    public function test_staff_see_only_staff_categories(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);

        $this->actingAs($staff)
            ->get(route('notifications.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories', ['late_change', 'program_missing'])
                ->where('sections', [['audience' => 'staff', 'categories' => ['late_change', 'program_missing']]])
                ->has('preferences.late_change')
                ->missing('preferences.departures')
                // The timings the staff toggles link to.
                ->where('lateChangeCutoff', '12:00')
                ->where('programReminderTime', '11:30')
            );
    }

    public function test_parents_see_only_guardian_categories(): void
    {
        $parent = User::factory()->create(['role' => UserRole::Parent]);

        $this->actingAs($parent)
            ->get(route('notifications.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('preferences.departures')
                ->missing('preferences.late_change')
                ->count('sections', 1)
                ->where('sections.0.audience', 'guardian')
            );
    }

    public function test_staff_who_are_a_guardian_see_both_sections(): void
    {
        $staff = User::factory()->create(['role' => UserRole::Staff]);
        $staff->children()->attach(Child::factory()->create());

        $this->actingAs($staff)
            ->get(route('notifications.edit'))
            ->assertInertia(fn (Assert $page) => $page
                ->count('sections', 2)
                ->where('sections.0.audience', 'guardian')
                ->where('sections.1.audience', 'staff')
                ->has('preferences.departures')
                ->has('preferences.late_change')
            );
    }

    public function test_update_rejects_a_category_the_user_is_no_audience_for(): void
    {
        $parent = User::factory()->create(['role' => UserRole::Parent]);

        $this->actingAs($parent)
            ->patch(route('notifications.update'), [
                'preferences' => ['late_change' => ['slack' => false, 'push' => false]],
            ])
            ->assertSessionHasErrors('preferences.late_change');
    }

    public function test_update_keeps_preferences_of_the_other_audience(): void
    {
        // A staff-parent turns their guardian toggles off, then loses guardian status:
        // saving the staff-only page must not wipe what they set as a guardian.
        $staff = User::factory()->create([
            'role' => UserRole::Staff,
            'notification_preferences' => ['departures' => ['slack' => false, 'push' => false]],
        ]);

        $this->actingAs($staff)
            ->patch(route('notifications.update'), [
                'preferences' => [
                    'late_change' => ['slack' => true, 'push' => false],
                    'program_missing' => ['slack' => true, 'push' => true],
                ],
            ])
            ->assertRedirect();

        $staff->refresh();
        $this->assertFalse($staff->wantsNotification('departures', 'slack'));
        $this->assertFalse($staff->wantsNotification('late_change', 'push'));
        $this->assertTrue($staff->wantsNotification('late_change', 'slack'));
    }

    public function test_update_rejects_a_non_boolean_value(): void
    {
        $user = User::factory()->create(['role' => UserRole::Parent]);

        $preferences = [];
        foreach (['departures', 'excursions', 'companion', 'missing_plan', 'care_registration', 'weekly_digest'] as $category) {
            $preferences[$category] = ['slack' => true, 'push' => true];
        }
        $preferences['departures']['slack'] = 'yes please';

        $this->actingAs($user)
            ->patch(route('notifications.update'), ['preferences' => $preferences])
            ->assertSessionHasErrors('preferences.departures.slack');
    }
}
