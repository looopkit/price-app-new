<?php

namespace App\Observers;

use App\Models\Account;
use App\Services\ImportOrUpdateDataService;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Log;

use App\Jobs\AccountActivatedJob;

class AccountObserver
{
    public function __construct(
        private ImportOrUpdateDataService $importService,
        private WebhookService $webhookService
    ) {}

    /**
     * Handle the Account "created" event.
     */
    public function created(Account $account): void
    {
        // 
    }

    public function saving(Account $account): void
    {
        $account->is_active = !is_null($account->access_token);
    }

    /**
     * Handle the Account "updated" event.
     */
    public function updated(Account $account): void
    {
        if (! $account->wasChanged('is_active')) {
            return;
        }

        if ($account->is_active) {
            AccountActivatedJob::dispatch($account->id);
        } else {
            //AccountDeactivatedJob::dispatch($account->id);
        }
    }

    /**
     * Handle the Account "deleted" event.
     */
    public function deleted(Account $account): void
    {
        Log::info('Account deleted', [
            'account_id' => $account->id,
        ]);

        // Webhooks will be deleted via cascade
        // Additional cleanup can be added here
    }
}