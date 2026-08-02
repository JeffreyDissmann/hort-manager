<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class HelpPageTest extends TestCase
{
    use RefreshDatabase;

    /** Every chapter the overview links to. */
    public static function topics(): array
    {
        return array_map(
            fn (string $topic): array => [$topic],
            ['getting-started', 'pickups', 'absences', 'holidays', 'excursions', 'slack', 'staff', 'glossary'],
        );
    }

    public function test_the_help_page_is_reachable_without_logging_in(): void
    {
        $this->get(route('help'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Help')->where('topic', null));
    }

    public function test_the_help_page_is_reachable_when_logged_in(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('help'))
            ->assertOk();
    }

    #[DataProvider('topics')]
    public function test_each_chapter_has_its_own_page(string $topic): void
    {
        // Guests too: a parent may well read up before their first sign-in.
        $this->get(route('help', $topic))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Help')->where('topic', $topic));
    }

    public function test_an_unknown_chapter_is_not_a_page(): void
    {
        // The slug picks a component on the client — anything else must 404 rather
        // than render an empty chapter.
        $this->get('/help/pizza')->assertNotFound();
    }

    #[DataProvider('topics')]
    public function test_every_chapter_is_translated_in_both_languages(string $topic): void
    {
        // A missing key renders as the key itself, which nobody would notice in
        // review — so assert the overview's card texts exist.
        foreach (['de', 'en'] as $locale) {
            $this->assertIsString(__("help.topics.{$topic}.title", [], $locale));
            $this->assertNotSame("help.topics.{$topic}.title", __("help.topics.{$topic}.title", [], $locale));
            $this->assertNotSame("help.topics.{$topic}.teaser", __("help.topics.{$topic}.teaser", [], $locale));

            // The chapter's own content block must exist as well.
            $this->assertIsArray(__("help.{$topic}", [], $locale), "help.{$topic} missing in {$locale}");
        }
    }
}
