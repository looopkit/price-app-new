<?php

namespace App\Services;

use App\Models\Account;
use App\Models\OrderPosition;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class OrderPricingService
{
    /**
     * Get pricing matrix for customer orders
     * Calculates best suppliers and prices for procurement
     */
    public function getOrderPricingMatrix(Account $account, array $orderPositionIds): array
    {
        Log::info('Building order pricing matrix', [
            'account_id' => $account->id,
            'orders_count' => count($orderPositionIds),
        ]);

        $orders = OrderPosition::forAccount($account->id)
            ->customerOrders()
            ->whereIn('id', $orderPositionIds)
            ->with(['product.offers.supplier'])
            ->get();

        $matrix = [];
        $totalProcurementCost = 0;

        foreach ($orders as $order) {
            $product = $order->product;
            $remainingQty = $order->getRemainingQuantity();

            if ($remainingQty <= 0) {
                continue; // Order fully covered
            }

            $offers = $product->offers()->orderByPriority()->get();

            if ($offers->isEmpty()) {
                $matrix[] = [
                    'order_id' => $order->id,
                    'product_id' => $product->id,
                    'product_name' => $product->name,
                    'required_quantity' => $remainingQty,
                    'available_offers' => [],
                    'recommended_supplier' => null,
                    'procurement_cost' => null,
                    'warning' => 'No offers available',
                ];
                continue;
            }

            // Calculate procurement plan
            $procurementPlan = $this->calculateProcurementPlan($remainingQty, $offers);

            $totalCost = array_sum(array_column($procurementPlan, 'cost'));
            $totalProcurementCost += $totalCost;

            $matrix[] = [
                'order_id' => $order->id,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'product_code' => $product->code,
                'required_quantity' => $remainingQty,
                'customer_price' => (float) $order->price,
                'procurement_plan' => $procurementPlan,
                'total_procurement_cost' => $totalCost,
                'margin' => ($order->price * $remainingQty) - $totalCost,
                'margin_percentage' => $order->price > 0 
                    ? round((1 - ($totalCost / ($order->price * $remainingQty))) * 100, 2)
                    : 0,
            ];
        }

        return [
            'account_id' => $account->id,
            'orders_count' => count($matrix),
            'total_procurement_cost' => round($totalProcurementCost, 2),
            'orders' => $matrix,
        ];
    }

    /**
     * Calculate optimal procurement plan from multiple suppliers
     */
    private function calculateProcurementPlan(float $requiredQty, Collection $offers): array
    {
        $plan = [];
        $remaining = $requiredQty;

        foreach ($offers as $offer) {
            if ($remaining <= 0) {
                break;
            }

            $availableStock = (float) $offer->stock;
            if ($availableStock <= 0) {
                continue;
            }

            $qtyFromThisSupplier = min($remaining, $availableStock);
            $cost = $qtyFromThisSupplier * $offer->price;

            $plan[] = [
                'supplier_id' => $offer->supplier->id,
                'supplier_name' => $offer->supplier->name,
                'offer_id' => $offer->id,
                'quantity' => $qtyFromThisSupplier,
                'price_per_unit' => (float) $offer->price,
                'cost' => round($cost, 2),
                'priority' => $offer->priority,
            ];

            $remaining -= $qtyFromThisSupplier;
        }

        if ($remaining > 0) {
            $plan[] = [
                'warning' => "Insufficient stock: {$remaining} units not covered",
            ];
        }

        return $plan;
    }

    /**
     * Recommend best supplier for single product order
     */
    public function recommendSupplier(Account $account, int $productId, float $quantity): ?array
    {
        $product = Product::forAccount($account->id)->find($productId);

        if (!$product) {
            return null;
        }

        $offers = $product->offers()
            ->with('supplier')
            ->orderByPriority()
            ->get();

        if ($offers->isEmpty()) {
            return null;
        }

        // Find best offer that can fulfill quantity
        $bestOffer = $offers->first(fn($offer) => $offer->stock >= $quantity);

        if (!$bestOffer) {
            // If no single supplier can fulfill, use highest priority
            $bestOffer = $offers->first();
        }

        return [
            'supplier_id' => $bestOffer->supplier->id,
            'supplier_name' => $bestOffer->supplier->name,
            'offer_id' => $bestOffer->id,
            'price' => (float) $bestOffer->price,
            'available_stock' => (float) $bestOffer->stock,
            'can_fulfill' => $bestOffer->stock >= $quantity,
            'total_cost' => round($bestOffer->price * $quantity, 2),
        ];
    }

    /**
     * Group orders by recommended supplier for batch procurement
     */
    public function groupOrdersBySupplier(Account $account, array $orderPositionIds): array
    {
        $matrix = $this->getOrderPricingMatrix($account, $orderPositionIds);

        $groupedBySupplier = [];

        foreach ($matrix['orders'] as $order) {
            foreach ($order['procurement_plan'] as $plan) {
                if (isset($plan['warning'])) {
                    continue;
                }

                $supplierId = $plan['supplier_id'];

                if (!isset($groupedBySupplier[$supplierId])) {
                    $groupedBySupplier[$supplierId] = [
                        'supplier_id' => $supplierId,
                        'supplier_name' => $plan['supplier_name'],
                        'items' => [],
                        'total_cost' => 0,
                    ];
                }

                $groupedBySupplier[$supplierId]['items'][] = [
                    'order_id' => $order['order_id'],
                    'product_id' => $order['product_id'],
                    'product_name' => $order['product_name'],
                    'quantity' => $plan['quantity'],
                    'price' => $plan['price_per_unit'],
                    'cost' => $plan['cost'],
                ];

                $groupedBySupplier[$supplierId]['total_cost'] += $plan['cost'];
            }
        }

        return [
            'account_id' => $account->id,
            'suppliers_count' => count($groupedBySupplier),
            'suppliers' => array_values($groupedBySupplier),
        ];
    }
}