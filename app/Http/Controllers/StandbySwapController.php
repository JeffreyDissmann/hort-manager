<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\StandbyDuty;
use App\Models\StandbySwap;
use App\Support\StandbySwaps;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Springerdienst swaps: a family offers one of its dates for another family's, the
 * other family accepts or declines, the asking family may withdraw. The rules live in
 * {@see StandbySwaps}; this only maps the four actions onto it.
 */
class StandbySwapController extends Controller
{
    public function __construct(private readonly StandbySwaps $swaps) {}

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'offered_duty_id' => ['required', 'integer', 'exists:standby_duties,id'],
            'wanted_duty_id' => ['required', 'integer', 'exists:standby_duties,id', 'different:offered_duty_id'],
        ]);

        $this->swaps->request(
            $request->user(),
            StandbyDuty::findOrFail($data['offered_duty_id']),
            StandbyDuty::findOrFail($data['wanted_duty_id']),
        );

        return back()->with('status', __('flash.standby_swap_requested'));
    }

    public function accept(Request $request, StandbySwap $swap): RedirectResponse
    {
        $this->swaps->accept($request->user(), $swap);

        return back()->with('status', __('flash.standby_swap_accepted'));
    }

    public function decline(Request $request, StandbySwap $swap): RedirectResponse
    {
        $this->swaps->decline($request->user(), $swap);

        return back()->with('status', __('flash.standby_swap_declined'));
    }

    public function destroy(Request $request, StandbySwap $swap): RedirectResponse
    {
        $this->swaps->cancel($request->user(), $swap);

        return back()->with('status', __('flash.standby_swap_cancelled'));
    }
}
