<?php

namespace App\Jobs;

use App\Models\Account;
use App\Models\OrderPosition;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ImportOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        private int $accountId,
        private string $orderType, // 'customerorder' or 'purchaseorder'
        private array $orderData
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
            $this->processOrder($account);

            Log::info('Order imported', [
                'account_id' => $account->id,
                'order_type' => $this->orderType,
            ]);
        } catch (\Exception $e) {
            Log::error('Order import failed', [
                'account_id' => $account->id,
                'order_type' => $this->orderType,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    private function processOrder(Account $account): void
    {
        $externalId = $this->extractId($this->orderData['meta']['href']);
        $positions = $this->orderData['positions']['rows'] ?? [];

        foreach ($positions as $positionData) {
            $this->processPosition($account, $externalId, $positionData);
        }
    }

    private function processPosition(Account $account, string $orderExternalId, array $positionData): void
    {
        $positionExternalId = $this->extractId($positionData['meta']['href']);
        $productExternalId = $this->extractId($positionData['assortment']['meta']['href']);
        $productType = $positionData['assortment']['meta']['type'];

        // Find or create product
        $product = Product::forAccount($account->id)
            ->byExternalId($productExternalId)
            ->first();

        if (!$product) {
            // Product should be synced already, but create minimal record if missing
            $product = Product::create([
                'account_id' => $account->id,
                'external_id' => $productExternalId,
                'type' => $this->normalizeProductType($productType),
                'name' => $positionData['assortment']['name'] ?? 'Unknown',
            ]);

            Log::warning('Product created from order position', [
                'product_id' => $product->id,
                'external_id' => $productExternalId,
            ]);
        }

        $quantity = ($positionData['quantity'] ?? 0) / 1000; // MoySklad uses units * 1000
        $price = ($positionData['price'] ?? 0) / 100; // MoySklad uses kopeks
        $total = ($quantity * $price);

        OrderPosition::updateOrCreate(
            [
                'account_id' => $account->id,
                'external_id' => $positionExternalId,
            ],
            [
                'type' => $this->orderType,
                'product_id' => $product->id,
                'total_quantity' => $quantity,
                'price' => $price,
                'total' => $total,
            ]
        );

        Log::info('Order position processed', [
            'account_id' => $account->id,
            'position_external_id' => $positionExternalId,
            'product_id' => $product->id,
            'quantity' => $quantity,
        ]);
    }

    private function extractId(string $href): string
    {
        $parts = explode('/', $href);
        return end($parts);
    }

    private function normalizeProductType(string $metaType): string
    {
        return match ($metaType) {
            'product' => 'product',
            'variant' => 'variant',
            'bundle' => 'bundle',
            'service' => 'service',
            default => 'product',
        };
    }
}