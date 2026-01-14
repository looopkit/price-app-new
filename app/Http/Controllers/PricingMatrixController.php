<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Services\PricingMatrixService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class PricingMatrixController extends Controller
{
    public function __construct(
        private PricingMatrixService $pricingMatrixService
    ) {}

    /**
     * Get pricing matrix
     */
    public function matrix(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:accounts,id',
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'supplier_id' => 'sometimes|exists:suppliers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $account = Account::find($request->account_id);
        $productIds = $request->product_ids;
        $supplierId = $request->supplier_id;

        $matrix = $this->pricingMatrixService->getMatrix($account, $productIds, $supplierId);

        return response()->json($matrix);
    }

    /**
     * Get best offers
     */
    public function bestOffers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:accounts,id',
            'product_ids' => 'required|array',
            'product_ids.*' => 'exists:products,id',
            'criteria' => 'sometimes|in:price,priority',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $account = Account::find($request->account_id);
        $productIds = $request->product_ids;
        $criteria = $request->get('criteria', 'priority');

        $offers = $this->pricingMatrixService->getBestOffers($account, $productIds, $criteria);

        return response()->json([
            'account_id' => $account->id,
            'criteria' => $criteria,
            'offers' => $offers,
        ]);
    }

    /**
     * Compare suppliers
     */
    public function compareSuppliers(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:accounts,id',
            'supplier_ids' => 'required|array',
            'supplier_ids.*' => 'exists:suppliers,id',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        $account = Account::find($request->account_id);
        $supplierIds = $request->supplier_ids;

        $comparison = $this->pricingMatrixService->compareSuppliers($account, $supplierIds);

        return response()->json($comparison);
    }
}