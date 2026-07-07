<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\AbsenceReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAbsenceRequest extends FormRequest
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
            'child_id' => ['required', 'exists:children,id'],
            'from' => ['required', 'date', 'after_or_equal:today'],
            // Cap the span at ~a school year: the controller writes one row per day,
            // so an unbounded range would flood the DB from a single request.
            'to' => ['required', 'date', 'after_or_equal:from', 'before_or_equal:'.now()->addYear()->toDateString()],
            'reason' => ['required', Rule::enum(AbsenceReason::class)],
            // Optional here so the board's quick-report and the assistant still work;
            // the Wochenplan editor enforces it on the client.
            'comment' => ['nullable', 'string', 'max:255'],
        ];
    }
}
