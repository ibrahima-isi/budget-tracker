<?php

use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\BudgetController;
use App\Http\Controllers\CurrencyController;
use App\Http\Controllers\LogoController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\CategorieController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DepenseController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RevenuController;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin'       => Route::has('login'),
        'canRegister'    => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion'     => PHP_VERSION,
    ]);
})->name('home');

// Serve the logo from private storage (not publicly accessible via /storage/)
Route::get('/logo', [LogoController::class, 'show'])->name('logo');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::resource('budgets',    BudgetController::class)->except(['create', 'edit']);
    Route::resource('depenses',   DepenseController::class)->except(['create', 'edit', 'show']);
    Route::resource('revenus',    RevenuController::class)->except(['create', 'edit', 'show']);
    Route::resource('categories', CategorieController::class)->except(['create', 'edit', 'show']);
    Route::post('categories/{category}/toggle-enabled', [CategorieController::class, 'toggleEnabled'])->name('categories.toggleEnabled');

    // Settings, Currencies & Activity Logs — admin only
    Route::middleware('admin')->group(function () {
        Route::get('activity-logs', [ActivityLogController::class, 'index'])->name('activity-logs.index');

        Route::get('settings',              [SettingsController::class, 'index'])->name('settings.index');
        Route::post('settings',             [SettingsController::class, 'update'])->name('settings.update');
        Route::delete('settings/logo',      [SettingsController::class, 'destroyLogo'])->name('settings.logo.destroy');

        Route::post('settings/currencies',                        [CurrencyController::class, 'store'])->name('currencies.store');
        Route::patch('settings/currencies/{currency}',            [CurrencyController::class, 'update'])->name('currencies.update');
        Route::patch('settings/currencies/{currency}/default',    [CurrencyController::class, 'setDefault'])->name('currencies.default');
        Route::patch('settings/currencies/{currency}/toggle',     [CurrencyController::class, 'toggle'])->name('currencies.toggle');
        Route::delete('settings/currencies/{currency}',           [CurrencyController::class, 'destroy'])->name('currencies.destroy');
    });
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
