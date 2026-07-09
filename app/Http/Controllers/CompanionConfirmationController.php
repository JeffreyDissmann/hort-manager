<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmCompanionRequest;
use App\Models\DailyDeparture;
use App\Support\CompanionAnswer;
use Illuminate\Http\RedirectResponse;

class CompanionConfirmationController extends Controller
{
    /**
     * The companion child's guardian (or any staff member) confirms or declines
     * another child tagging along home with theirs. Applicability (404) and
     * authorization (403) are enforced by ConfirmCompanionRequest.
     */
    public function update(ConfirmCompanionRequest $request, DailyDeparture $departure): RedirectResponse
    {
        CompanionAnswer::record($departure, $request->boolean('confirmed'), $request->user()->id);

        return back()->with('status', __('flash.companion_answered', ['name' => $departure->child->name]));
    }
}
