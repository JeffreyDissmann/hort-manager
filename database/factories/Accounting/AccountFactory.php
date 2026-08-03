<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Account>
 */
class AccountFactory extends Factory
{
    protected $model = Account::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Konto', 'Bar-Kasse', 'Sparkonto']),
            'iban' => null,
            'opening_balance_cents' => 0,
            'opening_balance_date' => null,
            'active' => true,
        ];
    }

    /** Give the account a starting balance as of a date. */
    public function withOpeningBalance(int $cents, string $date = '2026-01-01'): static
    {
        return $this->state(['opening_balance_cents' => $cents, 'opening_balance_date' => $date]);
    }
}
