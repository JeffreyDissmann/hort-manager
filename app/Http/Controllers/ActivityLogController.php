<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

/** Admin-only audit trail of who did what (spatie/laravel-activitylog). */
class ActivityLogController extends Controller
{
    public function index(Request $request): Response
    {
        abort_unless((bool) $request->user()?->is_admin, 403);

        // id is the tie-breaker so same-second entries keep a stable, insertion order.
        $activities = Activity::with('causer')
            ->latest()
            ->latest('id')
            ->paginate(50)
            ->through(fn (Activity $activity): array => [
                'id' => $activity->id,
                'description' => $activity->description,
                'event' => $activity->event,
                'subject' => $activity->subject_type ? Str::snake(class_basename($activity->subject_type)) : null,
                'causer' => $activity->causer?->name,
                'changes' => $this->changes($activity),
                'at' => $activity->created_at?->timezone(config('app.timezone'))->format('d.m.Y H:i'),
            ]);

        return Inertia::render('ActivityLog/Index', [
            'activities' => $activities,
        ]);
    }

    /**
     * Compact list of changed fields: name, old value, new value.
     *
     * @return list<array{field: string, old: ?string, new: ?string}>
     */
    private function changes(Activity $activity): array
    {
        // v5 stores the before/after diff on `attribute_changes` (not `properties`).
        $new = (array) data_get($activity->attribute_changes, 'attributes', []);
        $old = (array) data_get($activity->attribute_changes, 'old', []);

        return collect(array_keys($new + $old))
            ->map(fn (string $key): array => [
                'field' => $key,
                'old' => $this->stringify($old[$key] ?? null),
                'new' => $this->stringify($new[$key] ?? null),
            ])
            ->all();
    }

    private function stringify(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            // Rendered in the viewing admin's locale (SetLocale middleware).
            return __($value ? 'activity.bool_true' : 'activity.bool_false');
        }

        return is_scalar($value) ? (string) $value : json_encode($value, JSON_UNESCAPED_UNICODE);
    }
}
