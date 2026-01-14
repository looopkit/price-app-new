<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\EntityResolveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class EntityResolveController extends Controller
{
    public function __construct(
        private EntityResolveService $entityResolveService
    ) {}

    /**
     * Resolve entity from MoySklad extension
     * 
     * Query parameters:
     * - name (required): account name (unique identifier)
     * - id + type: single entity resolution
     * - json: multiple entities resolution
     */
    public function resolve(Request $request): JsonResponse
    {
        // Validate name
        $validator = Validator::make($request->all(), [
            'name' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Account name is required',
                'details' => $validator->errors(),
            ], 400);
        }

        // Find account by name
        $account = Account::byName($request->get('name'))
            ->active()
            ->first();

        if (!$account) {
            Log::warning('Account not found or inactive', [
                'name' => $request->get('name'),
            ]);

            return response()->json([
                'error' => 'Account not found or inactive',
            ], 404);
        }

        // Single entity resolution
        if ($request->has('id') && $request->has('type')) {
            return $this->resolveSingle($account, $request);
        }

        // Multiple entities resolution
        if ($request->has('json')) {
            return $this->resolveMultiple($account, $request);
        }

        return response()->json([
            'error' => 'Invalid request parameters',
        ], 400);
    }

    /**
     * Resolve single entity
     */
    private function resolveSingle(Account $account, Request $request): JsonResponse
    {
        $externalId = $request->get('id');
        $type = $request->get('type');

        Log::info('Resolving single entity', [
            'account_id' => $account->id,
            'external_id' => $externalId,
            'type' => $type,
        ]);

        $result = $this->entityResolveService->resolveOneOrCreate($account, $type, $externalId);

        if (!$result) {
            return response()->json([
                'error' => 'Entity not found',
            ], 404);
        }

        return response()->json([
            'id' => $result['id'],
            'url' => $result['url'],
        ]);
    }

    /**
     * Resolve multiple entities
     */
    private function resolveMultiple(Account $account, Request $request): JsonResponse
    {
        $jsonData = json_decode($request->get('json'), true);

        if (!$jsonData || !is_array($jsonData)) {
            return response()->json([
                'error' => 'Invalid JSON data',
            ], 400);
        }

        Log::info('Resolving multiple entities', [
            'account_id' => $account->id,
            'count' => count($jsonData),
        ]);

        $results = [];

        foreach ($jsonData as $item) {
            if (!isset($item['id']) || !isset($item['type'])) {
                continue;
            }

            $result = $this->entityResolveService->resolveOneOrCreate(
                $account,
                $item['type'],
                $item['id']
            );

            if ($result) {
                $results[] = $result;
            }
        }

        return response()->json([
            'entities' => $results,
        ]);
    }
}