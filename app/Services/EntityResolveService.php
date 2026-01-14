<?php

namespace App\Services;

use App\Models\Account;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Log;

class EntityResolveService
{
    public function __construct(
        private ProductSyncService $productSyncService,
        private SupplierSyncService $supplierSyncService
    ) {}

    /**
     * Resolve one entity or create if not exists
     * Returns internal ID and redirect URL
     */
    public function resolveOneOrCreate(Account $account, string $type, string $externalId): ?array
    {
        $entity = $this->findLocalEntity($account, $type, $externalId);

        // If entity doesn't exist locally, sync it from MoySklad
        if (!$entity) {
            $entity = $this->syncEntity($account, $type, $externalId);
        }

        if (!$entity) {
            Log::warning('Entity not found and cannot be synced', [
                'account_id' => $account->id,
                'type' => $type,
                'external_id' => $externalId,
            ]);
            return null;
        }

        return [
            'id' => $entity->id,
            'url' => $this->generateRedirectUrl($type, $entity->id),
        ];
    }

    /**
     * Resolve many entities or create if not exist
     */
    public function resolveManyOrCreate(Account $account, string $type, array $externalIds): array
    {
        $results = [];

        foreach ($externalIds as $externalId) {
            $result = $this->resolveOneOrCreate($account, $type, $externalId);
            if ($result) {
                $results[] = $result;
            }
        }

        return $results;
    }

    /**
     * Find entity locally
     */
    private function findLocalEntity(Account $account, string $type, string $externalId): ?Model
    {
        return match ($type) {
            'product', 'variant', 'bundle', 'service' => $account->products()
                ->byExternalId($externalId)
                ->first(),
            'counterparty' => $account->suppliers()
                ->byExternalId($externalId)
                ->first(),
            default => null,
        };
    }

    /**
     * Sync entity from MoySklad
     */
    private function syncEntity(Account $account, string $type, string $externalId): ?Model
    {
        try {
            return match ($type) {
                'product', 'variant', 'bundle', 'service' => 
                    $this->productSyncService->syncOneByExternalId($account, $externalId, $type),
                'counterparty' => 
                    $this->supplierSyncService->syncOneByExternalId($account, $externalId),
                default => null,
            };
        } catch (\Exception $e) {
            Log::error('Entity sync failed', [
                'account_id' => $account->id,
                'type' => $type,
                'external_id' => $externalId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Generate redirect URL for entity
     */
    private function generateRedirectUrl(string $type, int $id): string
    {
        $route = match ($type) {
            'product', 'variant', 'bundle', 'service' => 'products.show',
            'counterparty' => 'suppliers.show',
            default => 'home',
        };

        return route($route, ['id' => $id]);
    }
}