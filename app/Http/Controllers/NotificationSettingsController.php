<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    /** The channels a preference can be toggled for. */
    private const CHANNELS = ['slack', 'push'];

    /** Show the per-category × per-channel notification matrix. */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        $preferences = [];
        foreach (NotificationCategory::cases() as $category) {
            foreach (self::CHANNELS as $channel) {
                $preferences[$category->value][$channel] = $user->wantsNotification($category->value, $channel);
            }
        }

        return Inertia::render('Notifications/Edit', [
            'preferences' => $preferences,
            'categories' => NotificationCategory::values(),
            'slackConnected' => filled($user->slack_id),
        ]);
    }

    /** Persist the notification matrix. */
    public function update(Request $request): RedirectResponse
    {
        $rules = ['preferences' => ['required', 'array']];
        foreach (NotificationCategory::values() as $category) {
            foreach (self::CHANNELS as $channel) {
                $rules["preferences.{$category}.{$channel}"] = ['required', 'boolean'];
            }
        }

        $validator = Validator::make($request->all(), $rules);
        // Reject any category the enum doesn't know about.
        $validator->after(function ($validator) use ($request): void {
            $known = NotificationCategory::values();
            foreach (array_keys((array) $request->input('preferences', [])) as $category) {
                if (! in_array($category, $known, true)) {
                    $validator->errors()->add("preferences.{$category}", 'Unbekannte Kategorie.');
                }
            }
        });

        $validated = $validator->validate();

        $user = $request->user();
        $user->notification_preferences = $validated['preferences'];
        $user->save();

        return back()->with('status', __('notifications.saved'));
    }
}
