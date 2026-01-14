<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\OrderPricingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OrderPricingController extends Controller
{
    public function __construct(
        private OrderPricingService $orderPricingService
    ) {}

    /**
     * Get pricing matrix for orders
     */
    public function matrix(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:accounts,id',
            'order_position_ids' => 'required|array',
            'order_position_ids.*' => 'exists:order_positions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $account = Account::find($request->account_id);
        $orderPositionIds = $request->order_position_ids;

        $matrix = $this->orderPricingService->getOrderPricingMatrix($account, $orderPositionIds);

        return response()->json($matrix);
    }

    /**
     * Recommend supplier for product
     */
    public function recommend(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:accounts,id',
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|numeric|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $account = Account::find($request->account_id);
        $productId = $request->product_id;
        $quantity = $request->quantity;

        $recommendation = $this->orderPricingService->recommendSupplier($account, $productId, $quantity);

        if (!$recommendation) {
            return response()->json([
                'error' => 'No supplier recommendations available',
            ], 404);
        }

        return response()->json([
            'product_id' => $productId,
            'quantity' => $quantity,
            'recommendation' => $recommendation,
        ]);
    }

    /**
     * Group orders by supplier for batch procurement
     */
    public function groupBySupplier(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:accounts,id',
            'order_position_ids' => 'required|array',
            'order_position_ids.*' => 'exists:order_positions,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $account = Account::find($request->account_id);
        $orderPositionIds = $request->order_position_ids;

        $grouped = $this->orderPricingService->groupOrdersBySupplier($account, $orderPositionIds);

        return response()->json($grouped);
    }
}