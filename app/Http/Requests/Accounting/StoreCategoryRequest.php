<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use App\Enums\CategoryDirection;
use App\Models\Accounting\Category;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Create a category. A root needs an explicit income/expense direction; a child
 * inherits its parent's direction (the submitted value is ignored for children).
 */
class StoreCategoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', Rule::exists('accounting_categories', 'id')],
            'direction' => ['required_without:parent_id', Rule::enum(CategoryDirection::class)],
        ];
    }

    /** The direction the new node should carry — inherited from a parent if any. */
    public function resolvedDirection(): CategoryDirection
    {
        if ($this->filled('parent_id')) {
            return Category::findOrFail($this->integer('parent_id'))->direction;
        }

        return CategoryDirection::from($this->string('direction')->value());
    }
}
