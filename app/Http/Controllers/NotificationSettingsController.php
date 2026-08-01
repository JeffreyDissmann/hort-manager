<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\NotificationCategory;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;
use Inertia\Response;

class NotificationSettingsController extends Controller
{
    /** The channels a preference can be toggled for. */
    private const CHANNELS = ['slack', 'push'];

    /** Show the per-category × per-channel notification matrix, scoped to what the user can receive. */
    public function edit(Request $request): Response
    {
        $user = $request->user();
        $categories = NotificationCategory::for($user);

        $preferences = [];
        foreach ($categories as $category) {
            foreach (self::CHANNELS as $channel) {
                $preferences[$category->value][$channel] = $user->wantsNotification($category->value, $channel);
            }
        }

        // Grouped by audience so a staff member who is also a guardian gets two sections.
        $sections = collect($categories)
            ->groupBy(fn (NotificationCategory $category): string => $category->audience()->value)
            ->map(fn ($group, string $audience): array => [
                'audience' => $audience,
                'categories' => $group->map(fn (NotificationCategory $c): string => $c->value)->values()->all(),
            ])
            ->values();

        return Inertia::render('Notifications/Edit', [
            'preferences' => $preferences,
            'categories' => collect($categories)->map(fn (NotificationCategory $c): string => $c->value)->all(),
            'sections' => $sections,
            'slackConnected' => filled($user->slack_id),
            // Shown next to the staff toggles, linking to where the timing is set.
            'lateChangeCutoff' => Setting::lateChangeCutoff(),
            'programReminderTime' => Setting::programReminderTime(),
        ]);
    }

    /** Persist the notification matrix. */
    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $allowed = array_map(
            fn (NotificationCategory $c): string => $c->value,
            NotificationCategory::for($user),
        );

        $rules = ['preferences' => ['required', 'array']];
        foreach ($allowed as $category) {
            foreach (self::CHANNELS as $channel) {
                $rules["preferences.{$category}.{$channel}"] = ['required', 'boolean'];
            }
        }

        $validator = Validator::make($request->all(), $rules);
        // Reject anything the enum doesn't know about — or that this user can't receive.
        $validator->after(function ($validator) use ($request, $allowed): void {
            foreach (array_keys((array) $request->input('preferences', [])) as $category) {
                if (! in_array($category, $allowed, true)) {
                    $validator->errors()->add("preferences.{$category}", 'Unbekannte Kategorie.');
                }
            }
        });

        $validated = $validator->validate();

        // Merge, don't replace: the page only renders the user's own categories, so a
        // plain assignment would wipe the toggles of the audience they're not shown.
        $user->notification_preferences = array_replace(
            (array) $user->notification_preferences,
            $validated['preferences'],
        );
        $user->save();

        return back()->with('status', __('notifications.saved'));
    }
}
