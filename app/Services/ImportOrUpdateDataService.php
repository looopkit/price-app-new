<?php

namespace App\Services;

use App\Jobs\ProductJob;
use App\Jobs\SupplierJob;
use App\Models\Account;
use Illuminate\Support\Facades\Log;

class ImportOrUpdateDataService
{
    private const BATCH_SIZE = 100;

    public function __construct(
        private MoySkladService $moySkladService
    ) {}

    /**
     * Import all products from MoySklad
     */
    public function importProducts(Account $account): void
    {
        $this->moySkladService->setAccessToken($account->access_token);

        Log::info('Starting products import', ['account_id' => $account->id]);

        $offset = 0;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->moySkladService->getEntities('entity/product', self::BATCH_SIZE, $offset);

            if (!$response || !isset($response['rows'])) {
                Log::error('Failed to fetch products', [
                    'account_id' => $account->id,
                    'offset' => $offset,
                ]);
                break;
            }

            $products = $response['rows'];

            foreach ($products as $productData) {
                ProductJob::dispatch($account->id, $productData);
            }

            $hasMore = count($products) === self::BATCH_SIZE;
            $offset += self::BATCH_SIZE;

            Log::info('Products batch dispatched', [
                'account_id' => $account->id,
                'offset' => $offset,
                'count' => count($products),
            ]);
        }

        // Import variants separately
        $this->importVariants($account);

        Log::info('Products import completed', ['account_id' => $account->id]);
    }

    /**
     * Import all variants from MoySklad
     */
    private function importVariants(Account $account): void
    {
        Log::info('Starting variants import', ['account_id' => $account->id]);

        $offset = 0;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->moySkladService->getEntities('entity/variant', self::BATCH_SIZE, $offset);

            if (!$response || !isset($response['rows'])) {
                Log::error('Failed to fetch variants', [
                    'account_id' => $account->id,
                    'offset' => $offset,
                ]);
                break;
            }

            $variants = $response['rows'];

            foreach ($variants as $variantData) {
                ProductJob::dispatch($account->id, $variantData);
            }

            $hasMore = count($variants) === self::BATCH_SIZE;
            $offset += self::BATCH_SIZE;

            Log::info('Variants batch dispatched', [
                'account_id' => $account->id,
                'offset' => $offset,
                'count' => count($variants),
            ]);
        }

        Log::info('Variants import completed', ['account_id' => $account->id]);
    }

    /**
     * Import all suppliers from MoySklad
     */
    public function importSuppliers(Account $account): void
    {
        $this->moySkladService->setAccessToken($account->access_token);

        Log::info('Starting suppliers import', ['account_id' => $account->id]);

        $offset = 0;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->moySkladService->getEntities('entity/counterparty', self::BATCH_SIZE, $offset);

            if (!$response || !isset($response['rows'])) {
                Log::error('Failed to fetch suppliers', [
                    'account_id' => $account->id,
                    'offset' => $offset,
                ]);
                break;
            }

            $suppliers = $response['rows'];

            foreach ($suppliers as $supplierData) {
                SupplierJob::dispatch($account->id, $supplierData);
            }

            $hasMore = count($suppliers) === self::BATCH_SIZE;
            $offset += self::BATCH_SIZE;

            Log::info('Suppliers batch dispatched', [
                'account_id' => $account->id,
                'offset' => $offset,
                'count' => count($suppliers),
            ]);
        }

        Log::info('Suppliers import completed', ['account_id' => $account->id]);
    }

    /**
     * Import all data for account
     */
    public function importAllData(Account $account): void
    {
        Log::info('Starting full data import', ['account_id' => $account->id]);

        $this->importSuppliers($account);
        $this->importProducts($account);

        Log::info('Full data import completed', ['account_id' => $account->id]);
    }
}