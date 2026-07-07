<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\ConfirmCompanionRequest;
use App\Models\DailyDeparture;
use App\Notifications\CompanionAnswered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;

class CompanionConfirmationController extends Controller
{
    /**
     * The companion child's guardian (or any staff member) confirms or declines
     * another child tagging along home with theirs. Applicability (404) and
     * authorization (403) are enforced by ConfirmCompanionRequest.
     */
    public function update(ConfirmCompanionRequest $request, DailyDeparture $departure): RedirectResponse
    {
        $confirmed = $request->boolean('confirmed');

        $departure->update([
            'companion_confirmed' => $confirmed,
            'companion_confirmed_by' => $request->user()->id,
            'companion_confirmed_at' => now(),
        ]);

        // Close the loop: tell the requesting child's family the outcome (now „mit …",
        // or — on a No — that their child stays a normal pickup at the synced time).
        Notification::send(
            $departure->child->guardians()->get(),
            new CompanionAnswered($departure, $confirmed),
        );

        return back()->with('status', __('flash.companion_answered', ['name' => $departure->child->name]));
    }
}
