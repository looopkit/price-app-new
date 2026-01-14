<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\EntityResolveController;
use App\Http\Controllers\FileImportController;
use App\Http\Controllers\OfferController;
use App\Http\Controllers\OrderPricingController;
use App\Http\Controllers\PricingMatrixController;
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

// Offers management
Route::prefix('offers')->group(function () {
    Route::post('/', [OfferController::class, 'store'])->name('offers.store');
    Route::put('/{id}', [OfferController::class, 'update'])->name('offers.update');
    Route::delete('/{id}', [OfferController::class, 'destroy'])->name('offers.destroy');
    Route::get('/product/{productId}', [OfferController::class, 'indexForProduct'])->name('offers.product');
});

// Pricing matrix
Route::prefix('pricing')->group(function () {
    Route::post('/matrix', [PricingMatrixController::class, 'matrix'])->name('pricing.matrix');
    Route::post('/best-offers', [PricingMatrixController::class, 'bestOffers'])->name('pricing.best-offers');
    Route::post('/compare-suppliers', [PricingMatrixController::class, 'compareSuppliers'])->name('pricing.compare');
});

// Order pricing
Route::prefix('orders')->group(function () {
    Route::post('/pricing-matrix', [OrderPricingController::class, 'matrix'])->name('orders.pricing-matrix');
    Route::post('/recommend-supplier', [OrderPricingController::class, 'recommend'])->name('orders.recommend');
    Route::post('/group-by-supplier', [OrderPricingController::class, 'groupBySupplier'])->name('orders.group');
});

// File imports
Route::prefix('imports')->group(function () {
    Route::post('/', [FileImportController::class, 'import'])->name('imports.upload');
    Route::get('/{fileId}/status', [FileImportController::class, 'status'])->name('imports.status');
    Route::get('/account/{accountId}', [FileImportController::class, 'index'])->name('imports.index');
});