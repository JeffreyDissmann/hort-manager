<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DepartureStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MarkDepartureRequest extends FormRequest
{
    /** Staff-only is enforced in the controller (authorize('mark', $departure)). */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            // Board marking only sets a live status; „excursion" is an overlay, not set here.
            'status' => ['required', Rule::in([
                DepartureStatus::Present->value,
                DepartureStatus::PickedUp->value,
                DepartureStatus::SentHome->value,
            ])],
        ];
    }
}
