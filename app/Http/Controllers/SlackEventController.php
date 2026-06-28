<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Jobs\PublishSlackHome;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class SlackEventController extends Controller
{
    /**
     * Slack Events API endpoint (signature-verified). Answers the one-time URL
     * verification challenge and (re)publishes the App Home tab when opened.
     */
    public function handle(Request $request): Response
    {
        if ($request->input('type') === 'url_verification') {
            return response($request->input('challenge'));
        }

        if ($request->input('event.type') === 'app_home_opened') {
            PublishSlackHome::dispatch($request->input('event.user'));
        }

        return response()->noContent();
    }
}
