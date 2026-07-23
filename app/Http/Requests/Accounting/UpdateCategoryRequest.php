<?php

declare(strict_types=1);

namespace App\Http\Requests\Accounting;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Rename or (de)activate a category. Direction and parent are fixed once created —
 * moving a subtree across directions would break its bookings, so it's not allowed here.
 */
class UpdateCategoryRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'comment' => ['nullable', 'string', 'max:1000'],
            'active' => ['boolean'],
        ];
    }
}
