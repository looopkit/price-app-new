<?php

namespace App\Jobs;

use App\Models\Account;
use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SupplierJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        private int $accountId,
        private array $supplierData
    ) {}

    public function handle(): void
    {
        $account = Account::find($this->accountId);

        if (!$account || !$account->isActive()) {
            Log::warning('Account not found or inactive', [
                'account_id' => $this->accountId,
            ]);
            return;
        }

        try {
            $this->processSupplier($account, $this->supplierData);
        } catch (\Exception $e) {
            Log::error('Supplier job failed', [
                'account_id' => $this->accountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function processSupplier(Account $account, array $data): void
    {
        $externalId = $this->extractId($data['meta']['href']);

        $supplier = Supplier::updateOrCreate(
            [
                'account_id' => $account->id,
                'external_id' => $externalId,
            ],
            [
                'name' => $data['name'] ?? '',
            ]
        );

        Log::info('Supplier processed', [
            'account_id' => $account->id,
            'supplier_id' => $supplier->id,
            'external_id' => $externalId,
        ]);
    }

    private function extractId(string $href): string
    {
        $parts = explode('/', $href);
        return end($parts);
    }
}