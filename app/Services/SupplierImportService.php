<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Facades\Log;

class SupplierImportService
{
    public function __construct(
        private MoySkladService $moySkladService,
        private EntityResolveService $entityResolveService
    ) {}

    /**
     * Import offers from supplier's counterparty card in MoySklad
     * This reads products associated with the supplier and their prices
     */
    public function importOffersFromSupplier(Account $account, int $supplierId): array
    {
        $supplier = Supplier::forAccount($account->id)->find($supplierId);

        if (!$supplier) {
            throw new \Exception('Supplier not found');
        }

        $this->moySkladService->setAccessToken($account->access_token);

        Log::info('Importing offers from supplier', [
            'account_id' => $account->id,
            'supplier_id' => $supplier->id,
        ]);

        // Get supplier data from MoySklad
        $supplierData = $this->moySkladService->getEntity(
            'entity/counterparty',
            $supplier->external_id
        );

        if (!$supplierData) {
            throw new \Exception('Supplier not found in MoySklad');
        }

        $offersCreated = 0;
        $offersUpdated = 0;
        $errors = [];

        // Get products associated with this supplier
        // In MoySklad, this could be through:
        // 1. Supplier prices in product cards
        // 2. Purchase orders history
        // 3. Custom attributes

        // Method 1: Get all products and check for supplier prices
        $products = $this->getProductsWithSupplierPrices($account, $supplier);

        foreach ($products as $productData) {
            try {
                $result = $this->processSupplierProduct($account, $supplier, $productData);
                
                if ($result['created']) {
                    $offersCreated++;
                } else {
                    $offersUpdated++;
                }
            } catch (\Exception $e) {
                $errors[] = [
                    'product' => $productData['name'] ?? 'Unknown',
                    'error' => $e->getMessage(),
                ];

                Log::error('Failed to process supplier product', [
                    'supplier_id' => $supplier->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        Log::info('Supplier offers import completed', [
            'supplier_id' => $supplier->id,
            'created' => $offersCreated,
            'updated' => $offersUpdated,
            'errors' => count($errors),
        ]);

        return [
            'supplier_id' => $supplier->id,
            'supplier_name' => $supplier->name,
            'offers_created' => $offersCreated,
            'offers_updated' => $offersUpdated,
            'errors_count' => count($errors),
            'errors' => $errors,
        ];
    }

    /**
     * Get products with prices from specific supplier
     */
    private function getProductsWithSupplierPrices(Account $account, Supplier $supplier): array
    {
        // This would query MoySklad API for products with supplier prices
        // Implementation depends on how supplier prices are stored in MoySklad
        
        // For now, get recent purchase orders from this supplier
        $response = $this->moySkladService->getEntities(
            "entity/purchaseorder?filter=agent.id={$supplier->external_id}",
            100,
            0
        );

        $products = [];

        if ($response && isset($response['rows'])) {
            foreach ($response['rows'] as $order) {
                if (isset($order['positions']['meta']['href'])) {
                    // Get order positions
                    $positionsResponse = $this->moySkladService->client()
                        ->get($order['positions']['meta']['href'])
                        ->json();

                    if (isset($positionsResponse['rows'])) {
                        foreach ($positionsResponse['rows'] as $position) {
                            $products[] = $position;
                        }
                    }
                }
            }
        }

        return $products;
    }

    /**
     * Process single product from supplier
     */
    private function processSupplierProduct(Account $account, Supplier $supplier, array $productData): array
    {
        $productExternalId = $this->extractId($productData['assortment']['meta']['href']);
        $productType = $productData['assortment']['meta']['type'];

        // Resolve or create product
        $productResult = $this->entityResolveService->resolveOneOrCreate(
            $account,
            $productType,
            $productExternalId
        );

        if (!$productResult) {
            throw new \Exception("Failed to resolve product: {$productExternalId}");
        }

        $product = Product::find($productResult['id']);

        // Extract price and quantity
        $price = ($productData['price'] ?? 0) / 100; // MoySklad uses kopeks
        $stock = 0; // Stock from purchase orders doesn't represent available stock

        // Create or update offer
        $offer = Offer::updateOrCreate(
            [
                'account_id' => $account->id,
                'supplier_id' => $supplier->id,
                'product_id' => $product->id,
            ],
            [
                'price' => $price,
                'stock' => $stock,
                'priority' => 0, // Default priority
            ]
        );

        return [
            'offer_id' => $offer->id,
            'created' => $offer->wasRecentlyCreated,
        ];
    }

    private function extractId(string $href): string
    {
        $parts = explode('/', $href);
        return end($parts);
    }
}