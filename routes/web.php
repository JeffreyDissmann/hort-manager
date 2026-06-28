<?php

declare(strict_types=1);

use App\Http\Controllers\Auth\SlackController;
use App\Http\Controllers\ChildController;
use App\Http\Controllers\DailyBoardController;
use App\Http\Controllers\DailyProgramController;
use App\Http\Controllers\ExcursionController;
use App\Http\Controllers\ExcursionRsvpController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SlackCommandController;
use App\Http\Controllers\SlackEventController;
use App\Http\Controllers\SlackInteractionController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeeklyAdjustmentController;
use App\Http\Controllers\WeeklyOverviewController;
use App\Http\Middleware\VerifySlackSignature;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::get('/dashboard', function () {
    return Inertia::render('Dashboard');
})->middleware(['auth', 'verified'])->name('dashboard');

// "Sign in with Slack" SSO (guest-accessible — this is how parents log in).
Route::get('/auth/slack/redirect', [SlackController::class, 'redirect'])->name('slack.redirect');
Route::get('/auth/slack/callback', [SlackController::class, 'callback'])->name('slack.callback');

// Deep-link from a Slack message into the app, signing in via Slack if needed.
Route::get('/slack/enter', [SlackController::class, 'enter'])->name('slack.enter');

// Slack interactive buttons + slash command; authenticated by signature, not a session.
Route::post('/slack/interactions', [SlackInteractionController::class, 'handle'])
    ->middleware(VerifySlackSignature::class)
    ->name('slack.interactions');
Route::post('/slack/commands', [SlackCommandController::class, 'handle'])
    ->middleware(VerifySlackSignature::class)
    ->name('slack.commands');
Route::post('/slack/events', [SlackEventController::class, 'handle'])
    ->middleware(VerifySlackSignature::class)
    ->name('slack.events');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('children', ChildController::class)->except('show');

    // User management (admin only — the controller guards every action).
    Route::get('/benutzer', [UserController::class, 'index'])->name('users.index');
    Route::post('/benutzer/sync', [UserController::class, 'sync'])->name('users.sync');
    Route::patch('/benutzer/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/benutzer/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/wochenplan', WeeklyOverviewController::class)->name('weekly-plan');
    Route::patch('/wochenplan/anpassung', [WeeklyAdjustmentController::class, 'update'])->name('weekly-plan.adjust');
    Route::patch('/wochenplan/zuruecksetzen', [WeeklyAdjustmentController::class, 'reset'])->name('weekly-plan.reset');

    Route::get('/programm', [DailyProgramController::class, 'index'])->name('program');
    Route::patch('/programm', [DailyProgramController::class, 'update'])->name('program.update');
    Route::patch('/programm/standard', [DailyProgramController::class, 'updateDefaults'])->name('program.defaults');

    Route::get('/tagesboard', [DailyBoardController::class, 'index'])->name('board');
    Route::patch('/tagesboard/{departure}/status', [DailyBoardController::class, 'mark'])->name('board.mark');
    Route::patch('/tagesboard/{departure}/plan', [DailyBoardController::class, 'override'])->name('board.override');

    Route::resource('ausfluege', ExcursionController::class)
        ->parameters(['ausfluege' => 'excursion'])
        ->names('excursions')
        ->except('show');
    Route::patch('ausfluege/{excursion}/live', [ExcursionController::class, 'live'])->name('excursions.live');

    // Parent participation poll.
    Route::get('umfragen', [ExcursionRsvpController::class, 'index'])->name('polls.index');
    Route::patch('ausfluege/{excursion}/rsvp', [ExcursionRsvpController::class, 'update'])->name('polls.update');
});

require __DIR__.'/auth.php';
