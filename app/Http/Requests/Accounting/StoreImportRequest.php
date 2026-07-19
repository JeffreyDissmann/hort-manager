<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Upload a bank-statement CSV against one account. */
class StoreImportRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', Rule::exists('accounting_accounts', 'id')],
            // Validate by extension, not MIME: bank CSVs are often UTF-16 and get
            // detected as application/octet-stream, which a mimes: rule would reject.
            'file' => ['required', 'file', 'extensions:csv,txt', 'max:5120'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.extensions' => __('accounting.import.file_invalid'),
            'file.mimes' => __('accounting.import.file_invalid'),
            'file.uploaded' => __('accounting.import.file_invalid'),
        ];
    }
}
