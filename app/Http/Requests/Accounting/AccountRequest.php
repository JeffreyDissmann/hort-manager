<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

/** Create/update a Buchhaltung account. Admin access is enforced by the route group. */
class AccountRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34', 'regex:/^[A-Z]{2}[0-9A-Z ]+$/i'],
            // Opening balance is entered in euros; stored as cents by the controller.
            'opening_balance' => ['nullable', 'numeric', 'between:-99999999.99,99999999.99'],
            'opening_balance_date' => ['nullable', 'date'],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'iban.regex' => __('accounting.accounts.iban_invalid'),
        ];
    }

    /**
     * The validated data mapped to model columns (euros → cents, IBAN normalised).
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $iban = $this->filled('iban')
            ? strtoupper((string) preg_replace('/\s+/', '', (string) $this->input('iban')))
            : null;

        return [
            'name' => $this->string('name')->trim()->value(),
            'iban' => $iban,
            'opening_balance_cents' => (int) round((float) $this->input('opening_balance', 0) * 100),
            'opening_balance_date' => $this->input('opening_balance_date') ?: null,
            'active' => $this->boolean('active'),
        ];
    }
}
