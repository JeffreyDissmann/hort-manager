<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Models\Accounting\Account;
use App\Models\Accounting\Booking;
use App\Models\Accounting\Category;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    protected $model = Booking::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'category_id' => Category::factory()->income(),
            'kind' => BookingKind::Income,
            'status' => BookingStatus::Confirmed,
            'amount_cents' => fake()->numberBetween(500, 50000),
            'currency' => 'EUR',
            'booking_date' => '2026-04-01',
            'valuta_date' => '2026-04-01',
            'purpose' => fake()->sentence(),
            'comment' => null,
            'counterparty_user_id' => null,
            'counterparty_name' => null,
        ];
    }

    public function draft(): static
    {
        return $this->state(['status' => BookingStatus::Draft]);
    }

    /** An AI-analysed draft (its values are AI proposals, awaiting confirmation). */
    public function suggested(): static
    {
        return $this->state(['status' => BookingStatus::Suggested]);
    }

    /** An expense: negative amount, expense category. */
    public function expense(): static
    {
        return $this->state(fn (array $attributes): array => [
            'kind' => BookingKind::Expense,
            'category_id' => Category::factory()->expense(),
            'amount_cents' => -abs($attributes['amount_cents'] ?? 1000),
        ]);
    }
}
