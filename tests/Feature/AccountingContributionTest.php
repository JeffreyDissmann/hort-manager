<?php

declare(strict_types=1);

use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Models\Child;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

it('forbids non-admins from the contributions view', function () {
    $this->actingAs(User::factory()->staff()->create())
        ->get('/accounting/contributions')
        ->assertForbidden();
});

it('builds a child × month matrix of confirmed contributions in the selected group', function () {
    $admin = User::factory()->admin()->create();
    $group = Category::factory()->income()->create(['name' => 'Beiträge', 'position' => 1]);
    $elternbeitrag = Category::factory()->childOf($group)->create(['name' => 'Elternbeitrag', 'position' => 1]);
    $emma = Child::factory()->create(['name' => 'Emma']);
    $noah = Child::factory()->create(['name' => 'Noah']);

    Booking::factory()->create(['category_id' => $elternbeitrag->id, 'counterparty_child_id' => $emma->id, 'amount_cents' => 13000, 'booking_date' => '2026-01-10']);
    Booking::factory()->create(['category_id' => $elternbeitrag->id, 'counterparty_child_id' => $emma->id, 'amount_cents' => 13000, 'booking_date' => '2026-02-10']);
    Booking::factory()->create(['category_id' => $elternbeitrag->id, 'counterparty_child_id' => $noah->id, 'amount_cents' => 13000, 'booking_date' => '2026-01-12']);
    // Excluded: an unconfirmed payment.
    Booking::factory()->draft()->create(['category_id' => $elternbeitrag->id, 'counterparty_child_id' => $noah->id, 'amount_cents' => 13000, 'booking_date' => '2026-03-01']);

    $this->actingAs($admin)
        ->get('/accounting/contributions?year=2026')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Contributions/Index')
            ->where('root', $group->id)        // first income group pre-selected
            ->where('year', 2026)
            ->has('rows', 2)
            ->where('rows.0.name', 'Emma')
            ->where('rows.0.months.0', 13000)
            ->where('rows.0.months.1', 13000)
            ->where('rows.0.total', 26000)
            ->where('rows.1.name', 'Noah')
            ->where('rows.1.months.1', 0)
            ->where('monthTotals.0', 26000)
            ->where('grandTotal', 39000));
});

it('defaults to the first income group and can switch groups', function () {
    $admin = User::factory()->admin()->create();
    $beitraege = Category::factory()->income()->create(['name' => 'Beiträge', 'position' => 1]);
    $ertraege = Category::factory()->income()->create(['name' => 'Erträge', 'position' => 2]);
    $elternbeitrag = Category::factory()->childOf($beitraege)->create(['name' => 'Elternbeitrag']);
    $foerderung = Category::factory()->childOf($ertraege)->create(['name' => 'EKI Förderung']);
    $emma = Child::factory()->create(['name' => 'Emma']);

    Booking::factory()->create(['category_id' => $elternbeitrag->id, 'counterparty_child_id' => $emma->id, 'amount_cents' => 13000, 'booking_date' => '2026-01-10']);
    Booking::factory()->create(['category_id' => $foerderung->id, 'counterparty_child_id' => $emma->id, 'amount_cents' => 4000, 'booking_date' => '2026-01-10']);

    // Default group = Beiträge → only the Elternbeitrag payment.
    $this->actingAs($admin)
        ->get('/accounting/contributions?year=2026')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('root', $beitraege->id)
            ->has('roots', 2)
            ->where('grandTotal', 13000));

    // Switching to Erträge scopes to that group.
    $this->actingAs($admin)
        ->get("/accounting/contributions?year=2026&root={$ertraege->id}")
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('root', $ertraege->id)
            ->where('grandTotal', 4000));
});

