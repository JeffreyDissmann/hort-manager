<?php

declare(strict_types=1);

use App\Http\Controllers\AbsenceController;
use App\Http\Controllers\Accounting\AccountController;
use App\Http\Controllers\Accounting\BookingController;
use App\Http\Controllers\Accounting\CategoryController;
use App\Http\Controllers\Accounting\ImportController;
use App\Http\Controllers\Accounting\TransferController;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Auth\SlackController;
use App\Http\Controllers\ChildController;
use App\Http\Controllers\CompanionConfirmationController;
use App\Http\Controllers\DailyBoardController;
use App\Http\Controllers\DailyProgramController;
use App\Http\Controllers\ExcursionController;
use App\Http\Controllers\ExcursionRsvpController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NotificationSettingsController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SlackCommandController;
use App\Http\Controllers\SlackEventController;
use App\Http\Controllers\SlackInteractionController;
use App\Http\Controllers\StandardPlanController;
use App\Http\Controllers\SwitchRoleController;
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
Route::get('/help', fn () => Inertia::render('Help'))->name('help');

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

    Route::get('/notifications', [NotificationSettingsController::class, 'edit'])->name('notifications.edit');
    Route::patch('/notifications', [NotificationSettingsController::class, 'update'])->name('notifications.update');

    // Admin-only: switch your own role between staff / parent.
    Route::post('/role', [SwitchRoleController::class, 'update'])->name('role.update');

    Route::resource('children', ChildController::class)->except('show');

    // User management (admin only — the controller guards every action).
    Route::get('/users', [UserController::class, 'index'])->name('users.index');
    Route::post('/users/sync', [UserController::class, 'sync'])->name('users.sync');
    Route::patch('/users/{user}', [UserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');

    // Admin-only: the activity log / audit trail (the controller guards it).
    Route::get('/activity-log', [ActivityLogController::class, 'index'])->name('activity-log');

    Route::get('/weekly-plan', WeeklyOverviewController::class)->name('weekly-plan');
    Route::patch('/weekly-plan/adjust', [WeeklyAdjustmentController::class, 'update'])->name('weekly-plan.adjust');
    Route::patch('/weekly-plan/reset', [WeeklyAdjustmentController::class, 'reset'])->name('weekly-plan.reset');
    // The companion's parent (or staff) confirms another child going home with theirs.
    Route::patch('/companion/{departure}/confirm', [CompanionConfirmationController::class, 'update'])->name('companion.confirm');
    Route::get('/standard-plan', StandardPlanController::class)->name('standard-plan');

    // Krankmeldung / Abwesenheit — staff or the child's parent.
    Route::post('/absences', [AbsenceController::class, 'store'])->name('absences.store');
    Route::delete('/absences', [AbsenceController::class, 'destroy'])->name('absences.destroy');

    Route::get('/program', [DailyProgramController::class, 'index'])->name('program');
    Route::patch('/program', [DailyProgramController::class, 'update'])->name('program.update');
    Route::patch('/program/defaults', [DailyProgramController::class, 'updateDefaults'])->name('program.defaults');

    Route::get('/board', [DailyBoardController::class, 'index'])->name('board');
    Route::patch('/board/{departure}/status', [DailyBoardController::class, 'mark'])->name('board.mark');
    Route::patch('/board/{departure}/plan', [DailyBoardController::class, 'override'])->name('board.override');

    Route::resource('excursions', ExcursionController::class)->except('show');
    Route::patch('excursions/{excursion}/live', [ExcursionController::class, 'live'])->name('excursions.live');

    // Parent participation poll.
    Route::get('polls', [ExcursionRsvpController::class, 'index'])->name('polls.index');
    Route::patch('excursions/{excursion}/rsvp', [ExcursionRsvpController::class, 'update'])->name('polls.update');

    // Buchhaltung — admin-only accounting module.
    Route::middleware('admin')->prefix('accounting')->name('accounting.')->group(function () {
        Route::resource('accounts', AccountController::class)->except('show');
        Route::resource('categories', CategoryController::class)->only(['index', 'store', 'update', 'destroy']);
        // Step-through review of draft bookings (before the resource's {booking} routes).
        Route::get('bookings/review', [BookingController::class, 'review'])->name('bookings.review');
        Route::patch('bookings/{booking}/review', [BookingController::class, 'reviewSave'])->name('bookings.review-save');
        // Re-run the AI over all unconfirmed bookings.
        Route::post('bookings/reanalyse', [BookingController::class, 'reanalyse'])->name('bookings.reanalyse');
        Route::resource('bookings', BookingController::class)->except('show');

        Route::get('transfers/create', [TransferController::class, 'create'])->name('transfers.create');
        Route::post('transfers', [TransferController::class, 'store'])->name('transfers.store');

        Route::get('import', [ImportController::class, 'create'])->name('import.create');
        Route::post('import', [ImportController::class, 'store'])->name('import.store');
        Route::get('import/{import}', [ImportController::class, 'show'])->name('import.show');
    });
});

require __DIR__.'/auth.php';
