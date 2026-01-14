<?php

namespace App\Providers;

use App\Models\Account;
use App\Observers\AccountObserver;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    /**
     * The event listener mappings for the application.
     *
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        // Add your event listeners here
    ];

    /**
     * Register any events for your application.
     */
    public function boot(): void
    {
        Account::observe(AccountObserver::class);
    }

    /**
     * Determine if events and listeners should be automatically discovered.
     */
    public function shouldDiscoverEvents(): bool
    {
        return false;
    }
}