<?php

declare(strict_types=1);

use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\Auth\SlackController;
use App\Http\Controllers\ChildController;
use App\Http\Controllers\DailyBoardController;
use App\Http\Controllers\DailyProgramController;
use App\Http\Controllers\ExcursionController;
use App\Http\Controllers\ExcursionRsvpController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SlackCommandController;
use App\Http\Controllers\SlackEventController;
use App\Http\Controllers\SlackInteractionController;
use App\Http\Controllers\TrmnlDashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\WeeklyAdjustmentController;
use App\Http\Controllers\WeeklyOverviewController;
use App\Http\Middleware\VerifySlackSignature;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// Serve the PWA service worker from the site root so it controls the whole origin
// (scope "/") on any server — no Service-Worker-Allowed header needed.
Route::get('/sw.js', function () {
    $path = public_path('build/sw.js');
    abort_unless(file_exists($path), 404);

    return response()->file($path, [
        'Content-Type' => 'text/javascript',
        'Service-Worker-Allowed' => '/',
        'Cache-Control' => 'no-cache',
    ]);
})->name('sw');

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

// User-facing help/manual — reachable before login and from inside the app.
Route::get('/hilfe', fn () => Inertia::render('Help'))->name('help');

// Deep-link from a Slack message into the app, signing in via Slack if needed.
Route::get('/slack/enter', [SlackController::class, 'enter'])->name('slack.enter');

// Read-only JSON feed for the TRMNL staff-room display. No session — authenticated
// by a signed URL (hort:trmnl-url prints the link to paste into TRMNL's polling field).
Route::get('/trmnl/dashboard', TrmnlDashboardController::class)
    ->middleware('signed')
    ->name('trmnl.dashboard');

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
    // PWA web-push subscriptions for the signed-in user.
    Route::post('/push/subscribe', [PushSubscriptionController::class, 'store'])->name('push.subscribe');
    Route::delete('/push/subscribe', [PushSubscriptionController::class, 'destroy'])->name('push.unsubscribe');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/profile/language', [LocaleController::class, 'update'])->name('locale.update');

    Route::resource('children', ChildController::class)->except('show');

    // User management (admin only — the controller guards every action).
    Route::get('/benutzer', [UserController::class, 'index'])->name('users.index');
    Route::post('/benutzer/sync', [UserController::class, 'sync'])->name('users.sync');
    Route::patch('/benutzer/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/benutzer/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    Route::get('/wochenplan', WeeklyOverviewController::class)->name('weekly-plan');
    Route::patch('/wochenplan/anpassung', [WeeklyAdjustmentController::class, 'update'])->name('weekly-plan.adjust');
    Route::patch('/wochenplan/zuruecksetzen', [WeeklyAdjustmentController::class, 'reset'])->name('weekly-plan.reset');

    // Krankmeldung / Abwesenheit — staff or the child's parent.
    Route::post('/abwesenheiten', [AbsenceController::class, 'store'])->name('absences.store');
    Route::delete('/abwesenheiten', [AbsenceController::class, 'destroy'])->name('absences.destroy');

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
