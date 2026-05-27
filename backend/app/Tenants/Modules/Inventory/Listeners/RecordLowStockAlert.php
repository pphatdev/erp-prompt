<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Listeners;

use App\Models\Tenant\LowStockAlert;
use App\Tenants\Modules\Inventory\Events\ProductWentBelowMinimumStock;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * Persists the threshold-crossing as a `LowStockAlert` row, idempotent on
 * (product_id, status=open) so a SKU already flagged red doesn't spawn
 * duplicates if the event re-fires for any reason.
 *
 * Queued — keeps the original out-movement transaction snappy.
 */
class RecordLowStockAlert implements ShouldQueue
{
    public function handle(ProductWentBelowMinimumStock $event): void
    {
        $product = $event->product;

        $existing = LowStockAlert::query()
            ->where('product_id', $product->id)
            ->where('status', LowStockAlert::STATUS_OPEN)
            ->first();

        if ($existing) {
            return;
        }

        LowStockAlert::create([
            'tenant_id'         => $product->tenant_id,
            'product_id'        => $product->id,
            'threshold'         => $event->threshold,
            'quantity_at_alert' => $event->currentQuantity,
            'status'            => LowStockAlert::STATUS_OPEN,
        ]);

        Log::info('Low-stock alert opened', [
            'product_id' => $product->id,
            'sku'        => $product->sku,
            'qty'        => $event->currentQuantity,
            'threshold'  => $event->threshold,
        ]);
    }
}
