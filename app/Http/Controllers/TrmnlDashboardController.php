<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\HortDashboardData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * JSON feed for the TRMNL staff-room display (polled on the device's refresh
 * schedule). Public but gated twice: the `signed` middleware on the route, plus the
 * rotatable token below. The feed carries the whole day — every child's name, pickup
 * time and absence — and the URL lives in TRMNL's plugin config, so it needs to be
 * revocable without rotating APP_KEY (which would invalidate every other signed URL).
 * `hort:trmnl-url --rotate` issues a new link and kills the old one.
 */
class TrmnlDashboardController extends Controller
{
    public function __invoke(Request $request, HortDashboardData $data): JsonResponse
    {
        abort_unless(
            hash_equals(Setting::trmnlToken(), (string) $request->query('token')),
            403,
        );

        return response()->json($data->build());
    }
}
