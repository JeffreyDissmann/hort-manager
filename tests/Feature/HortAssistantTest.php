<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Ai\Agents\HortAnswerAgent;
use App\Ai\Agents\HortIntentAgent;
use App\Enums\DepartureMethod;
use App\Enums\DepartureStatus;
use App\Enums\UserRole;
use App\Jobs\SyncExcursionRsvp;
use App\Models\Child;
use App\Models\DailyDeparture;
use App\Models\Excursion;
use App\Models\User;
use App\Notifications\CompanionRequest;
use App\Services\HortAssistant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class HortAssistantTest extends TestCase
{
    use RefreshDatabase;

    /** @return array{0: User, 1: Child} */
    private function parentWithTom(): array
    {
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $child = Child::factory()->create(['name' => 'Tom']);
        $parent->children()->attach($child);

        return [$parent, $child];
    }

    /** @param array<string, mixed> $data */
    private function fakeIntent(array $data): void
    {
        HortIntentAgent::fake(fn () => array_merge([
            'intent' => 'unbekannt', 'kind' => null, 'datum' => null,
            'uhrzeit' => null, 'art' => null, 'ausflug' => null, 'zusage' => null,
        ], $data));
    }

    public function test_it_reports_a_child_krank(): void
    {
        Carbon::setTestNow('2026-06-22');
        [$parent, $child] = $this->parentWithTom();
        $this->fakeIntent(['intent' => 'krank', 'kind' => 'Tom', 'datum' => '2026-06-22']);

        $reply = app(HortAssistant::class)->reply($parent, 'Tom ist krank');

        $this->assertStringContainsString('Tom', $reply);
        $this->assertDatabaseHas('absences', [
            'child_id' => $child->id,
            'date' => '2026-06-22',
            'reason' => 'sick',
        ]);
    }

    public function test_it_changes_a_pickup_time(): void
    {
        Carbon::setTestNow('2026-06-22');
        [$parent, $child] = $this->parentWithTom();
        $this->fakeIntent([
            'intent' => 'abholzeit', 'kind' => 'Tom',
            'datum' => '2026-06-24', 'uhrzeit' => '16:30', 'art' => 'abgeholt',
        ]);

        app(HortAssistant::class)->reply($parent, 'Tom wird Mittwoch um 16:30 abgeholt');

        $departure = DailyDeparture::where('child_id', $child->id)->where('date', '2026-06-24')->first();
        $this->assertNotNull($departure);
        $this->assertStringStartsWith('16:30', (string) $departure->planned_time);
    }

    public function test_a_method_change_without_a_time_keeps_the_planned_time(): void
    {
        Carbon::setTestNow('2026-06-22');
        [$parent, $child] = $this->parentWithTom();
        DailyDeparture::create([
            'child_id' => $child->id, 'date' => '2026-06-22',
            'status' => DepartureStatus::Present, 'planned_time' => '15:00',
        ]);
        $this->fakeIntent(['intent' => 'abholzeit', 'kind' => 'Tom', 'datum' => 'heute', 'art' => 'abgeholt']);

        $reply = app(HortAssistant::class)->reply($parent, 'Tom wird heute von Papa abgeholt');

        $departure = DailyDeparture::where('child_id', $child->id)->where('date', '2026-06-22')->first();
        $this->assertStringStartsWith('15:00', (string) $departure->planned_time); // not wiped
        $this->assertSame(DepartureMethod::PickedUp, $departure->planned_method);
        $this->assertStringContainsString('Wird abgeholt', $reply);
        $this->assertStringNotContainsString('keine Abholzeit', $reply);
    }

    public function test_changing_a_companion_to_alone_reopens_a_dependent(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-06-22'); // Monday
        [$parent, $tom] = $this->parentWithTom();
        $anna = Child::factory()->create(['name' => 'Anna']);

        // Tom is picked up on Wednesday, so Anna's „geht mit Tom mit" is auto-approved.
        DailyDeparture::create([
            'child_id' => $tom->id, 'date' => '2026-06-24',
            'status' => DepartureStatus::Present, 'planned_time' => '15:00',
            'planned_method' => DepartureMethod::PickedUp,
        ]);
        DailyDeparture::create([
            'child_id' => $anna->id, 'date' => '2026-06-24',
            'status' => DepartureStatus::Present,
            'planned_method' => DepartureMethod::WithChild, 'companion_child_id' => $tom->id,
            'companion_confirmed' => true, // auto-approved (system), no confirmer
        ]);

        // Tom's parent tells the assistant Tom goes home alone that day.
        $this->fakeIntent(['intent' => 'abholzeit', 'kind' => 'Tom', 'datum' => '2026-06-24', 'art' => 'allein']);
        app(HortAssistant::class)->reply($parent, 'Tom geht Mittwoch allein');

        // Anna's auto-approval is reopened, and Tom's family is asked.
        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $anna->id,
            'companion_confirmed' => null,
        ]);
        Notification::assertSentTo($parent, CompanionRequest::class);
    }

    public function test_a_pickup_change_without_details_asks_for_them(): void
    {
        Carbon::setTestNow('2026-06-22');
        [$parent, $child] = $this->parentWithTom();
        $this->fakeIntent(['intent' => 'abholzeit', 'kind' => 'Tom', 'datum' => 'heute']);

        $reply = app(HortAssistant::class)->reply($parent, 'Ändere Toms Abholung');

        $this->assertStringContainsString('Uhrzeit', $reply);
        $this->assertDatabaseMissing('daily_departures', ['child_id' => $child->id]);
    }

    public function test_a_weekday_keyword_resolves_to_the_upcoming_date(): void
    {
        Carbon::setTestNow('2026-07-03'); // Freitag
        [$parent, $child] = $this->parentWithTom();
        $this->fakeIntent([
            'intent' => 'abholzeit', 'kind' => 'Tom',
            'datum' => 'montag', 'uhrzeit' => '15:00', 'art' => 'abgeholt',
        ]);

        $reply = app(HortAssistant::class)->reply($parent, 'ich hole Tom nächsten Montag um 15 uhr ab');

        // Not today (Fri 03.07) — the coming Monday, 06.07.
        $this->assertDatabaseHas('daily_departures', [
            'child_id' => $child->id,
            'date' => '2026-07-06',
        ]);
        $this->assertDatabaseMissing('daily_departures', ['child_id' => $child->id, 'date' => '2026-07-03']);
        $this->assertStringContainsString('Montag', $reply);
    }

    public function test_it_answers_a_question(): void
    {
        [$parent] = $this->parentWithTom();
        $this->fakeIntent(['intent' => 'frage']);
        HortAnswerAgent::fake(fn () => 'Tom geht heute um 15 Uhr.');

        $reply = app(HortAssistant::class)->reply($parent, 'Wann geht Tom heute?');

        $this->assertStringContainsString('15 Uhr', $reply);
    }

    public function test_it_rewrites_llm_markdown_into_slack_mrkdwn(): void
    {
        [$parent] = $this->parentWithTom();
        $this->fakeIntent(['intent' => 'frage']);
        HortAnswerAgent::fake(fn () => "## Plan\n**Tom** wird abgeholt.\n- Montag\n[App](https://hort.test)");

        $reply = app(HortAssistant::class)->reply($parent, 'Was ist der Plan?');

        $this->assertStringContainsString("Plan\n", $reply);          // heading marker stripped
        $this->assertStringNotContainsString('#', $reply);
        $this->assertStringContainsString('*Tom*', $reply);           // bold collapsed to single *
        $this->assertStringNotContainsString('**', $reply);
        $this->assertStringContainsString('• Montag', $reply);        // bullet normalised
        $this->assertStringContainsString('<https://hort.test|App>', $reply); // link rewritten
    }

    public function test_it_reports_an_absence_with_the_away_reason(): void
    {
        Carbon::setTestNow('2026-06-22');
        [$parent, $child] = $this->parentWithTom();
        $this->fakeIntent(['intent' => 'abwesend', 'kind' => 'Tom', 'datum' => 'heute']);

        app(HortAssistant::class)->reply($parent, 'Tom ist heute nicht da');

        $this->assertDatabaseHas('absences', [
            'child_id' => $child->id, 'date' => '2026-06-22', 'reason' => 'away',
        ]);
    }

    public function test_it_records_an_excursion_rsvp(): void
    {
        Queue::fake();
        [$parent, $child] = $this->parentWithTom();
        $excursion = Excursion::factory()->create([
            'name' => 'Zoo', 'date' => now()->addWeek()->toDateString(),
            'rsvp_deadline' => now()->addDays(3)->toDateString(),
        ]);
        $excursion->children()->attach($child->id); // pending invite
        $this->fakeIntent(['intent' => 'ausflug', 'kind' => 'Tom', 'ausflug' => 'Zoo', 'zusage' => true]);

        $reply = app(HortAssistant::class)->reply($parent, 'Tom kommt beim Zoo mit');

        $this->assertDatabaseHas('child_excursion', [
            'child_id' => $child->id, 'excursion_id' => $excursion->id,
            'response' => true, 'answered_by' => $parent->id,
        ]);
        Queue::assertPushed(SyncExcursionRsvp::class);
        $this->assertStringContainsString('Zoo', $reply);
    }

    public function test_it_declines_an_excursion_rsvp(): void
    {
        Queue::fake();
        [$parent, $child] = $this->parentWithTom();
        $excursion = Excursion::factory()->create([
            'name' => 'Zoo', 'date' => now()->addWeek()->toDateString(),
            'rsvp_deadline' => now()->addDays(3)->toDateString(),
        ]);
        $excursion->children()->attach($child->id);
        $this->fakeIntent(['intent' => 'ausflug', 'kind' => 'Tom', 'ausflug' => 'Zoo', 'zusage' => false]);

        app(HortAssistant::class)->reply($parent, 'Tom kommt nicht mit zum Zoo');

        $this->assertDatabaseHas('child_excursion', [
            'child_id' => $child->id, 'excursion_id' => $excursion->id, 'response' => false,
        ]);
    }

    public function test_it_refuses_an_rsvp_once_the_poll_is_closed(): void
    {
        Queue::fake();
        [$parent, $child] = $this->parentWithTom();
        $excursion = Excursion::factory()->create([
            'name' => 'Zoo', 'date' => now()->addWeek()->toDateString(),
            'rsvp_deadline' => now()->subDay()->toDateString(), // deadline passed
        ]);
        $excursion->children()->attach($child->id);
        $this->fakeIntent(['intent' => 'ausflug', 'kind' => 'Tom', 'ausflug' => 'Zoo', 'zusage' => true]);

        $reply = app(HortAssistant::class)->reply($parent, 'Tom kommt doch mit zum Zoo');

        $this->assertStringContainsString('geschlossen', $reply);
        $this->assertDatabaseHas('child_excursion', [
            'child_id' => $child->id, 'excursion_id' => $excursion->id, 'response' => null,
        ]);
        Queue::assertNotPushed(SyncExcursionRsvp::class);
    }

    public function test_it_never_touches_a_child_that_is_not_the_parents(): void
    {
        $parent = User::factory()->create(['role' => UserRole::Parent]);
        $tom = Child::factory()->create(['name' => 'Tom']);
        $lena = Child::factory()->create(['name' => 'Lena']);
        $parent->children()->attach([$tom->id, $lena->id]);
        $stranger = Child::factory()->create(['name' => 'Max']); // another family's child
        $this->fakeIntent(['intent' => 'krank', 'kind' => 'Max', 'datum' => 'heute']);

        $reply = app(HortAssistant::class)->reply($parent, 'Max ist krank');

        // The stranger is never in scope, so no absence can be created for them.
        $this->assertDatabaseMissing('absences', ['child_id' => $stranger->id]);
        $this->assertStringContainsString('Kind', $reply); // asks which of the parent's own kids
    }

    public function test_a_single_child_parent_naming_another_child_is_asked_not_guessed(): void
    {
        [$parent, $child] = $this->parentWithTom(); // sole child: Tom
        $this->fakeIntent(['intent' => 'krank', 'kind' => 'Fremdkind', 'datum' => 'heute']);

        $reply = app(HortAssistant::class)->reply($parent, 'Fremdkind ist krank');

        // Must NOT silently apply to Tom just because he's the only child.
        $this->assertDatabaseMissing('absences', ['child_id' => $child->id]);
        $this->assertStringContainsString('Kind', $reply);
    }

    public function test_an_unknown_intent_returns_the_help_text(): void
    {
        [$parent] = $this->parentWithTom();
        $this->fakeIntent(['intent' => 'unbekannt']);

        $reply = app(HortAssistant::class)->reply($parent, 'Schönes Wochenende!');

        $this->assertStringContainsString('krank', $reply); // help lists example phrasings
    }

    public function test_a_parent_without_children_is_told_so(): void
    {
        $parent = User::factory()->create(['role' => UserRole::Parent]);

        $reply = app(HortAssistant::class)->reply($parent, 'Tom ist krank');

        $this->assertStringContainsString('kein Kind', $reply);
    }
}
