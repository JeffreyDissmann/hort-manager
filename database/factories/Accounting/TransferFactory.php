<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Enums\BookingKind;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Transfer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Transfer>
 */
class TransferFactory extends Factory
{
    protected $model = Transfer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'out_booking_id' => Booking::factory()->state(['kind' => BookingKind::Transfer, 'category_id' => null, 'amount_cents' => -1000]),
            'in_booking_id' => Booking::factory()->state(['kind' => BookingKind::Transfer, 'category_id' => null, 'amount_cents' => 1000]),
            'created_by' => null,
        ];
    }
}
