<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Support\HortStatistics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * „Datenpflege" — the gaps in the Hort's own data that someone can go and close: a
 * child with no Stammplan, one nobody is linked to, a trip nobody answered. Its own
 * page rather than a card on „Statistik", because this one asks for work while the
 * charts only describe: mixing the two makes it easy to scroll past the work.
 */
class DataUpkeepController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->isAdmin(), 403);

        return Inertia::render('DataUpkeep/Index', [
            'gaps' => HortStatistics::gaps(),
            'inventory' => HortStatistics::inventory(),
            'retentionMonths' => Setting::retentionMonths(),
            'retentionOptions' => Setting::RetentionOptions,
            'retentionCutoff' => Setting::retentionCutoff()?->toDateString(),
        ]);
    }

    /** Change how long the Hort keeps its day-to-day records. */
    public function update(Request $request): RedirectResponse
    {
        abort_unless((bool) $request->user()?->isAdmin(), 403);

        $validated = $request->validate([
            'retention_months' => ['required', 'integer', Rule::in(Setting::RetentionOptions)],
        ]);

        Setting::set(Setting::RetentionMonths, $validated['retention_months']);

        // Not pruned here: the nightly command does that, so shortening the period by
        // accident doesn't delete two years of records on the way back from a mis-click.
        return back()->with('status', __('flash.retention_saved'));
    }
}
