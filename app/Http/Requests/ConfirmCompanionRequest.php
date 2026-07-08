<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\DepartureMethod;
use App\Models\DailyDeparture;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmCompanionRequest extends FormRequest
{
    /**
     * 404 unless this really is a companion arrangement — runs before authorize(), so
     * we don't leak (via a 403) that some unrelated departure id exists.
     */
    protected function prepareForValidation(): void
    {
        $departure = $this->route('departure');

        abort_unless(
            $departure instanceof DailyDeparture
                && $departure->planned_method === DepartureMethod::WithChild
                && $departure->companion_child_id !== null,
            404,
        );
    }

    /**
     * Only the *companion's* guardian (whose home the child would come to) may answer;
     * staff may answer on any family's behalf. A guardian of the tagging-along child
     * has no say here → 403.
     */
    public function authorize(): bool
    {
        $departure = $this->route('departure');
        $user = $this->user();

        return $user->isStaff() || ($departure->companion?->isGuardedBy($user) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['confirmed' => ['required', 'boolean']];
    }
}
