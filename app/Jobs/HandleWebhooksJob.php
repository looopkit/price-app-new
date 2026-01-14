<?php

namespace App\Jobs;

use App\Models\Account;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class HandleWebhooksJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $timeout = 120;

    public function __construct(
        private int $accountId,
        private array $payload
    ) {}

    public function handle(): void
    {
        $account = Account::find($this->accountId);

        if (!$account || !$account->isActive()) {
            Log::warning('Account not found or inactive for webhook', [
                'account_id' => $this->accountId,
            ]);
            return;
        }

        try {
            $this->processWebhook($account, $this->payload);
        } catch (\Exception $e) {
            Log::error('Webhook handling failed', [
                'account_id' => $this->accountId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    private function processWebhook(Account $account, array $payload): void
    {
        $action = $payload['action'] ?? null;
        $entityType = $payload['entityType'] ?? null;

        if (!$action || !$entityType) {
            Log::warning('Invalid webhook payload', [
                'account_id' => $account->id,
                'payload' => $payload,
            ]);
            return;
        }

        match ($action) {
            'CREATE', 'UPDATE' => $this->handleCreateOrUpdate($account, $entityType, $payload),
            'DELETE' => $this->handleDelete($account, $entityType, $payload),
            default => Log::warning('Unknown webhook action', [
                'account_id' => $account->id,
                'action' => $action,
            ]),
        };
    }

    private function handleCreateOrUpdate(Account $account, string $entityType, array $payload): void
    {
        $events = $payload['events'] ?? [];

        foreach ($events as $event) {
            $meta = $event['meta'] ?? null;

            if (!$meta || !isset($meta['href'])) {
                continue;
            }

            $externalId = $this->extractId($meta['href']);

            // Dispatch appropriate job based on entity type
            match ($entityType) {
                'product', 'variant' => $this->fetchAndDispatchProduct($account, $externalId),
                'counterparty' => $this->fetchAndDispatchSupplier($account, $externalId),
                default => Log::warning('Unknown entity type', [
                    'account_id' => $account->id,
                    'entity_type' => $entityType,
                ]),
            };
        }
    }

    private function handleDelete(Account $account, string $entityType, array $payload): void
    {
        $events = $payload['events'] ?? [];

        foreach ($events as $event) {
            $meta = $event['meta'] ?? null;

            if (!$meta || !isset($meta['href'])) {
                continue;
            }

            $externalId = $this->extractId($meta['href']);

            // Delete local entity
            match ($entityType) {
                'product', 'variant' => Product::forAccount($account->id)
                    ->byExternalId($externalId)
                    ->delete(),
                'counterparty' => Supplier::forAccount($account->id)
                    ->byExternalId($externalId)
                    ->delete(),
                default => null,
            };

            Log::info('Entity deleted', [
                'account_id' => $account->id,
                'entity_type' => $entityType,
                'external_id' => $externalId,
            ]);
        }
    }

    private function fetchAndDispatchProduct(Account $account, string $externalId): void
    {
        // Here we would fetch full entity data from MoySklad
        // For now, dispatch job with minimal data
        ProductJob::dispatch($account->id, [
            'meta' => [
                'href' => "https://api.moysklad.ru/api/remap/1.2/entity/product/{$externalId}",
                'type' => 'product',
            ],
        ]);
    }

    private function fetchAndDispatchSupplier(Account $account, string $externalId): void
    {
        SupplierJob::dispatch($account->id, [
            'meta' => [
                'href' => "https://api.moysklad.ru/api/remap/1.2/entity/counterparty/{$externalId}",
            ],
        ]);
    }

    private function extractId(string $href): string
    {
        $parts = explode('/', $href);
        return end($parts);
    }
}