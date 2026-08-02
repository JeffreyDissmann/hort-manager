<?php

declare(strict_types=1);

use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Each morning, remind guardians who still owe an excursion answer due today.
Schedule::command('excursions:remind-rsvps')->dailyAt('08:00');

// …and those who still owe a Ferienbetreuung answer due today (same shape).
Schedule::command('care:remind-open')->dailyAt('08:05');

// Monday: the weekly overview (food, activities and each child's week) to parents, at
// the Hort-wide configured time — and a fixed lead earlier, a nudge to whoever still
// has to fill the week's Tagesprogramm in. Both read the setting on every schedule:run,
// so a changed time takes effect without a deploy.
Schedule::command('program:remind-missing')->weeklyOn(1, Setting::programReminderTime());
Schedule::command('weekly:digest')->weeklyOn(1, Setting::weeklyDigestTime());

// Nightly cleanup of data older than the retention period (DATA_RETENTION_WEEKS).
Schedule::command('hort:prune-old-data')->dailyAt('03:00');
