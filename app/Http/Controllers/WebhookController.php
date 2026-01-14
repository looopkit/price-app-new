<?php

namespace App\Http\Controllers;

use App\Jobs\HandleWebhooksJob;
use App\Models\Account;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    /**
     * Handle incoming webhook from MoySklad
     */
    public function handle(Request $request): JsonResponse
    {
        $payload = $request->all();

        Log::info('Webhook received', [
            'payload' => $payload,
        ]);

        // Validate payload
        if (!$this->validatePayload($payload)) {
            Log::warning('Invalid webhook payload', [
                'payload' => $payload,
            ]);

            return response()->json([
                'error' => 'Invalid payload',
            ], 400);
        }

        // Extract account token from webhook URL or headers
        $accountId = $this->extractAccountId($payload);

        if (!$accountId) {
            Log::warning('Cannot determine account from webhook', [
                'payload' => $payload,
            ]);

            return response()->json([
                'error' => 'Account not found',
            ], 404);
        }

        // Verify account exists and is active
        $account = Account::find($accountId);

        if (!$account) {
            Log::error('Account not found', [
                'account_id' => $accountId,
            ]);

            // Return error so MoySklad disables webhook
            return response()->json([
                'error' => 'Account not found',
            ], 404);
        }

        if (!$account->isActive()) {
            Log::warning('Account is inactive', [
                'account_id' => $accountId,
            ]);

            // Return error so MoySklad disables webhook
            return response()->json([
                'error' => 'Account is inactive',
            ], 403);
        }

        // Dispatch job to process webhook
        HandleWebhooksJob::dispatch($accountId, $payload);

        Log::info('Webhook dispatched', [
            'account_id' => $accountId,
        ]);

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * Validate webhook payload
     */
    private function validatePayload(array $payload): bool
    {
        return isset($payload['action']) && 
               isset($payload['entityType']) &&
               isset($payload['events']) &&
               is_array($payload['events']);
    }

    /**
     * Extract account ID from webhook payload
     * 
     * In production, you might:
     * 1. Use different webhook URLs per account (e.g., /webhooks/{accountId})
     * 2. Use custom headers
     * 3. Store webhook-to-account mapping
     */
    private function extractAccountId(array $payload): ?int
    {
        // Method 1: Extract from audit context if available
        if (isset($payload['audit']['meta']['href'])) {
            // Parse account info from meta if available
        }

        // Method 2: Use URL parameter (requires route parameter)
        // This would be set in routes: /webhooks/{account}
        
        // Method 3: Query by webhook external ID
        if (isset($payload['meta']['id'])) {
            $webhook = \App\Models\Webhook::byExternalId($payload['meta']['id'])->first();
            return $webhook?->account_id;
        }

        // For demo purposes, you could also pass account_id in query string
        // or use a custom header
        
        return null;
    }
}