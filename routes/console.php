<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Each morning, remind guardians who still owe an excursion answer due today.
Schedule::command('excursions:remind-rsvps')->dailyAt('08:00');
