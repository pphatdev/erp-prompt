<?php

namespace App\Tenants\Modules\Inventory\Services;

use App\Models\Tenant\Product;
use App\Models\Tenant\StockMovement;
use Illuminate\Support\Facades\DB;
use Exception;

class StockService
{
    /**
     * Record a stock movement ensuring atomic transaction and preventing negative stock if required.
     */
    public function recordMovement(array $data): StockMovement
    {
        return DB::transaction(function () use ($data) {
            $product = Product::lockForUpdate()->findOrFail($data['product_id']);
            
            $quantity = (int) $data['quantity'];
            
            // Adjust sign based on movement type
            if (in_array($data['type'], ['out', 'transfer_out'])) {
                $quantity = -abs($quantity);
            } else {
                $quantity = abs($quantity);
            }

            // Check for negative stock if doing an outward movement
            if ($quantity < 0) {
                // Determine current stock in the specific warehouse
                $currentWarehouseStock = StockMovement::where('product_id', $product->id)
                    ->where('warehouse_id', $data['warehouse_id'])
                    ->sum('quantity');
                    
                if ($currentWarehouseStock + $quantity < 0) {
                    throw new Exception("Insufficient stock in warehouse for product: {$product->sku}. Available: {$currentWarehouseStock}, Requested: " . abs($quantity));
                }
            }

            return StockMovement::create([
                'product_id' => $product->id,
                'warehouse_id' => $data['warehouse_id'],
                'type' => $data['type'],
                'quantity' => $quantity,
                'reference' => $data['reference'] ?? null,
                'notes' => $data['notes'] ?? null,
            ]);
        });
    }

    /**
     * Handle an internal transfer between warehouses.
     */
    public function transferStock(string $productId, string $fromWarehouseId, string $toWarehouseId, int $quantity, ?string $reference = null)
    {
        return DB::transaction(function () use ($productId, $fromWarehouseId, $toWarehouseId, $quantity, $reference) {
            
            // 1. Record Outward Movement
            $this->recordMovement([
                'product_id' => $productId,
                'warehouse_id' => $fromWarehouseId,
                'type' => 'transfer_out',
                'quantity' => $quantity,
                'reference' => $reference,
                'notes' => "Transfer to warehouse {$toWarehouseId}",
            ]);

            // 2. Record Inward Movement
            $this->recordMovement([
                'product_id' => $productId,
                'warehouse_id' => $toWarehouseId,
                'type' => 'transfer_in',
                'quantity' => $quantity,
                'reference' => $reference,
                'notes' => "Transfer from warehouse {$fromWarehouseId}",
            ]);
            
            return true;
        });
    }
}
