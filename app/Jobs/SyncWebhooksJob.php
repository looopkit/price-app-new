<?php

namespace App\Jobs;

use App\Models\Account;
use App\Services\WebhookService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SyncWebhooksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 300;

    public function __construct(
        private int $accountId
    ) {}

    public function handle(WebhookService $webhookService): void
    {
        $account = Account::find($this->accountId);

        if (!$account) {
            Log::warning('Account not found for webhook sync', [
                'account_id' => $this->accountId,
            ]);
            return;
        }

        try {
            $webhookService->createOrUpdateWebhooks($account);

            Log::info('Webhooks sync job completed', [
                'account_id' => $this->accountId,
            ]);
        } catch (\Exception $e) {
            Log::error('Webhooks sync job failed', [
                'account_id' => $this->accountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}