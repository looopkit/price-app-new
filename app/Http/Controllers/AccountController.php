<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\ImportOrUpdateDataService;
use App\Services\WebhookService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class AccountController extends Controller
{
    public function __construct(
        private ImportOrUpdateDataService $importService,
        private WebhookService $webhookService
    ) {}

    /**
     * Create new account
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'access_token' => 'required|string|unique:accounts,access_token',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $account = Account::create([
                'name' => $request->get('name'),
                'access_token' => $request->get('access_token'),
                'is_active' => true,
            ]);

            Log::info('Account created', [
                'account_id' => $account->id,
                'name' => $account->name,
            ]);

            // Start initial import
            $this->importService->importAllData($account);

            // Sync webhooks
            $this->webhookService->syncWebhooks($account);

            return response()->json([
                'id' => $account->id,
                'name' => $account->name,
                'is_active' => $account->is_active,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Account creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to create account',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update account
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $account = Account::find($id);

        if (!$account) {
            return response()->json([
                'error' => 'Account not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|string|max:255',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $wasActive = $account->is_active;

            $account->update($request->only(['name', 'is_active']));

            // Handle activation/deactivation
            if ($request->has('is_active')) {
                $isActive = $request->boolean('is_active');

                if ($isActive && !$wasActive) {
                    // Activating account - enable webhooks
                    $this->webhookService->enableWebhooks($account);
                    Log::info('Account activated', ['account_id' => $account->id]);
                } elseif (!$isActive && $wasActive) {
                    // Deactivating account - disable webhooks
                    $this->webhookService->disableWebhooks($account);
                    Log::info('Account deactivated', ['account_id' => $account->id]);
                }
            }

            return response()->json([
                'id' => $account->id,
                'name' => $account->name,
                'is_active' => $account->is_active,
            ]);
        } catch (\Exception $e) {
            Log::error('Account update failed', [
                'account_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'error' => 'Failed to update account',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get account
     */
    public function show(int $id): JsonResponse
    {
        $account = Account::with(['products', 'suppliers', 'webhooks'])->find($id);

        if (!$account) {
            return response()->json([
                'error' => 'Account not found',
            ], 404);
        }

        return response()->json([
            'id' => $account->id,
            'name' => $account->name,
            'is_active' => $account->is_active,
            'products_count' => $account->products->count(),
            'suppliers_count' => $account->suppliers->count(),
            'webhooks_count' => $account->webhooks->count(),
            'created_at' => $account->created_at,
            'updated_at' => $account->updated_at,
        ]);
    }

    /**
     * List accounts
     */
    public function index(): JsonResponse
    {
        $accounts = Account::withCount(['products', 'suppliers', 'webhooks'])->get();

        return response()->json([
            'accounts' => $accounts->map(fn($account) => [
                'id' => $account->id,
                'name' => $account->name,
                'is_active' => $account->is_active,
                'products_count' => $account->products_count,
                'suppliers_count' => $account->suppliers_count,
                'webhooks_count' => $account->webhooks_count,
            ]),
        ]);
    }

    /**
     * Trigger manual sync
     */
    public function sync(int $id): JsonResponse
    {
        $account = Account::find($id);

        if (!$account) {
            return response()->json([
                'error' => 'Account not found',
            ], 404);
        }

        if (!$account->isActive()) {
            return response()->json([
                'error' => 'Account is not active',
            ], 403);
        }

        try {
            $this->importService->importAllData($account);

            return response()->json([
                'message' => 'Sync started',
            ]);
        } catch (\Exception $e) {
            Log::error('Manual sync failed', [
                'account_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Sync failed',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}