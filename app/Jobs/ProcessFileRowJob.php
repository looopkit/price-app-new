<?php

namespace App\Jobs;

use App\Models\FileRow;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\EntityResolveService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessFileRowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        private int $fileRowId
    ) {}

    public function handle(EntityResolveService $resolveService): void
    {
        $row = FileRow::find($this->fileRowId);

        if (!$row) {
            Log::warning('FileRow not found', ['file_row_id' => $this->fileRowId]);
            return;
        }

        $file = $row->file;
        $account = $file->account;

        if (!$account || !$account->isActive()) {
            $row->markAsFailed('Account not found or inactive');
            return;
        }

        try {
            $this->processRow($row, $account, $resolveService);

            $row->markAsProcessed();
            $file->incrementProcessedRows();

            Log::info('FileRow processed', [
                'file_row_id' => $row->id,
                'file_id' => $file->id,
            ]);
        } catch (\Exception $e) {
            $row->markAsFailed($e->getMessage());

            Log::error('FileRow processing failed', [
                'file_row_id' => $row->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function processRow(FileRow $row, $account, EntityResolveService $resolveService): void
    {
        $data = $row->data;

        // Extract data from row
        $supplierExternalId = $data['supplier_external_id'] ?? null;
        $productExternalId = $data['product_external_id'] ?? null;
        $price = $data['price'] ?? null;
        $stock = $data['stock'] ?? 0;
        $priority = $data['priority'] ?? 0;

        if (!$supplierExternalId || !$productExternalId || !$price) {
            throw new \Exception('Missing required fields: supplier, product, or price');
        }

        // Resolve supplier
        $supplierResult = $resolveService->resolveOneOrCreate($account, 'counterparty', $supplierExternalId);
        if (!$supplierResult) {
            throw new \Exception("Supplier not found: {$supplierExternalId}");
        }

        $supplier = Supplier::find($supplierResult['id']);

        // Resolve product
        $productResult = $resolveService->resolveOneOrCreate($account, 'product', $productExternalId);
        if (!$productResult) {
            throw new \Exception("Product not found: {$productExternalId}");
        }

        $product = Product::find($productResult['id']);

        // Create or update offer
        Offer::updateOrCreate(
            [
                'account_id' => $account->id,
                'supplier_id' => $supplier->id,
                'product_id' => $product->id,
            ],
            [
                'price' => $price,
                'stock' => $stock,
                'priority' => $priority,
            ]
        );

        Log::info('Offer created/updated from file', [
            'account_id' => $account->id,
            'supplier_id' => $supplier->id,
            'product_id' => $product->id,
            'price' => $price,
        ]);
    }
}