<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DepartureMethod;
use App\Enums\TimeQualifier;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class OverrideDepartureRequest extends FormRequest
{
    /** Guardianship is enforced in the controller (authorize('update', $child)). */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'planned_time' => ['required', 'date_format:H:i'],
            // The board has no companion picker; „geht mit … mit" is Wochenplan-only.
            'planned_method' => ['nullable', Rule::enum(DepartureMethod::class), Rule::notIn([DepartureMethod::WithChild->value])],
            'time_qualifier' => ['nullable', Rule::enum(TimeQualifier::class)],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }
}
