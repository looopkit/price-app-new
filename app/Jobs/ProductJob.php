<?php

namespace App\Jobs;

use App\Models\Account;
use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        private int $accountId,
        private array $productData
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
            $this->processProduct($account, $this->productData);
        } catch (\Exception $e) {
            Log::error('Product job failed', [
                'account_id' => $this->accountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function processProduct(Account $account, array $data): void
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

        Log::info('Product processed', [
            'account_id' => $account->id,
            'product_id' => $product->id,
            'external_id' => $externalId,
            'type' => $type,
        ]);
    }

    private function extractId(string $href): string
    {
        $parts = explode('/', $href);
        return end($parts);
    }

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
}