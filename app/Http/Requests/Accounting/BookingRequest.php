<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Models\Accounting\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create/update a manual income or expense booking. The amount is entered as a
 * positive magnitude in euros; its sign and the booking's kind derive from the
 * chosen category's direction.
 */
class BookingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', Rule::exists('accounting_accounts', 'id')],
            'category_id' => ['required', Rule::exists('accounting_categories', 'id')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'booking_date' => ['required', 'date'],
            'valuta_date' => ['nullable', 'date'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'comment' => ['nullable', 'string', 'max:2000'],
            'counterparty_user_id' => ['nullable', Rule::exists('users', 'id')],
            'counterparty_name' => ['nullable', 'string', 'max:255'],
            // Only used by the edit form's review-status toggle.
            'status' => ['nullable', Rule::enum(BookingStatus::class)],
        ];
    }

    /**
     * The validated data mapped to booking columns: kind + signed cents derived
     * from the category's direction; valuta defaults to the booking date.
     *
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        $category = Category::findOrFail($this->integer('category_id'));
        $magnitude = (int) round((float) $this->input('amount') * 100);
        $userId = $this->filled('counterparty_user_id') ? $this->integer('counterparty_user_id') : null;

        return [
            'account_id' => $this->integer('account_id'),
            'category_id' => $category->id,
            'kind' => BookingKind::from($category->direction->value),
            'amount_cents' => $magnitude * $category->direction->sign(),
            'currency' => 'EUR',
            'booking_date' => $this->date('booking_date')->toDateString(),
            'valuta_date' => ($this->input('valuta_date') ?: $this->input('booking_date')),
            'purpose' => $this->input('purpose'),
            'comment' => $this->input('comment'),
            // A linked user wins; free-text name is only kept when no user is set.
            'counterparty_user_id' => $userId,
            'counterparty_name' => $userId ? null : $this->input('counterparty_name'),
        ];
    }
}
