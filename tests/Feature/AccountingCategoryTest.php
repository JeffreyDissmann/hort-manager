<?php

declare(strict_types=1);

use App\Enums\CategoryDirection;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Models\User;
use App\Support\Accounting\CategoryOptions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('forbids non-admins from categories', function () {
    $staff = User::factory()->staff()->create();

    $this->actingAs($staff)->get('/accounting/categories')->assertForbidden();
});

it('renders the income and expense trees', function () {
    $admin = User::factory()->admin()->create();
    $root = Category::factory()->expense()->create(['name' => 'Betrieb']);
    Category::factory()->childOf($root)->create(['name' => 'Miete']);

    $this->actingAs($admin)
        ->get('/accounting/categories')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Categories/Index')
            ->where('trees.expense.0.name', 'Betrieb')
            ->where('trees.expense.0.children.0.name', 'Miete')
            ->where('trees.income', []));
});

it('creates a root category with a direction', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/accounting/categories', ['name' => 'Essensgeld', 'direction' => 'income'])
        ->assertRedirect();

    $category = Category::firstWhere('name', 'Essensgeld');
    expect($category->direction)->toBe(CategoryDirection::Income)
        ->and($category->parent_id)->toBeNull();
});

it('inherits the parent direction for a child, ignoring a mismatched submission', function () {
    $admin = User::factory()->admin()->create();
    $parent = Category::factory()->expense()->create();

    $this->actingAs($admin)
        ->post('/accounting/categories', [
            'name' => 'Miete',
            'parent_id' => $parent->id,
            'direction' => 'income', // should be ignored — parent is expense
        ])
        ->assertRedirect();

    expect(Category::firstWhere('name', 'Miete')->direction)->toBe(CategoryDirection::Expense);
});

it('requires a direction for a root category', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post('/accounting/categories', ['name' => 'Ohne Richtung'])
        ->assertSessionHasErrors('direction');
});

it('renames and deactivates a category', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->create(['name' => 'Alt', 'active' => true]);

    $this->actingAs($admin)
        ->patch("/accounting/categories/{$category->id}", ['name' => 'Neu', 'active' => false])
        ->assertRedirect();

    expect($category->refresh()->name)->toBe('Neu')
        ->and($category->active)->toBeFalse();
});

it('stores a category comment and exposes it to the options helper', function () {
    $admin = User::factory()->admin()->create();
    $category = Category::factory()->income()->create(['name' => 'Essensgeld']);

    $this->actingAs($admin)
        ->patch("/accounting/categories/{$category->id}", [
            'name' => 'Essensgeld',
            'comment' => 'Monatlicher Beitrag fürs Mittagessen',
            'active' => true,
        ])
        ->assertRedirect();

    expect($category->refresh()->comment)->toBe('Monatlicher Beitrag fürs Mittagessen');

    $option = collect(CategoryOptions::flat())->firstWhere('id', $category->id);
    expect($option['comment'])->toBe('Monatlicher Beitrag fürs Mittagessen');
});

it('deletes an empty category but refuses one with bookings in its subtree', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin);

    $empty = Category::factory()->create();
    $this->delete("/accounting/categories/{$empty->id}");
    expect(Category::find($empty->id))->toBeNull();

    // Root → child → booking on the child; deleting the root must be refused.
    $root = Category::factory()->expense()->create();
    $child = Category::factory()->childOf($root)->create();
    Booking::factory()->expense()->create(['category_id' => $child->id]);

    $this->delete("/accounting/categories/{$root->id}");
    expect(Category::find($root->id))->not->toBeNull();
});
