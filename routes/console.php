<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Each morning, remind guardians who still owe an excursion answer due today.
Schedule::command('excursions:remind-rsvps')->dailyAt('08:00');

// Monday midday: the weekly overview (food, activities and each child's week) to parents.
Schedule::command('weekly:digest')->weeklyOn(1, '12:00');

// Nightly cleanup of data older than the retention period (DATA_RETENTION_WEEKS).
Schedule::command('hort:prune-old-data')->dailyAt('03:00');
