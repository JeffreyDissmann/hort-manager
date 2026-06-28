<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureMethod;
use App\Enums\UserRole;
use App\Models\Child;
use App\Models\Excursion;
use App\Models\User;
use App\Models\WeeklySchedule;
use App\Notifications\ExcursionRsvpReminder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExcursionManagementTest extends TestCase
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

    public function test_create_suggests_the_next_free_friday(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24')); // Wednesday

        $this->actingAs($this->staff())
            ->get(route('excursions.create'))
            ->assertInertia(fn (Assert $page) => $page->where('suggestedDate', '2026-06-26'));
    }

    public function test_create_skips_a_friday_that_already_has_an_excursion(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24')); // Wednesday
        Excursion::factory()->create(['date' => '2026-06-26']); // next Friday is taken

        $this->actingAs($this->staff())
            ->get(route('excursions.create'))
            ->assertInertia(fn (Assert $page) => $page->where('suggestedDate', '2026-07-03'));
    }

    public function test_creating_an_excursion_invites_every_child_as_an_open_poll(): void
    {
        Child::factory()->count(3)->create();

        $this->actingAs($this->staff())
            ->post(route('excursions.store'), [
                'name' => 'Zoo-Ausflug',
                'date' => '2026-06-29',
                'depart_at' => '09:00',
                'return_at' => '15:00',
                'rsvp_deadline' => '2026-06-27',
            ])
            ->assertRedirect(route('excursions.index'));

        $excursion = Excursion::firstWhere('name', 'Zoo-Ausflug');
        $this->assertNotNull($excursion);
        // All three children invited, all still pending (null response).
        $this->assertSame(3, $excursion->children()->count());
        $this->assertSame(0, $excursion->participants()->count());
        $this->assertDatabaseCount('child_excursion', 3);
    }

    public function test_creating_an_excursion_dms_slack_connected_guardians(): void
    {
        Http::fake(['slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'channel' => 'D1', 'ts' => '1.1'])]);

        $child = Child::factory()->create();
        $withSlack = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);
        $noSlack = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => null]);
        $child->guardians()->attach([$withSlack->id, $noSlack->id]);
        // A Slack user without any child must not be pestered.
        User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U2']);

        $this->actingAs($this->staff())
            ->post(route('excursions.store'), [
                'name' => 'Zoo-Ausflug',
                'date' => '2026-06-29',
                'rsvp_deadline' => '2026-06-27',
            ]);

        // Posted only to the Slack-connected guardian (channel = their Slack id), and remembered.
        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage' && $request['channel'] === 'U1');
        Http::assertSentCount(1);
        $this->assertDatabaseHas('excursion_slack_messages', ['user_id' => $withSlack->id, 'ts' => '1.1']);
    }

    public function test_an_answer_updates_every_guardians_slack_message(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::sequence()
                ->push(['ok' => true, 'channel' => 'D1', 'ts' => 'ts1'])
                ->push(['ok' => true, 'channel' => 'D2', 'ts' => 'ts2']),
            'slack.com/api/chat.update' => Http::response(['ok' => true]),
        ]);

        $child = Child::factory()->create();
        $mum = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);
        $dad = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U2']);
        $child->guardians()->attach([$mum->id, $dad->id]);

        // Creating the excursion DMs both guardians (two tracked messages).
        $this->actingAs($this->staff())->post(route('excursions.store'), [
            'name' => 'Zoo', 'date' => '2026-07-01', 'rsvp_deadline' => '2026-06-30',
        ]);
        $excursion = Excursion::firstWhere('name', 'Zoo');
        $this->assertDatabaseCount('excursion_slack_messages', 2);

        // Mum answers in the app → BOTH guardians' DMs are chat.update'd.
        $this->actingAs($mum)->patch(route('polls.update', $excursion), [
            'child_id' => $child->id,
            'response' => true,
        ]);

        Http::assertSent(fn ($r) => $r->url() === 'https://slack.com/api/chat.update' && $r['ts'] === 'ts1');
        Http::assertSent(fn ($r) => $r->url() === 'https://slack.com/api/chat.update' && $r['ts'] === 'ts2');
    }

    public function test_deleting_an_excursion_marks_the_slack_messages_cancelled(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true, 'channel' => 'D1', 'ts' => 'ts1']),
            'slack.com/api/chat.update' => Http::response(['ok' => true]),
        ]);

        $child = Child::factory()->create();
        $guardian = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);
        $child->guardians()->attach($guardian);

        $this->actingAs($this->staff())->post(route('excursions.store'), [
            'name' => 'Zoo', 'date' => '2026-07-01', 'rsvp_deadline' => '2026-06-30',
        ]);
        $excursion = Excursion::firstWhere('name', 'Zoo');

        $this->actingAs($this->staff())->delete(route('excursions.destroy', $excursion));

        Http::assertSent(fn ($r) => $r->url() === 'https://slack.com/api/chat.update'
            && $r['ts'] === 'ts1'
            && str_contains($r['text'], 'abgesagt'));
    }

    public function test_rsvp_reminder_dms_only_pending_slack_guardians(): void
    {
        Notification::fake();
        Carbon::setTestNow(Carbon::parse('2026-06-27'));

        $excursion = Excursion::factory()->create([
            'date' => '2026-06-29',
            'rsvp_deadline' => '2026-06-27', // due today
        ]);

        $pendingChild = Child::factory()->create();
        $pendingGuardian = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U1']);
        $pendingChild->guardians()->attach($pendingGuardian);
        $excursion->children()->attach($pendingChild->id); // response stays null

        $answeredChild = Child::factory()->create();
        $answeredGuardian = User::factory()->create(['role' => UserRole::Parent, 'slack_id' => 'U2']);
        $answeredChild->guardians()->attach($answeredGuardian);
        $excursion->children()->attach($answeredChild->id, ['response' => true]);

        $this->artisan('excursions:remind-rsvps')->assertSuccessful();

        Notification::assertSentTo($pendingGuardian, ExcursionRsvpReminder::class);
        Notification::assertNotSentTo($answeredGuardian, ExcursionRsvpReminder::class);
    }

    public function test_index_separates_upcoming_and_past_excursions(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-06-24'));
        $upcoming = Excursion::factory()->create(['date' => '2026-07-01']);
        $past = Excursion::factory()->create(['date' => '2026-06-01']);

        $this->actingAs($this->staff())
            ->get(route('excursions.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Excursions/Index')
                ->has('upcoming', 1)
                ->where('upcoming.0.id', $upcoming->id)
                ->has('past', 1)
                ->where('past.0.id', $past->id)
            );
    }

    public function test_planning_requires_name_date_and_deadline(): void
    {
        $this->actingAs($this->staff())
            ->post(route('excursions.store'), ['name' => '', 'date' => '', 'rsvp_deadline' => ''])
            ->assertSessionHasErrors(['name', 'date', 'rsvp_deadline']);
    }

    public function test_parents_cannot_manage_excursions(): void
    {
        $parent = $this->parent();

        $this->actingAs($parent)->get(route('excursions.index'))->assertForbidden();
        $this->actingAs($parent)->get(route('excursions.create'))->assertForbidden();
        $this->actingAs($parent)
            ->post(route('excursions.store'), ['name' => 'X', 'date' => '2026-06-29'])
            ->assertForbidden();
    }

    public function test_a_parent_can_answer_the_poll_for_their_child(): void
    {
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);
        $excursion = Excursion::factory()->create(['rsvp_deadline' => Carbon::tomorrow()]);
        $excursion->children()->attach($child->id);

        $this->actingAs($parent)
            ->patch(route('polls.update', $excursion), ['child_id' => $child->id, 'response' => true])
            ->assertRedirect();

        $pivot = $excursion->children()->where('children.id', $child->id)->first()->pivot;
        $this->assertEquals(1, $pivot->response);
        $this->assertSame($parent->id, $pivot->answered_by);
    }

    public function test_an_answer_by_one_parent_resolves_it_for_the_other(): void
    {
        $mum = $this->parent();
        $dad = $this->parent();
        $child = Child::factory()->create();
        $child->guardians()->attach([$mum->id, $dad->id]);
        $excursion = Excursion::factory()->create(['rsvp_deadline' => Carbon::tomorrow()]);
        $excursion->children()->attach($child->id);

        // Mum answers.
        $this->actingAs($mum)
            ->patch(route('polls.update', $excursion), ['child_id' => $child->id, 'response' => true]);

        // Dad now has nothing pending (the child's answer is shared).
        $this->actingAs($dad)
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page->where('pendingPolls', 0));
    }

    public function test_a_parent_cannot_answer_for_another_childs_poll(): void
    {
        $child = Child::factory()->create(); // not linked to this parent
        $excursion = Excursion::factory()->create(['rsvp_deadline' => Carbon::tomorrow()]);
        $excursion->children()->attach($child->id);

        $this->actingAs($this->parent())
            ->patch(route('polls.update', $excursion), ['child_id' => $child->id, 'response' => true])
            ->assertForbidden();
    }

    public function test_a_parent_cannot_answer_after_the_deadline_but_staff_can(): void
    {
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);
        $excursion = Excursion::factory()->create(['rsvp_deadline' => Carbon::yesterday()]);
        $excursion->children()->attach($child->id);

        $this->actingAs($parent)
            ->patch(route('polls.update', $excursion), ['child_id' => $child->id, 'response' => true])
            ->assertForbidden();

        // Staff can still fix it up after the deadline.
        $this->actingAs($this->staff())
            ->patch(route('polls.update', $excursion), ['child_id' => $child->id, 'response' => true])
            ->assertRedirect();
    }

    public function test_pending_poll_count_is_shared_with_parents(): void
    {
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);
        $excursion = Excursion::factory()->create(['rsvp_deadline' => Carbon::tomorrow()]);
        $excursion->children()->attach($child->id);

        $this->actingAs($parent)
            ->get(route('polls.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Excursions/Poll')
                ->where('pendingPolls', 1)
                ->has('upcoming', 1)
            );
    }

    public function test_poll_page_separates_upcoming_and_past_excursions(): void
    {
        $parent = $this->parent();
        $child = Child::factory()->create();
        $parent->children()->attach($child);

        $upcoming = Excursion::factory()->create(['date' => Carbon::tomorrow()->toDateString()]);
        $upcoming->children()->attach($child->id);

        $past = Excursion::factory()->create(['date' => Carbon::yesterday()->toDateString()]);
        $past->children()->attach($child->id, ['response' => true]);

        $this->actingAs($parent)
            ->get(route('polls.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('upcoming', 1)
                ->where('upcoming.0.id', $upcoming->id)
                ->has('past', 1)
                ->where('past.0.id', $past->id)
            );
    }

    public function test_deleting_an_excursion_removes_its_links(): void
    {
        $excursion = Excursion::factory()->create();
        $child = Child::factory()->create();
        $excursion->children()->attach($child->id);

        $this->actingAs($this->staff())
            ->delete(route('excursions.destroy', $excursion))
            ->assertRedirect(route('excursions.index'));

        $this->assertDatabaseMissing('excursions', ['id' => $excursion->id]);
        $this->assertDatabaseMissing('child_excursion', ['excursion_id' => $excursion->id]);
    }

    public function test_only_confirmed_participants_appear_on_the_board(): void
    {
        $this->travelTo(Carbon::parse('2026-06-22')); // Monday

        $joining = $this->scheduledChild('2026-06-22');
        $declining = $this->scheduledChild('2026-06-22');

        $excursion = Excursion::factory()->create([
            'name' => 'Waldtag',
            'date' => '2026-06-22',
            'return_at' => '15:30',
        ]);
        $excursion->children()->attach($joining->id, ['response' => true]);
        $excursion->children()->attach($declining->id, ['response' => false]);

        $this->actingAs($this->staff())
            ->get(route('board'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('excursions.0.child_count', 1)
                // Board rows are sorted by time then name; both kids share 16:00,
                // so assert the joining child carries the overlay and the other does not.
                ->where('rows', fn ($rows) => collect($rows)
                    ->firstWhere('child_id', $joining->id)['excursion']['name'] === 'Waldtag'
                    && collect($rows)->firstWhere('child_id', $declining->id)['excursion'] === null)
            );
    }

    public function test_staff_can_flip_the_live_trip_state(): void
    {
        $staff = $this->staff();
        $excursion = Excursion::factory()->create();
        $this->assertSame('planned', $excursion->state());

        $this->actingAs($staff)
            ->patch(route('excursions.live', $excursion), ['event' => 'depart'])
            ->assertRedirect();
        $this->assertSame('away', $excursion->refresh()->state());

        $this->actingAs($staff)
            ->patch(route('excursions.live', $excursion), ['event' => 'return']);
        $this->assertSame('back', $excursion->refresh()->state());

        $this->actingAs($staff)
            ->patch(route('excursions.live', $excursion), ['event' => 'undo_depart']);
        $excursion->refresh();
        $this->assertSame('planned', $excursion->state());
        $this->assertNull($excursion->returned_at);
    }

    public function test_parents_cannot_flip_the_live_state(): void
    {
        $excursion = Excursion::factory()->create();

        $this->actingAs($this->parent())
            ->patch(route('excursions.live', $excursion), ['event' => 'depart'])
            ->assertForbidden();
    }

    private function scheduledChild(string $date): Child
    {
        $child = Child::factory()->create();
        WeeklySchedule::create([
            'child_id' => $child->id,
            'weekday' => Carbon::parse($date)->dayOfWeekIso,
            'planned_time' => '16:00',
            'method' => DepartureMethod::PickedUp,
        ]);

        return $child;
    }
}
