<?php

namespace App\Providers;

use App\Services\EntityResolveService;
use App\Services\ImportOrUpdateDataService;
use App\Services\MoySkladService;
use App\Services\ProductSyncService;
use App\Services\SupplierSyncService;
use App\Services\WebhookService;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register MoySklad service as singleton
        $this->app->singleton(MoySkladService::class);

        // Register sync services
        $this->app->bind(ProductSyncService::class, function ($app) {
            return new ProductSyncService(
                $app->make(MoySkladService::class)
            );
        });

        $this->app->bind(SupplierSyncService::class, function ($app) {
            return new SupplierSyncService(
                $app->make(MoySkladService::class)
            );
        });

        // Register entity resolve service
        $this->app->bind(EntityResolveService::class, function ($app) {
            return new EntityResolveService(
                $app->make(ProductSyncService::class),
                $app->make(SupplierSyncService::class)
            );
        });

        // Register import service
        $this->app->bind(ImportOrUpdateDataService::class, function ($app) {
            return new ImportOrUpdateDataService(
                $app->make(MoySkladService::class)
            );
        });

        // Register webhook service
        $this->app->bind(WebhookService::class, function ($app) {
            return new WebhookService(
                $app->make(MoySkladService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}