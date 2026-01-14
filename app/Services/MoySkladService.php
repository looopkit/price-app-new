<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MoySkladService
{
    private const BASE_URL = 'https://api.moysklad.ru/api/remap/1.2';
    
    private ?string $accessToken = null;

    public function setAccessToken(string $accessToken): self
    {
        $this->accessToken = $accessToken;
        return $this;
    }

    public function client(): PendingRequest
    {
        if (!$this->accessToken) {
            throw new \RuntimeException('Access token is not set');
        }

        return Http::baseUrl(self::BASE_URL)
            ->withHeaders([
                'Authorization' => 'Bearer ' . $this->accessToken,
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
            ])
            ->timeout(30)
            ->retry(3, 1000);
    }

    /**
     * Extract UUID from MoySklad URL
     * Example: https://online.moysklad.ru/app/#feature/edit?id=UUID
     */
    public function extractIdFromUrl(string $url): ?string
    {
        if (preg_match('/[?&]id=([a-f0-9\-]{36})/i', $url, $matches)) {
            return $matches[1];
        }

        // Direct UUID check
        if (preg_match('/^[a-f0-9\-]{36}$/i', $url)) {
            return $url;
        }

        Log::warning('Cannot extract ID from URL', ['url' => $url]);
        return null;
    }

    /**
     * Get entity by ID
     */
    public function getEntity(string $endpoint, string $id): ?array
    {
        try {
            $response = $this->client()->get("{$endpoint}/{$id}");

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('MoySklad API error', [
                'endpoint' => $endpoint,
                'id' => $id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('MoySklad API exception', [
                'endpoint' => $endpoint,
                'id' => $id,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Get entities list with pagination
     */
    public function getEntities(string $endpoint, int $limit = 100, int $offset = 0): ?array
    {
        try {
            $response = $this->client()->get($endpoint, [
                'limit' => $limit,
                'offset' => $offset,
            ]);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('MoySklad API error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('MoySklad API exception', [
                'endpoint' => $endpoint,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Create or update webhook
     */
    public function createWebhook(array $data): ?array
    {
        try {
            $response = $this->client()->post('entity/webhook', $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('MoySklad webhook creation error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('MoySklad webhook creation exception', [
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Update webhook
     */
    public function updateWebhook(string $webhookId, array $data): ?array
    {
        try {
            $response = $this->client()->put("entity/webhook/{$webhookId}", $data);

            if ($response->successful()) {
                return $response->json();
            }

            Log::error('MoySklad webhook update error', [
                'webhookId' => $webhookId,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return null;
        } catch (\Exception $e) {
            Log::error('MoySklad webhook update exception', [
                'webhookId' => $webhookId,
                'error' => $e->getMessage(),
            ]);

            return null;
        }
    }

    /**
     * Delete webhook
     */
    public function deleteWebhook(string $webhookId): bool
    {
        try {
            $response = $this->client()->delete("entity/webhook/{$webhookId}");

            return $response->successful();
        } catch (\Exception $e) {
            Log::error('MoySklad webhook deletion exception', [
                'webhookId' => $webhookId,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Get all webhooks
     */
    public function getWebhooks(): ?array
    {
        return $this->getEntities('entity/webhook', 100);
    }
}