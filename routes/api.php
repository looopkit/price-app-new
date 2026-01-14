<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\EntityResolveController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Webhooks from MoySklad
Route::post('/webhooks', [WebhookController::class, 'handle'])
    ->name('webhooks.handle');

// Entity resolve from MoySklad extensions
Route::get('/resolve', [EntityResolveController::class, 'resolve'])
    ->name('entity.resolve');

// Account management
Route::prefix('accounts')->group(function () {
    Route::get('/', [AccountController::class, 'index'])->name('accounts.index');
    Route::post('/', [AccountController::class, 'store'])->name('accounts.store');
    Route::get('/{id}', [AccountController::class, 'show'])->name('accounts.show');
    Route::put('/{id}', [AccountController::class, 'update'])->name('accounts.update');
    Route::post('/{id}/sync', [AccountController::class, 'sync'])->name('accounts.sync');
});