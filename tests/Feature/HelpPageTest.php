<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class HelpPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_help_page_is_reachable_without_logging_in(): void
    {
        $this->get(route('help'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('Help'));
    }

    public function test_the_help_page_is_reachable_when_logged_in(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('help'))
            ->assertOk();
    }
}
