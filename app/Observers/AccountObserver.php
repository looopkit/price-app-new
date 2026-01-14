<?php

namespace App\Observers;

use App\Models\Account;
use App\Services\ImportOrUpdateDataService;
use App\Services\WebhookService;
use Illuminate\Support\Facades\Log;

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
        Log::info('Account created, starting initialization', [
            'account_id' => $account->id,
        ]);

        try {
            // Start initial import in background
            $this->importService->importAllData($account);

            // Setup webhooks
            $this->webhookService->syncWebhooks($account);
        } catch (\Exception $e) {
            Log::error('Account initialization failed', [
                'account_id' => $account->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Handle the Account "updated" event.
     */
    public function updated(Account $account): void
    {
        // Check if is_active was changed
        if ($account->wasChanged('is_active')) {
            $this->handleActiveStatusChange($account);
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

    /**
     * Handle account activation/deactivation
     */
    private function handleActiveStatusChange(Account $account): void
    {
        if ($account->is_active) {
            Log::info('Account activated, enabling webhooks', [
                'account_id' => $account->id,
            ]);

            try {
                $this->webhookService->enableWebhooks($account);
            } catch (\Exception $e) {
                Log::error('Failed to enable webhooks', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            Log::info('Account deactivated, disabling webhooks', [
                'account_id' => $account->id,
            ]);

            try {
                $this->webhookService->disableWebhooks($account);
            } catch (\Exception $e) {
                Log::error('Failed to disable webhooks', [
                    'account_id' => $account->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}