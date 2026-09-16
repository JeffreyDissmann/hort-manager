<?php

declare(strict_types=1);

use App\Enums\BookingStatus;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Models\User;

// Pre-filling a category from the bookings overview: tick a row, pick a category in the
// bulk bar, „Kategorie setzen" — the category lands, the booking stays unconfirmed.

it('pre-fills a category on the selected bookings without confirming them', function () {
    $admin = User::factory()->admin()->accountingWriter()->create();
    $category = Category::factory()->income()->create(['name' => 'Essensgeld']);
    $booking = Booking::factory()->draft()->create(['category_id' => null, 'purpose' => 'SEPA-DAUERAUFTRAG Huber']);

    $page = actAndVisit($admin, '/accounting/bookings');

    $page->assertDontSee('Essensgeld')
        ->click('@booking-select')
        ->assertVisible('@bookings-bulk-bar')
        ->select('@bookings-assign-category-select', (string) $category->id)
        ->click('@bookings-assign-category')
        // The row now shows the category, is still a draft, and the bar has closed.
        ->assertSee('Essensgeld')
        ->assertSee('Entwurf')
        ->assertMissing('@bookings-bulk-bar')
        ->assertNoJavaScriptErrors();

    expect($booking->refresh()->category_id)->toBe($category->id)
        ->and($booking->status)->toBe(BookingStatus::Draft);
});
