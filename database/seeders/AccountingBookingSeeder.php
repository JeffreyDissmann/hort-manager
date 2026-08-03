<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Enums\CategoryDirection;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use App\Models\Accounting\Transfer;
use App\Models\User;
use Illuminate\Database\Seeder;

/** A handful of realistic April 2026 bookings (loosely based on the real statement). */
class AccountingBookingSeeder extends Seeder
{
    public function run(): void
    {
        $bank = Account::where('name', 'Hort-Konto')->first();
        $cash = Account::where('name', 'Bar-Kasse')->first();

        if (! $bank || ! $cash) {
            return;
        }

        $parent = User::where('email', 'eltern@hort.test')->first();

        // [account, category name, euros (positive), date, purpose, counterparty?, status?]
        $rows = [
            [$bank, 'Essensgeld', 50.00, '2026-04-01', 'SEPA-GUTSCHRIFT Essensgeld', 'Familie Müller'],
            [$bank, 'Vereinsbeitrag', 110.00, '2026-04-01', 'SEPA-GUTSCHRIFT Vereinsbeitrag', 'Familie Klein'],
            [$bank, 'Elternbeitrag', 180.00, '2026-04-02', 'SEPA-GUTSCHRIFT Elternbeitrag', $parent],
            [$bank, 'Essensgeld', 50.00, '2026-04-03', 'SEPA-GUTSCHRIFT Essensgeld', $parent],
            [$bank, 'Beitrag für Hortfreizeit', 75.00, '2026-04-08', 'Überweisung Hortfreizeit Juni', 'Familie Wagner'],

            [$bank, 'Raumkosten', 3520.00, '2026-04-01', 'DAUERAUFTRAG Miete + NK-Vorauszahlung', 'Vermietung Musterstraße'],
            [$bank, 'Ausflüge', 14.00, '2026-03-31', 'Kartenzahlung Museumsbesuch', null],
            [$bank, 'Lebensmittel', 68.42, '2026-04-04', 'Kartenzahlung Supermarkt', null],
            [$bank, 'Drogerie', 23.90, '2026-04-04', 'Kartenzahlung Drogeriemarkt', null],
            [$bank, 'Versicherung', 42.30, '2026-04-01', 'Beitrag Haftpflichtversicherung', null],
            [$bank, 'Telefon', 39.99, '2026-04-15', 'Lastschrift Telefonanbieter', null],
            [$bank, 'Zeitschriften Abo', 12.90, '2026-04-10', 'Abo Kinderzeitschrift', null],
            [$cash, 'Basteln', 18.50, '2026-04-09', 'Bastelbedarf bar bezahlt', null],
            [$cash, 'Büromaterial', 9.95, '2026-04-11', 'Druckerpapier', null],

            // Two drafts (as if freshly imported, awaiting review).
            [$bank, 'Lebensmittel', 51.20, '2026-04-18', 'Kartenzahlung Supermarkt', null, BookingStatus::Draft],
            [$bank, 'Essensgeld', 50.00, '2026-04-18', 'SEPA-GUTSCHRIFT Essensgeld', null, BookingStatus::Draft],
        ];

        foreach ($rows as $row) {
            [$account, $categoryName, $euros, $date, $purpose] = $row;
            $counterparty = $row[5] ?? null;
            $status = $row[6] ?? BookingStatus::Confirmed;

            $category = Category::where('name', $categoryName)->first();
            if (! $category) {
                continue;
            }

            $sign = $category->direction === CategoryDirection::Income ? 1 : -1;

            Booking::create([
                'account_id' => $account->id,
                'category_id' => $category->id,
                'kind' => BookingKind::from($category->direction->value),
                'status' => $status,
                'amount_cents' => (int) round($euros * 100) * $sign,
                'booking_date' => $date,
                'valuta_date' => $date,
                'purpose' => $purpose,
                'counterparty_user_id' => $counterparty instanceof User ? $counterparty->id : null,
                'counterparty_name' => is_string($counterparty) ? $counterparty : null,
            ]);
        }

        // A cash-to-bank transfer (Bar-Kasse → Hort-Konto).
        Transfer::record(
            fromAccountId: $cash->id,
            toAccountId: $bank->id,
            amountCents: 10000,
            bookingDate: '2026-04-12',
            purpose: 'Barbestand aufs Konto eingezahlt',
        );
    }
}
