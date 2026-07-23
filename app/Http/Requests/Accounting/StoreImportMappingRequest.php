<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Support\Accounting\StatementMapper;
use Illuminate\Foundation\Http\FormRequest;

/** Confirm the column mapping (field → column index) for a pending import. */
class StoreImportMappingRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [];

        foreach (StatementMapper::FIELDS as $field) {
            $required = in_array($field, StatementMapper::REQUIRED_FIELDS, true);
            $rules["mapping.{$field}"] = [$required ? 'required' : 'nullable', 'integer', 'min:0'];
        }

        return $rules;
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return array_merge(...array_map(
            fn (string $field): array => ["mapping.{$field}.required" => __('accounting.import.map_required')],
            StatementMapper::REQUIRED_FIELDS,
        ));
    }

    /**
     * The validated field → column-index mapping (optional fields absent → null).
     *
     * @return array<string, int|null>
     */
    public function mapping(): array
    {
        $mapping = $this->validated()['mapping'] ?? [];

        return collect(StatementMapper::FIELDS)
            ->mapWithKeys(fn (string $field): array => [$field => isset($mapping[$field]) ? (int) $mapping[$field] : null])
            ->all();
    }
}
