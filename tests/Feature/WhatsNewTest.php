<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WhatsNewTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_pages_share_the_latest_whats_new_entries(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('whatsNew', count(array_slice((array) config('whats_new'), 0, 5)))
                ->has('whatsNew.0.version')
                ->has('whatsNew.0.title')
                ->has('whatsNew.0.items'));
    }
}
