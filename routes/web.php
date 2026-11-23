<?php

use App\Http\Controllers\Admin\SpendReportController;
use App\Http\Controllers\CreditPurchaseController;
use App\Http\Controllers\GenerationController;
use App\Http\Controllers\PixelforgeWebhookController;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Route;

Route::inertia('/', 'Welcome')->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', [GenerationController::class, 'index'])->name('dashboard');
    Route::post('generations/estimate', [GenerationController::class, 'estimate'])->name('generations.estimate');
    Route::post('generations', [GenerationController::class, 'store'])
        ->middleware('throttle:20,1')
        ->name('generations.store');
    Route::post('credits', [CreditPurchaseController::class, 'store'])->name('credits.store');

    Route::get('admin/report', SpendReportController::class)
        ->middleware('can:view-spend-report')
        ->name('admin.report');
});

// Pixelforge has no CSRF token; the request is authenticated by its signature instead.
Route::post('webhooks/pixelforge', PixelforgeWebhookController::class)
    ->withoutMiddleware(ValidateCsrfToken::class)
    ->name('webhooks.pixelforge');

require __DIR__.'/settings.php';
