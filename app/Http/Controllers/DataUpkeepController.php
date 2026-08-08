<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\HortStatistics;
use Illuminate\Http\Request;
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
    public function __invoke(Request $request): Response
    {
        abort_unless((bool) $request->user()?->isAdmin(), 403);

        return Inertia::render('DataUpkeep/Index', [
            'gaps' => HortStatistics::gaps(),
            'inventory' => HortStatistics::inventory(),
        ]);
    }
}
