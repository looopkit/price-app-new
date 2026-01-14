<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Offer;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class OfferController extends Controller
{
    /**
     * Create single offer
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'account_id' => 'required|exists:accounts,id',
            'supplier_id' => 'required|exists:suppliers,id',
            'product_id' => 'required|exists:products,id',
            'price' => 'required|numeric|min:0',
            'stock' => 'required|numeric|min:0',
            'priority' => 'integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            // Verify entities belong to same account
            $account = Account::find($request->account_id);
            $supplier = Supplier::find($request->supplier_id);
            $product = Product::find($request->product_id);

            if ($supplier->account_id !== $account->id || $product->account_id !== $account->id) {
                return response()->json([
                    'error' => 'Entities must belong to the same account',
                ], 422);
            }

            $offer = Offer::create($request->only([
                'account_id',
                'supplier_id',
                'product_id',
                'price',
                'stock',
                'priority',
            ]));

            Log::info('Offer created', [
                'offer_id' => $offer->id,
                'account_id' => $account->id,
                'product_id' => $product->id,
                'supplier_id' => $supplier->id,
            ]);

            return response()->json([
                'id' => $offer->id,
                'supplier' => [
                    'id' => $supplier->id,
                    'name' => $supplier->name,
                ],
                'product' => [
                    'id' => $product->id,
                    'name' => $product->name,
                ],
                'price' => $offer->price,
                'stock' => $offer->stock,
                'priority' => $offer->priority,
            ], 201);
        } catch (\Exception $e) {
            Log::error('Offer creation failed', [
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to create offer',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Update offer
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $offer = Offer::find($id);

        if (!$offer) {
            return response()->json(['error' => 'Offer not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'price' => 'sometimes|numeric|min:0',
            'stock' => 'sometimes|numeric|min:0',
            'priority' => 'sometimes|integer|min:0',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'error' => 'Validation failed',
                'details' => $validator->errors(),
            ], 422);
        }

        try {
            $offer->update($request->only(['price', 'stock', 'priority']));

            Log::info('Offer updated', [
                'offer_id' => $offer->id,
                'changes' => $request->only(['price', 'stock', 'priority']),
            ]);

            return response()->json([
                'id' => $offer->id,
                'price' => $offer->price,
                'stock' => $offer->stock,
                'priority' => $offer->priority,
            ]);
        } catch (\Exception $e) {
            Log::error('Offer update failed', [
                'offer_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to update offer',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Delete offer
     */
    public function destroy(int $id): JsonResponse
    {
        $offer = Offer::find($id);

        if (!$offer) {
            return response()->json(['error' => 'Offer not found'], 404);
        }

        try {
            $offer->delete();

            Log::info('Offer deleted', ['offer_id' => $id]);

            return response()->json(['message' => 'Offer deleted']);
        } catch (\Exception $e) {
            Log::error('Offer deletion failed', [
                'offer_id' => $id,
                'error' => $e->getMessage(),
            ]);

            return response()->json([
                'error' => 'Failed to delete offer',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * List offers for product
     */
    public function indexForProduct(int $productId): JsonResponse
    {
        $product = Product::find($productId);

        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }

        $offers = $product->offers()
            ->with('supplier')
            ->orderByPriority()
            ->get();

        return response()->json([
            'product_id' => $product->id,
            'product_name' => $product->name,
            'offers' => $offers->map(fn($offer) => [
                'id' => $offer->id,
                'supplier' => [
                    'id' => $offer->supplier->id,
                    'name' => $offer->supplier->name,
                ],
                'price' => $offer->price,
                'stock' => $offer->stock,
                'priority' => $offer->priority,
            ]),
        ]);
    }
}