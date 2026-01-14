<?php

namespace App\Jobs;

use App\Models\Account;
use App\Models\Product;
use App\Services\MoySkladService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class UpdatePriceInMoySkladJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        private int $accountId,
        private int $productId,
        private float $newPrice,
        private ?string $priceType = 'salePrices' // or 'buyPrice'
    ) {}

    public function handle(MoySkladService $moySkladService): void
    {
        $account = Account::find($this->accountId);

        if (!$account || !$account->isActive()) {
            Log::warning('Account not found or inactive', [
                'account_id' => $this->accountId,
            ]);
            return;
        }

        $product = Product::find($this->productId);

        if (!$product) {
            Log::warning('Product not found', [
                'product_id' => $this->productId,
            ]);
            return;
        }

        try {
            $this->updatePrice($moySkladService, $account, $product);

            Log::info('Price updated in MoySklad', [
                'account_id' => $account->id,
                'product_id' => $product->id,
                'new_price' => $this->newPrice,
            ]);
        } catch (\Exception $e) {
            Log::error('Price update failed', [
                'account_id' => $account->id,
                'product_id' => $product->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function updatePrice(MoySkladService $moySkladService, Account $account, Product $product): void
    {
        $moySkladService->setAccessToken($account->access_token);

        $endpoint = $this->getEndpoint($product->type);
        $externalId = $product->external_id;

        // Get current product data
        $currentData = $moySkladService->getEntity($endpoint, $externalId);

        if (!$currentData) {
            throw new \Exception('Product not found in MoySklad');
        }

        // Prepare price update
        $priceData = $this->preparePriceData($currentData);

        // Update price via API
        $response = $moySkladService->client()->put("{$endpoint}/{$externalId}", $priceData);

        if (!$response->successful()) {
            throw new \Exception('Failed to update price in MoySklad: ' . $response->body());
        }

        Log::info('Price updated successfully', [
            'product_id' => $product->id,
            'external_id' => $externalId,
        ]);
    }

    private function getEndpoint(string $type): string
    {
        return match ($type) {
            'variant' => 'entity/variant',
            'bundle' => 'entity/bundle',
            'service' => 'entity/service',
            default => 'entity/product',
        };
    }

    private function preparePriceData(array $currentData): array
    {
        $priceInKopeks = $this->newPrice * 100; // MoySklad uses kopeks

        if ($this->priceType === 'salePrices') {
            // Update sale prices
            $salePrices = $currentData['salePrices'] ?? [];
            
            if (empty($salePrices)) {
                $salePrices = [[
                    'value' => $priceInKopeks,
                    'currency' => [
                        'meta' => [
                            'href' => 'https://api.moysklad.ru/api/remap/1.2/entity/currency/default',
                            'type' => 'currency',
                        ]
                    ]
                ]];
            } else {
                $salePrices[0]['value'] = $priceInKopeks;
            }

            return ['salePrices' => $salePrices];
        }

        // Update buy price
        return [
            'buyPrice' => [
                'value' => $priceInKopeks,
            ]
        ];
    }
}