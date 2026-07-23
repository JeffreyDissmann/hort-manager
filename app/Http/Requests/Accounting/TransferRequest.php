<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Create an internal transfer moving money from one own account to another. */
class TransferRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_account_id' => ['required', 'different:to_account_id', Rule::exists('accounting_accounts', 'id')],
            'to_account_id' => ['required', Rule::exists('accounting_accounts', 'id')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'booking_date' => ['required', 'date'],
            'valuta_date' => ['nullable', 'date'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'comment' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
