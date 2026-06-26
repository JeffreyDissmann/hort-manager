<?php

use App\Http\Controllers\ChildController;
use App\Http\Controllers\DailyBoardController;
use App\Http\Controllers\ExcursionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\WeeklyOverviewController;
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

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::resource('children', ChildController::class)->except('show');

    Route::get('/wochenplan', WeeklyOverviewController::class)->name('weekly-plan');

    Route::get('/tagesboard', [DailyBoardController::class, 'index'])->name('board');
    Route::patch('/tagesboard/{departure}/status', [DailyBoardController::class, 'mark'])->name('board.mark');
    Route::patch('/tagesboard/{departure}/plan', [DailyBoardController::class, 'override'])->name('board.override');

    Route::resource('ausfluege', ExcursionController::class)
        ->parameters(['ausfluege' => 'excursion'])
        ->names('excursions')
        ->except('show');
    Route::patch('ausfluege/{excursion}/live', [ExcursionController::class, 'live'])->name('excursions.live');
});

require __DIR__.'/auth.php';
