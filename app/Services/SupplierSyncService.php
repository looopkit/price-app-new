<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Supplier;
use Illuminate\Support\Facades\Log;

class SupplierSyncService
{
    public function __construct(
        private MoySkladService $moySkladService
    ) {}

    /**
     * Sync one supplier by external ID
     */
    public function syncOneByExternalId(Account $account, string $externalId, ?string $type = null): ?Supplier
    {
        $this->moySkladService->setAccessToken($account->access_token);

        $data = $this->moySkladService->getEntity('entity/counterparty', $externalId);

        if (!$data) {
            Log::warning('Supplier not found in MoySklad', [
                'account_id' => $account->id,
                'external_id' => $externalId,
            ]);
            return null;
        }

        return $this->createOrUpdateSupplier($account, $data);
    }

    /**
     * Create or update supplier from MoySklad data
     */
    public function createOrUpdateSupplier(Account $account, array $data): Supplier
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

        Log::info('Supplier synced', [
            'account_id' => $account->id,
            'supplier_id' => $supplier->id,
            'external_id' => $externalId,
        ]);

        return $supplier;
    }

    /**
     * Extract ID from MoySklad meta href
     */
    private function extractId(string $href): string
    {
        $parts = explode('/', $href);
        return end($parts);
    }
}