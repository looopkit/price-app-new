<?php

namespace App\Services;

use App\Jobs\SyncWebhooksJob;
use App\Models\Account;
use App\Models\Webhook;
use Illuminate\Support\Facades\Log;

class WebhookService
{
    private const WEBHOOK_ENTITIES = [
        'product' => ['CREATE', 'UPDATE', 'DELETE'],
        'variant' => ['CREATE', 'UPDATE', 'DELETE'],
        'counterparty' => ['CREATE', 'UPDATE', 'DELETE'],
    ];

    public function __construct(
        private MoySkladService $moySkladService
    ) {}

    /**
     * Sync all webhooks for account
     */
    public function syncWebhooks(Account $account): void
    {
        SyncWebhooksJob::dispatch($account->id);
    }

    /**
     * Create or update webhooks in MoySklad
     */
    public function createOrUpdateWebhooks(Account $account): void
    {
        $this->moySkladService->setAccessToken($account->access_token);

        $callbackUrl = route('webhooks.handle');

        foreach (self::WEBHOOK_ENTITIES as $entity => $actions) {
            foreach ($actions as $action) {
                $this->createOrUpdateWebhook($account, $entity, $action, $callbackUrl);
            }
        }

        Log::info('Webhooks synced', ['account_id' => $account->id]);
    }

    /**
     * Create or update single webhook
     */
    private function createOrUpdateWebhook(Account $account, string $entity, string $action, string $url): void
    {
        // Check if webhook already exists locally
        $webhook = Webhook::forAccount($account->id)
            ->byEntity($entity)
            ->byAction($action)
            ->first();

        $webhookData = [
            'url' => $url,
            'action' => $action,
            'entityType' => $entity,
            'enabled' => $account->isActive(),
        ];

        if ($webhook && $webhook->external_id) {
            // Update existing webhook
            $response = $this->moySkladService->updateWebhook($webhook->external_id, $webhookData);

            if ($response) {
                $webhook->update(['is_active' => $account->isActive()]);
                Log::info('Webhook updated', [
                    'account_id' => $account->id,
                    'webhook_id' => $webhook->id,
                    'entity' => $entity,
                    'action' => $action,
                ]);
            }
        } else {
            // Create new webhook
            $response = $this->moySkladService->createWebhook($webhookData);

            if ($response && isset($response['id'])) {
                Webhook::updateOrCreate(
                    [
                        'account_id' => $account->id,
                        'entity' => $entity,
                        'action' => $action,
                    ],
                    [
                        'external_id' => $response['id'],
                        'is_active' => $account->isActive(),
                    ]
                );

                Log::info('Webhook created', [
                    'account_id' => $account->id,
                    'entity' => $entity,
                    'action' => $action,
                    'external_id' => $response['id'],
                ]);
            }
        }
    }

    /**
     * Enable all webhooks for account
     */
    public function enableWebhooks(Account $account): void
    {
        $this->moySkladService->setAccessToken($account->access_token);

        $webhooks = Webhook::forAccount($account->id)->get();

        foreach ($webhooks as $webhook) {
            if (!$webhook->external_id) {
                continue;
            }

            $response = $this->moySkladService->updateWebhook($webhook->external_id, [
                'enabled' => true,
            ]);

            if ($response) {
                $webhook->update(['is_active' => true]);
            }
        }

        Log::info('Webhooks enabled', ['account_id' => $account->id]);
    }

    /**
     * Disable all webhooks for account
     */
    public function disableWebhooks(Account $account): void
    {
        $this->moySkladService->setAccessToken($account->access_token);

        $webhooks = Webhook::forAccount($account->id)->get();

        foreach ($webhooks as $webhook) {
            if (!$webhook->external_id) {
                continue;
            }

            $response = $this->moySkladService->updateWebhook($webhook->external_id, [
                'enabled' => false,
            ]);

            if ($response) {
                $webhook->update(['is_active' => false]);
            }
        }

        Log::info('Webhooks disabled', ['account_id' => $account->id]);
    }
}