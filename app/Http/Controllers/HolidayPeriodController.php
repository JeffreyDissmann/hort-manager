<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\HolidayPeriod;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/** Staff manage the Hort's Schließzeiten; everyone can see them. */
class HolidayPeriodController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', HolidayPeriod::class);

        $today = Carbon::today()->toDateString();

        $periods = HolidayPeriod::query()
            ->closed()
            ->orderBy('starts_on')
            ->get()
            ->map(fn (HolidayPeriod $period): array => [
                'id' => $period->id,
                'name' => $period->name,
                'starts_on' => $period->starts_on->toDateString(),
                'ends_on' => $period->ends_on->toDateString(),
                'note' => $period->note,
                'days' => $period->dayCount(),
            ]);

        return Inertia::render('Closures/Index', [
            // A period counts as over only once its last day has passed.
            'upcoming' => $periods->filter(fn (array $p): bool => $p['ends_on'] >= $today)->values(),
            'past' => $periods->filter(fn (array $p): bool => $p['ends_on'] < $today)
                ->sortByDesc('starts_on')->values(),
            'canManage' => $request->user()->can('create', HolidayPeriod::class),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->authorize('create', HolidayPeriod::class);

        HolidayPeriod::create($this->validated($request));

        return back()->with('status', __('flash.closure_saved'));
    }

    public function update(Request $request, HolidayPeriod $closure): RedirectResponse
    {
        $this->authorize('update', $closure);

        $closure->update($this->validated($request));

        return back()->with('status', __('flash.closure_saved'));
    }

    public function destroy(Request $request, HolidayPeriod $closure): RedirectResponse
    {
        $this->authorize('delete', $closure);

        $closure->delete();

        return back()->with('status', __('flash.closure_deleted'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'starts_on' => ['required', 'date'],
            // A single closed day is starts_on == ends_on, so equality is allowed.
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
    }
}
