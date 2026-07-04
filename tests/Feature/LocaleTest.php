<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\DepartureStatus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_interface_defaults_to_german(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'de')
                ->where('translations.common.save', 'Speichern')
            );
    }

    public function test_a_user_can_switch_the_interface_to_english(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->patch(route('locale.update'), ['locale' => 'en'])
            ->assertRedirect();

        $this->assertSame('en', $user->fresh()->locale);

        $this->actingAs($user->fresh())
            ->get(route('dashboard'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('locale', 'en')
                ->where('translations.common.save', 'Save')
            );
    }

    public function test_server_side_enum_labels_follow_the_active_locale(): void
    {
        App::setLocale('de');
        $this->assertSame('Abgeholt', DepartureStatus::PickedUp->label());

        App::setLocale('en');
        $this->assertSame('Picked up', DepartureStatus::PickedUp->label());
    }

    public function test_an_unknown_locale_is_rejected(): void
    {
        $user = User::factory()->create(['locale' => 'de']);

        $this->actingAs($user)
            ->patch(route('locale.update'), ['locale' => 'fr'])
            ->assertSessionHasErrors('locale');

        $this->assertSame('de', $user->fresh()->locale);
    }
}
