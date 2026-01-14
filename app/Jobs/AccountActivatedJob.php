<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

use App\Services\ImportOrUpdateDataService;
use App\Services\WebhookService;
use App\Models\Account;

class AccountActivatedJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public readonly int $accountId
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        ImportOrUpdateDataService $importService,
        WebhookService $webhookService
    ): void {
        $account = Account::findOrFail($this->accountId);

        $importService->importSuppliers($account);
        $importService->importProducts($account);

        $webhookService->enableForAccount($account);
    }
}
