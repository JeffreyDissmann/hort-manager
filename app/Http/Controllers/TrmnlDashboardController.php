<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Services\HortDashboardData;
use Illuminate\Http\JsonResponse;

/**
 * JSON feed for the TRMNL staff-room display (polled on the device's refresh
 * schedule). Public but gated by a signed URL — see the `signed` middleware on
 * the route and the hort:trmnl-url command that prints the link to paste into TRMNL.
 */
class TrmnlDashboardController extends Controller
{
    public function __invoke(HortDashboardData $data): JsonResponse
    {
        return response()->json($data->build());
    }
}
