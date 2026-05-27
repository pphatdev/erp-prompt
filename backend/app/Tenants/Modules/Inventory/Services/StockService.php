<?php

namespace App\Tenants\Modules\Inventory\Services;

use App\Models\Tenant\Product;
use App\Models\Tenant\StockMovement;
use App\Tenants\Modules\Inventory\Events\ProductWentBelowMinimumStock;
use Exception;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Record a stock movement, recompute Weighted Average Cost on `in`
     * movements, and guard against negative-stock on `out` / `transfer_out`.
     *
     * WAC formula (in-movements only):
     *   new_avg = ((old_qty * old_avg) + (delta * unit_cost)) / (old_qty + delta)
     *
     * Out-movements drop `products.total_quantity` but leave `average_cost`
     * alone — the cost basis only changes when new inventory arrives.
     */
    public function recordMovement(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            $product = Product::lockForUpdate()->findOrFail($data['product_id']);

            $quantity = (float) $data['quantity'];
            $type     = $data['type'];

            // Normalize sign by direction.
            if (in_array($type, ['out', 'transfer_out'], true)) {
                $quantity = -abs($quantity);
            } else {
                $quantity = abs($quantity);
            }

            // Negative-stock guard for out-movements.
            if ($quantity < 0) {
                $currentWarehouseStock = (float) StockMovement::where('product_id', $product->id)
                    ->where('warehouse_id', $data['warehouse_id'])
                    ->sum('quantity');

                if ($currentWarehouseStock + $quantity < 0) {
                    throw new Exception(
                        "Insufficient stock in warehouse for product: {$product->sku}. " .
                        "Available: {$currentWarehouseStock}, Requested: " . abs($quantity)
                    );
                }
            }

            $movement = StockMovement::create([
                'product_id'   => $product->id,
                'warehouse_id' => $data['warehouse_id'],
                'type'         => $type,
                'quantity'     => $quantity,
                'reference'    => $data['reference'] ?? null,
                'notes'        => $data['notes'] ?? null,
            ]);

            // Update denormalized product totals + WAC.
            $this->applyMovementToProduct($product, $quantity, $data['unit_cost'] ?? null);

            return $movement;
        });
    }

    /**
     * Internal transfer between warehouses — two movements in one txn so a
     * crash mid-way can't strand inventory. Pass `unit_cost` only for the
     * inbound side if the destination warehouse should book a different
     * cost basis (rare — usually the value just moves).
     */
    public function transferStock(
        string $productId,
        string $fromWarehouseId,
        string $toWarehouseId,
        float $quantity,
        ?string $reference = null,
    ) {
        return DB::transaction(function () use ($productId, $fromWarehouseId, $toWarehouseId, $quantity, $reference) {
            $this->recordMovement([
                'product_id'   => $productId,
                'warehouse_id' => $fromWarehouseId,
                'type'         => 'transfer_out',
                'quantity'     => $quantity,
                'reference'    => $reference,
                'notes'        => "Transfer to warehouse {$toWarehouseId}",
            ]);

            $this->recordMovement([
                'product_id'   => $productId,
                'warehouse_id' => $toWarehouseId,
                'type'         => 'transfer_in',
                'quantity'     => $quantity,
                'reference'    => $reference,
                'notes'        => "Transfer from warehouse {$fromWarehouseId}",
            ]);

            return true;
        });
    }

    /**
     * Physical on-hand units of `$productId` in `$warehouseId`, summed from
     * the stock_movements ledger. Excludes pending reservations — use
     * `getNetAvailableStock()` for sellable-now availability.
     */
    public function getPhysicalStock(string $productId, string $warehouseId): float
    {
        return (float) StockMovement::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->sum('quantity');
    }

    /**
     * Net availability — physical on-hand minus any active reservations.
     * POS / eCommerce checkout must respect this number before confirming
     * a cart.
     *
     * Lives on StockService (not StockReservationService) so the reservation
     * service can call back to it without circular dependencies.
     */
    public function getNetAvailableStock(string $productId, string $warehouseId): float
    {
        $physical = $this->getPhysicalStock($productId, $warehouseId);

        $reserved = (float) \App\Models\Tenant\StockReservation::query()
            ->where('product_id', $productId)
            ->where('warehouse_id', $warehouseId)
            ->whereIn('status', \App\Models\Tenant\StockReservation::HOLDING_STATUSES)
            ->sum('quantity');

        return $physical - $reserved;
    }

    /**
     * Apply a movement's effect to the product's denormalized counters and
     * Weighted Average Cost. Called inside `recordMovement` while the
     * Product row is already locked-for-update.
     *
     * Behaviour:
     *  - Quantity ALWAYS adjusts `total_quantity` (signed).
     *  - `average_cost` updates ONLY when a positive delta + unit_cost are
     *    present (i.e. inbound + we know what it cost).
     *  - `last_cost` mirrors the most recent inbound unit_cost.
     *  - Transfers post both a transfer_out and transfer_in — the out side
     *    decrements total_quantity, the in side increments it. Net effect on
     *    total_quantity is zero; average_cost unchanged unless an explicit
     *    transfer-in unit_cost is supplied.
     */
    private function applyMovementToProduct(Product $product, float $signedQty, ?float $unitCost): void
    {
        $oldQty = (float) $product->total_quantity;
        $newQty = $oldQty + $signedQty;

        $updates = ['total_quantity' => $newQty];

        if ($signedQty > 0 && $unitCost !== null && $unitCost >= 0) {
            $oldAvg = (float) $product->average_cost;
            // If we had nothing on hand, the new average IS the unit cost —
            // avoids divide-by-zero and the "WAC anchored to a stale 0" trap.
            $newAvg = $oldQty > 0
                ? (($oldQty * $oldAvg) + ($signedQty * $unitCost)) / $newQty
                : $unitCost;

            $updates['average_cost'] = round($newAvg, 4);
            $updates['last_cost']    = round($unitCost, 4);
        }

        $product->update($updates);

        $this->maybeEmitLowStockEvent($product, $oldQty, $newQty);
    }

    /**
     * Edge-trigger the low-stock event. Fires exactly once per downward
     * crossing — when oldQty was at-or-above the threshold and newQty fell
     * below it. Doesn't fire on:
     *  - in-movements (signedQty > 0, qty can only rise),
     *  - out-movements while ALREADY below threshold (busy SKU spam),
     *  - products with no minimum_stock_level set.
     */
    private function maybeEmitLowStockEvent(Product $product, float $oldQty, float $newQty): void
    {
        $threshold = (int) ($product->minimum_stock_level ?? 0);
        if ($threshold <= 0) {
            return;
        }
        if ($oldQty >= $threshold && $newQty < $threshold) {
            event(new ProductWentBelowMinimumStock($product, $oldQty, $newQty, $threshold));
        }
    }
}