it('shows every sub-category per child, unpaid ones as zero rows', function () {
    $admin = User::factory()->admin()->create();
    $group = Category::factory()->income()->create(['name' => 'Beiträge', 'position' => 1]);
    $elternbeitrag = Category::factory()->childOf($group)->create(['name' => 'Elternbeitrag', 'position' => 1]);
    Category::factory()->childOf($group)->create(['name' => 'Essensgeld', 'position' => 2]);
    $emma = Child::factory()->create(['name' => 'Emma']);

    // Emma pays only Elternbeitrag — Essensgeld must still appear as a zero row.
    Booking::factory()->create(['category_id' => $elternbeitrag->id, 'counterparty_child_id' => $emma->id, 'amount_cents' => 13000, 'booking_date' => '2026-01-10']);

    $this->actingAs($admin)
        ->get('/accounting/contributions?year=2026')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.0.total', 13000)
            ->has('rows.0.breakdown', 2)               // both sub-categories, always
            ->where('rows.0.breakdown.0.name', 'Elternbeitrag')
            ->where('rows.0.breakdown.0.total', 13000)
            ->where('rows.0.breakdown.1.name', 'Essensgeld')
            ->where('rows.0.breakdown.1.total', 0));   // unpaid, shown as a gap
});

it('collects child-less contributions into the unassigned bucket', function () {
    $admin = User::factory()->admin()->create();
    $group = Category::factory()->income()->create(['name' => 'Beiträge', 'position' => 1]);
    $elternbeitrag = Category::factory()->childOf($group)->create(['name' => 'Elternbeitrag']);
    $emma = Child::factory()->create(['name' => 'Emma']);

    Booking::factory()->create(['category_id' => $elternbeitrag->id, 'counterparty_child_id' => $emma->id, 'amount_cents' => 13000, 'booking_date' => '2026-01-10']);
    // Income with no child link (free text) → misassigned, must land in the bucket.
    Booking::factory()->create(['category_id' => $elternbeitrag->id, 'counterparty_child_id' => null, 'counterparty_name' => 'Unklar', 'amount_cents' => 9900, 'booking_date' => '2026-02-05']);

    $this->actingAs($admin)
        ->get('/accounting/contributions?year=2026')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.0.total', 13000)
            ->where('unassignedMonths.1', 9900)
            ->where('unassignedTotal', 9900));
});

it('links the bookings list to the misassigned income', function () {
    $admin = User::factory()->admin()->create();
    $group = Category::factory()->income()->create(['name' => 'Beiträge', 'position' => 1]);
    $elternbeitrag = Category::factory()->childOf($group)->create(['name' => 'Elternbeitrag']);
    $emma = Child::factory()->create(['name' => 'Emma']);

    Booking::factory()->create(['category_id' => $elternbeitrag->id, 'counterparty_child_id' => $emma->id, 'amount_cents' => 13000, 'booking_date' => '2026-01-10']);
    $unlinked = Booking::factory()->create(['category_id' => $elternbeitrag->id, 'counterparty_child_id' => null, 'counterparty_name' => 'Unklar', 'amount_cents' => 9900, 'booking_date' => '2026-02-05']);

    $this->actingAs($admin)
        ->get('/accounting/bookings?unassigned=1&status=confirmed')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('Accounting/Bookings/Index')
            ->has('bookings.data', 1)
            ->where('bookings.data.0.id', $unlinked->id));
});

it('rolls a grandchild-category contribution up into its top-level stream', function () {
    $admin = User::factory()->admin()->create();
    $group = Category::factory()->income()->create(['name' => 'Beiträge', 'position' => 1]);
    $stream = Category::factory()->childOf($group)->create(['name' => 'Elternbeitrag', 'position' => 1]);
    $grand = Category::factory()->childOf($stream)->create(['name' => '2026', 'position' => 1]);
    $emma = Child::factory()->create(['name' => 'Emma']);

    // Booked on the grandchild category — must still count under its stream.
    Booking::factory()->create(['category_id' => $grand->id, 'counterparty_child_id' => $emma->id, 'amount_cents' => 13000, 'booking_date' => '2026-01-10']);

    $this->actingAs($admin)
        ->get('/accounting/contributions?year=2026')
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->where('rows.0.total', 13000)
            ->has('rows.0.breakdown', 1)
            ->where('rows.0.breakdown.0.name', 'Elternbeitrag')
            ->where('rows.0.breakdown.0.total', 13000));
});
