<?php

namespace App\Services;

use App\Models\Account;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class PricingMatrixService
{
    /**
     * Get pricing matrix for products
     * 
     * @param Account $account
     * @param array $productIds
     * @param int|null $supplierId Optional filter by supplier
     * @return array
     */
    public function getMatrix(Account $account, array $productIds, ?int $supplierId = null): array
    {
        Log::info('Building pricing matrix', [
            'account_id' => $account->id,
            'products_count' => count($productIds),
            'supplier_id' => $supplierId,
        ]);

        $products = Product::forAccount($account->id)
            ->whereIn('id', $productIds)
            ->with(['offers' => function ($query) use ($supplierId) {
                $query->with('supplier')->orderByPriority();
                if ($supplierId) {
                    $query->where('supplier_id', $supplierId);
                }
            }])
            ->get();

        $matrix = [];

        foreach ($products as $product) {
            $offers = $product->offers;
            
            $bestOffer = $offers->first(); // Highest priority

            $matrix[] = [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'product_article' => $product->article,
                'current_price' => $bestOffer?->price,
                'current_stock' => $bestOffer?->stock,
                'best_supplier' => $bestOffer ? [
                    'id' => $bestOffer->supplier->id,
                    'name' => $bestOffer->supplier->name,
                ] : null,
                'offers' => $offers->map(fn($offer) => [
                    'id' => $offer->id,
                    'supplier_id' => $offer->supplier->id,
                    'supplier_name' => $offer->supplier->name,
                    'price' => (float) $offer->price,
                    'stock' => (float) $offer->stock,
                    'priority' => $offer->priority,
                ])->toArray(),
                'offers_count' => $offers->count(),
                'min_price' => $offers->min('price'),
                'max_price' => $offers->max('price'),
                'total_stock' => $offers->sum('stock'),
            ];
        }

        Log::info('Pricing matrix built', [
            'account_id' => $account->id,
            'products_processed' => count($matrix),
        ]);

        return [
            'account_id' => $account->id,
            'products_count' => count($matrix),
            'products' => $matrix,
        ];
    }

    /**
     * Get best offers for products based on criteria
     * 
     * @param Account $account
     * @param array $productIds
     * @param string $criteria 'price' or 'priority'
     * @return Collection
     */
    public function getBestOffers(Account $account, array $productIds, string $criteria = 'priority'): Collection
    {
        $products = Product::forAccount($account->id)
            ->whereIn('id', $productIds)
            ->with(['offers.supplier'])
            ->get();

        return $products->map(function ($product) use ($criteria) {
            $offers = $product->offers;

            if ($offers->isEmpty()) {
                return null;
            }

            $bestOffer = match ($criteria) {
                'price' => $offers->sortBy('price')->first(),
                'priority' => $offers->sortByDesc('priority')->first(),
                default => $offers->first(),
            };

            return [
                'product_id' => $product->id,
                'product_name' => $product->name,
                'best_offer' => [
                    'offer_id' => $bestOffer->id,
                    'supplier_id' => $bestOffer->supplier->id,
                    'supplier_name' => $bestOffer->supplier->name,
                    'price' => (float) $bestOffer->price,
                    'stock' => (float) $bestOffer->stock,
                    'priority' => $bestOffer->priority,
                ],
            ];
        })->filter();
    }

    /**
     * Compare offers from different suppliers
     */
    public function compareSuppliers(Account $account, array $supplierIds): array
    {
        $suppliers = Supplier::forAccount($account->id)
            ->whereIn('id', $supplierIds)
            ->with(['offers.product'])
            ->get();

        $comparison = [];

        foreach ($suppliers as $supplier) {
            $offers = $supplier->offers;

            $comparison[] = [
                'supplier_id' => $supplier->id,
                'supplier_name' => $supplier->name,
                'total_offers' => $offers->count(),
                'avg_price' => $offers->avg('price'),
                'total_stock' => $offers->sum('stock'),
                'products_covered' => $offers->pluck('product_id')->unique()->count(),
                'offers' => $offers->map(fn($offer) => [
                    'product_id' => $offer->product->id,
                    'product_name' => $offer->product->name,
                    'price' => (float) $offer->price,
                    'stock' => (float) $offer->stock,
                ])->toArray(),
            ];
        }

        return [
            'account_id' => $account->id,
            'suppliers_count' => count($comparison),
            'suppliers' => $comparison,
        ];
    }
}