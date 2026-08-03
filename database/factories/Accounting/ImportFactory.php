<?php

declare(strict_types=1);

namespace Database\Factories\Accounting;

use App\Models\Accounting\Account;
use App\Models\Accounting\Import;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Import>
 */
class ImportFactory extends Factory
{
    protected $model = Import::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'account_id' => Account::factory(),
            'uploaded_by' => null,
            'filename' => 'Umsatzliste.csv',
            'row_count' => 0,
            'imported_count' => 0,
            'duplicate_count' => 0,
        ];
    }
}
