<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Product;
use Illuminate\Support\Facades\Log;

class ProductSyncService
{
    public function __construct(
        private MoySkladService $moySkladService
    ) {}

    /**
     * Sync one product by external ID
     */
    public function syncOneByExternalId(Account $account, string $externalId, ?string $type = null): ?Product
    {
        $this->moySkladService->setAccessToken($account->access_token);

        // Determine endpoint based on type
        $endpoint = $this->getEndpointByType($type);
        
        $data = $this->moySkladService->getEntity($endpoint, $externalId);

        if (!$data) {
            Log::warning('Product not found in MoySklad', [
                'account_id' => $account->id,
                'external_id' => $externalId,
                'type' => $type,
            ]);
            return null;
        }

        return $this->createOrUpdateProduct($account, $data);
    }

    /**
     * Create or update product from MoySklad data
     */
    public function createOrUpdateProduct(Account $account, array $data): Product
    {
        $externalId = $this->extractId($data['meta']['href']);
        $type = $this->extractType($data['meta']['type']);

        // Handle parent product for variants
        $parentId = null;
        if ($type === 'variant' && isset($data['product'])) {
            $parentExternalId = $this->extractId($data['product']['meta']['href']);
            $parent = Product::forAccount($account->id)
                ->byExternalId($parentExternalId)
                ->first();

            // If parent doesn't exist, sync it first
            if (!$parent) {
                $parent = $this->syncOneByExternalId($account, $parentExternalId, 'product');
            }

            $parentId = $parent?->id;
        }

        $product = Product::updateOrCreate(
            [
                'account_id' => $account->id,
                'external_id' => $externalId,
            ],
            [
                'type' => $type,
                'name' => $data['name'] ?? '',
                'code' => $data['code'] ?? null,
                'article' => $data['article'] ?? null,
                'parent_id' => $parentId,
            ]
        );

        Log::info('Product synced', [
            'account_id' => $account->id,
            'product_id' => $product->id,
            'external_id' => $externalId,
            'type' => $type,
        ]);

        return $product;
    }

    /**
     * Extract ID from MoySklad meta href
     */
    private function extractId(string $href): string
    {
        $parts = explode('/', $href);
        return end($parts);
    }

    /**
     * Extract and normalize type
     */
    private function extractType(string $metaType): string
    {
        return match ($metaType) {
            'product' => 'product',
            'variant' => 'variant',
            'bundle' => 'bundle',
            'service' => 'service',
            default => 'product',
        };
    }

    /**
     * Get endpoint by type
     */
    private function getEndpointByType(?string $type): string
    {
        return match ($type) {
            'variant' => 'entity/variant',
            'bundle' => 'entity/bundle',
            'service' => 'entity/service',
            default => 'entity/product',
        };
    }
}