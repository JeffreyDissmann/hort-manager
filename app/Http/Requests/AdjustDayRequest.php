<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DepartureMethod;
use App\Enums\TimeQualifier;
use App\Models\Absence;
use App\Support\EffectivePlan;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class AdjustDayRequest extends FormRequest
{
    /** Guardianship + day-editability are enforced in the controller (authorizedDeparture). */
    public function authorize(): bool
    {
        return true;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'child_id' => ['required', 'integer', 'exists:children,id'],
            'date' => ['required', 'date'],
            // A real pickup needs both a method and a time; „geht mit … mit" mirrors the
            // companion's time, so it needs no own time. (Reverting a day is a separate
            // endpoint, weekly-plan.reset — this endpoint always sets a complete plan.)
            'planned_method' => ['required', Rule::enum(DepartureMethod::class)],
            'planned_time' => [
                Rule::requiredIf(fn () => $this->input('planned_method') !== DepartureMethod::WithChild->value),
                'nullable', 'date_format:H:i',
            ],
            'time_qualifier' => ['nullable', Rule::enum(TimeQualifier::class)],
            // Required only for „geht mit … mit", and never the child itself. Deeper
            // checks (companion actually leaving, no chains) run in after().
            'companion_child_id' => [
                Rule::requiredIf(fn () => $this->input('planned_method') === DepartureMethod::WithChild->value),
                'nullable', 'integer', 'exists:children,id', 'different:child_id',
            ],
            'note' => ['nullable', 'string', 'max:255'],
        ];
    }

    /** @return array<int, callable> */
    public function after(): array
    {
        return [function (Validator $validator) {
            if ($this->input('planned_method') !== DepartureMethod::WithChild->value) {
                return;
            }

            $companionId = $this->input('companion_child_id');
            if (! $companionId || $validator->errors()->has('companion_child_id')) {
                return;
            }

            // A companion must actually be leaving that day (not away, not without a
            // pickup) and must not itself be tagging along with a third child — no chains.
            $date = (string) $this->input('date');
            $plan = EffectivePlan::for((int) $companionId, $date);
            $absent = Absence::query()->where('child_id', $companionId)->where('date', $date)->exists();

            $unavailable = $absent
                || $plan['time'] === null
                || $plan['method'] === null
                || $plan['method'] === DepartureMethod::WithChild->value;

            if ($unavailable) {
                $validator->errors()->add('companion_child_id', __('weekly.companion_unavailable'));
            }
        }];
    }
}
