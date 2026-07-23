<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\TimeQualifier;
use App\Enums\UserRole;
use App\Models\Accounting\Booking;
use App\Models\Child;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ChildManagementTest extends TestCase
{
    use RefreshDatabase;

    private function staff(): User
    {
        return User::factory()->create(['role' => UserRole::Staff]);
    }

    private function parent(): User
    {
        return User::factory()->create(['role' => UserRole::Parent]);
    }

    public function test_guests_cannot_manage_children(): void
    {
        $this->get(route('children.index'))->assertRedirect(route('login'));
    }

    public function test_staff_index_lists_all_children(): void
    {
        Child::factory()->create(['name' => 'Emma']);
        Child::factory()->create(['name' => 'Mia']);

        $this->actingAs($this->staff())
            ->get(route('children.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Children/Index')
                ->where('canManage', true)
                ->has('children', 2)
            );
    }

    public function test_parent_index_shows_only_their_own_children(): void
    {
        $parent = $this->parent();
        $own = Child::factory()->create(['name' => 'Emma']);
        Child::factory()->create(['name' => 'Mia']); // someone else's
        $parent->children()->attach($own);

        $this->actingAs($parent)
            ->get(route('children.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('canManage', false)
                ->has('children', 1)
                ->where('children.0.name', 'Emma')
            );
    }

    public function test_the_child_list_shows_each_childs_guardians(): void
    {
        $child = Child::factory()->create(['name' => 'Emma']);
        $child->guardians()->attach(User::factory()->create(['name' => 'Mum', 'role' => UserRole::Parent]));
        $child->guardians()->attach(User::factory()->create(['name' => 'Dad', 'role' => UserRole::Parent]));

        $this->actingAs($this->staff())
            ->get(route('children.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('children.0.name', 'Emma')
                ->where('children.0.guardians', ['Dad', 'Mum']) // sorted
            );
    }

    public function test_staff_can_create_a_child(): void
    {
        $response = $this->actingAs($this->staff())
            ->post(route('children.store'), [
                'name' => 'Liam',
                'date_of_birth' => '2019-04-01',
                'note' => 'Geht freitags allein',
                'active_from' => '2026-08-01',
            ]);

        $child = Child::firstWhere('name', 'Liam');

        $this->assertNotNull($child);
        $response->assertRedirect(route('children.edit', $child));
        $this->assertSame('Geht freitags allein', $child->note);
        $this->assertSame('2026-08-01', $child->active_from->toDateString());
        $this->assertNull($child->active_until);
    }

    public function test_creating_a_child_requires_an_active_from_date(): void
    {
        $this->actingAs($this->staff())
            ->post(route('children.store'), ['name' => 'Liam'])
            ->assertSessionHasErrors('active_from');
    }

    public function test_creating_a_child_requires_a_name(): void
    {
        $this->actingAs($this->staff())
            ->post(route('children.store'), ['name' => ''])
            ->assertSessionHasErrors('name');

        $this->assertDatabaseCount('children', 0);
    }

    public function test_a_parent_can_create_a_child_and_becomes_its_guardian(): void
    {
        $parent = $this->parent();

        $this->actingAs($parent)
            ->post(route('children.store'), ['name' => 'Neu', 'active_from' => '2026-08-01'])
            ->assertRedirect();

        $child = Child::firstWhere('name', 'Neu');
        $this->assertNotNull($child);
        $this->assertTrue($child->guardians()->whereKey($parent->id)->exists());
    }

    public function test_a_guardian_can_delete_their_own_child(): void
    {
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);

        $this->actingAs($parent)
            ->delete(route('children.destroy', $child))
            ->assertRedirect(route('children.index'));

        $this->assertDatabaseMissing('children', ['id' => $child->id]);
    }

    public function test_a_parent_cannot_delete_a_child_they_do_not_guard(): void
    {
        $parent = $this->parent();
        $other = Child::factory()->create(); // not linked

        $this->actingAs($parent)
            ->delete(route('children.destroy', $other))
            ->assertForbidden();

        $this->assertDatabaseHas('children', ['id' => $other->id]);
    }

    public function test_edit_passes_the_birthday_as_a_plain_date(): void
    {
        $child = Child::factory()->create(['date_of_birth' => '2019-11-03']);

        $this->actingAs($this->staff())
            ->get(route('children.edit', $child))
            ->assertInertia(fn (Assert $page) => $page
                ->where('child.date_of_birth', '2019-11-03')
            );
    }

    public function test_staff_updating_a_child_upserts_the_weekly_schedule(): void
    {
        $child = Child::factory()->create();

        $this->actingAs($this->staff())
            ->patch(route('children.update', $child), [
                'name' => 'Mia',
                'schedule' => [
                    ['weekday' => 1, 'planned_time' => '16:00', 'method' => DepartureMethod::PickedUp->value],
                    ['weekday' => 2, 'planned_time' => '', 'method' => null],
                    ['weekday' => 5, 'planned_time' => '14:30', 'method' => DepartureMethod::SentHome->value],
                ],
            ])
            ->assertRedirect(route('children.index'));

        $this->assertSame('Mia', $child->refresh()->name);
        $this->assertDatabaseHas('weekly_schedules', [
            'child_id' => $child->id,
            'weekday' => 1,
            'planned_time' => '16:00',
            'method' => DepartureMethod::PickedUp->value,
        ]);
        $this->assertDatabaseMissing('weekly_schedules', ['child_id' => $child->id, 'weekday' => 2]);
        $this->assertSame(2, $child->weeklySchedules()->count());
    }

    public function test_a_sent_home_stammplan_day_keeps_its_time_qualifier(): void
    {
        $child = Child::factory()->create();

        $this->actingAs($this->staff())
            ->patch(route('children.update', $child), [
                'name' => $child->name,
                'schedule' => [
                    ['weekday' => 1, 'planned_time' => '15:00', 'method' => DepartureMethod::SentHome->value, 'time_qualifier' => TimeQualifier::From->value],
                    // A picked-up day must not keep a qualifier.
                    ['weekday' => 2, 'planned_time' => '16:00', 'method' => DepartureMethod::PickedUp->value, 'time_qualifier' => TimeQualifier::By->value],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('weekly_schedules', [
            'child_id' => $child->id,
            'weekday' => 1,
            'time_qualifier' => TimeQualifier::From->value,
        ]);
        $this->assertDatabaseHas('weekly_schedules', [
            'child_id' => $child->id,
            'weekday' => 2,
            'time_qualifier' => null,
        ]);
    }

    public function test_the_stammplan_rejects_the_companion_method(): void
    {
        $child = Child::factory()->create();

        // „Geht mit einem anderen Kind mit" is a per-day Wochenplan choice only.
        $this->actingAs($this->staff())
            ->patch(route('children.update', $child), [
                'name' => $child->name,
                'schedule' => [
                    ['weekday' => 1, 'planned_time' => '15:00', 'method' => DepartureMethod::WithChild->value],
                ],
            ])
            ->assertSessionHasErrors('schedule.0.method');

        $this->assertDatabaseMissing('weekly_schedules', ['child_id' => $child->id, 'weekday' => 1]);
    }

    public function test_an_invalid_schedule_does_not_persist_the_name_change(): void
    {
        $child = Child::factory()->create(['name' => 'Original']);

        $this->actingAs($this->staff())
            ->patch(route('children.update', $child), [
                'name' => 'Renamed',
                'schedule' => [
                    ['weekday' => 9, 'planned_time' => '16:00'], // weekday out of 1..5 → validation fails
                ],
            ])
            ->assertSessionHasErrors('schedule.0.weekday');

        // Nothing applied: the name is unchanged.
        $this->assertSame('Original', $child->refresh()->name);
    }

    public function test_a_weekday_can_carry_a_comment(): void
    {
        $child = Child::factory()->create();

        $this->actingAs($this->staff())
            ->patch(route('children.update', $child), [
                'name' => $child->name,
                'schedule' => [
                    [
                        'weekday' => 1,
                        'planned_time' => '14:00',
                        'method' => DepartureMethod::PickedUp->value,
                        'comment' => 'wegen Fußball',
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('weekly_schedules', [
            'child_id' => $child->id,
            'weekday' => 1,
            'comment' => 'wegen Fußball',
        ]);
    }

    public function test_a_parent_can_edit_their_own_childs_stammplan(): void
    {
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);

        $this->actingAs($parent)
            ->patch(route('children.update', $child), [
                'name' => $child->name,
                'schedule' => [
                    ['weekday' => 3, 'planned_time' => '15:00', 'method' => DepartureMethod::PickedUp->value],
                ],
            ])
            ->assertRedirect(route('children.index'));

        $this->assertDatabaseHas('weekly_schedules', [
            'child_id' => $child->id,
            'weekday' => 3,
            'planned_time' => '15:00',
        ]);
    }

    public function test_a_parent_cannot_edit_another_childs_stammplan(): void
    {
        $parent = $this->parent();
        $other = Child::factory()->create(); // not linked

        $this->actingAs($parent)
            ->get(route('children.edit', $other))
            ->assertForbidden();

        $this->actingAs($parent)
            ->patch(route('children.update', $other), ['name' => 'Hack'])
            ->assertForbidden();
    }

    public function test_staff_can_assign_guardians_to_a_child(): void
    {
        $child = Child::factory()->create();
        $mum = $this->parent();
        $dad = $this->parent();

        $this->actingAs($this->staff())
            ->patch(route('children.update', $child), [
                'name' => $child->name,
                'guardians' => [$mum->id, $dad->id],
            ])
            ->assertRedirect();

        $this->assertEqualsCanonicalizing(
            [$mum->id, $dad->id],
            $child->guardians()->pluck('users.id')->all(),
        );
    }

    public function test_a_guardian_can_add_a_co_guardian_but_not_drop_themselves(): void
    {
        $parent = $this->parent();
        $coParent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);

        // Adds the co-parent, and omits themselves on purpose.
        $this->actingAs($parent)
            ->patch(route('children.update', $child), [
                'name' => $child->name,
                'guardians' => [$coParent->id],
            ])
            ->assertRedirect();

        // Co-parent is added, and the acting parent is kept (can't lock themselves out).
        $this->assertEqualsCanonicalizing(
            [$parent->id, $coParent->id],
            $child->guardians()->pluck('users.id')->all(),
        );
    }

    public function test_a_child_can_be_deleted_by_staff(): void
    {
        $child = Child::factory()->create();
        $child->weeklySchedules()->create([
            'weekday' => 1,
            'planned_time' => '16:00',
            'method' => DepartureMethod::PickedUp,
        ]);

        $this->actingAs($this->staff())
            ->delete(route('children.destroy', $child))
            ->assertRedirect(route('children.index'));

        $this->assertDatabaseMissing('children', ['id' => $child->id]);
        $this->assertDatabaseMissing('weekly_schedules', ['child_id' => $child->id]);
    }

    public function test_a_child_with_bookings_cannot_be_deleted(): void
    {
        $child = Child::factory()->create();
        Booking::factory()->create(['counterparty_child_id' => $child->id]);

        $this->actingAs($this->staff())
            ->from(route('children.index'))
            ->delete(route('children.destroy', $child))
            ->assertRedirect(route('children.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('children', ['id' => $child->id]);
    }

    public function test_the_database_blocks_deleting_a_child_with_bookings(): void
    {
        // Belt-and-suspenders: even bypassing the controller guard, the
        // restrictOnDelete foreign key keeps a referenced child from being deleted.
        $child = Child::factory()->create();
        Booking::factory()->create(['counterparty_child_id' => $child->id]);

        $this->expectException(QueryException::class);
        $child->delete();
    }
}
