<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Enums\BookingKind;
use App\Enums\BookingStatus;
use App\Models\Accounting\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
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
            ...self::bookingRules(),
            // Only used by the edit form's review-status toggle.
            'status' => ['nullable', Rule::enum(BookingStatus::class)],
        ];
    }

    /**
     * The core booking-field rules, shared with the step-through review confirm.
     *
     * @return array<string, mixed>
     */
    public static function bookingRules(): array
    {
        return [
            'account_id' => ['required', Rule::exists('accounting_accounts', 'id')],
            'category_id' => ['required', Rule::exists('accounting_categories', 'id')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'booking_date' => ['required', 'date'],
            'valuta_date' => ['nullable', 'date'],
            'purpose' => ['nullable', 'string', 'max:2000'],
            'comment' => ['nullable', 'string', 'max:2000'],
            // Optional link to a document in the external Paperless archive.
            'paperless_document_id' => ['nullable', 'integer', 'min:1'],
            'paperless_document_title' => ['nullable', 'string', 'max:255'],
            'counterparty_child_id' => ['nullable', Rule::exists('children', 'id')],
            'counterparty_user_id' => ['nullable', Rule::exists('users', 'id')],
            'counterparty_name' => ['nullable', 'string', 'max:255'],
            // A „reversal" flips the cash-flow sign against the category's normal
            // direction — e.g. a refund on an expense category (money comes back in).
            'reversal' => ['sometimes', 'boolean'],
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
        return self::attributesFor($this->validated());
    }

    /**
     * Map validated booking-form data to booking columns — kind + signed cents from
     * the category's direction, mutually-exclusive counterparty, valuta defaulting to
     * the booking date. Shared by the create/edit form and the review confirm.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function attributesFor(array $data): array
    {
        $category = Category::findOrFail((int) $data['category_id']);
        $magnitude = (int) round((float) $data['amount'] * 100);

        // A reversal flips the sign against the category's normal direction (a refund
        // on an expense stays kind=expense but with a positive amount, so the report
        // nets it off the expenses).
        $sign = $category->direction->sign() * (! empty($data['reversal']) ? -1 : 1);

        // Counterparty precedence: child (income) beats user (person) beats free text.
        $childId = ! empty($data['counterparty_child_id']) ? (int) $data['counterparty_child_id'] : null;
        $userId = ! $childId && ! empty($data['counterparty_user_id']) ? (int) $data['counterparty_user_id'] : null;

        return [
            'account_id' => (int) $data['account_id'],
            'category_id' => $category->id,
            'kind' => BookingKind::from($category->direction->value),
            'amount_cents' => $magnitude * $sign,
            'currency' => 'EUR',
            'booking_date' => Carbon::parse($data['booking_date'])->toDateString(),
            'valuta_date' => Carbon::parse(($data['valuta_date'] ?? null) ?: $data['booking_date'])->toDateString(),
            'purpose' => $data['purpose'] ?? null,
            'comment' => $data['comment'] ?? null,
            // Null id clears the link (and its cached title); title only kept alongside an id.
            'paperless_document_id' => ! empty($data['paperless_document_id']) ? (int) $data['paperless_document_id'] : null,
            'paperless_document_title' => ! empty($data['paperless_document_id']) ? ($data['paperless_document_title'] ?? null) : null,
            'counterparty_child_id' => $childId,
            'counterparty_user_id' => $userId,
            'counterparty_name' => ($childId || $userId) ? null : ($data['counterparty_name'] ?? null),
        ];
    }
}
