<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Services\Fulfillment;

use App\Models\Tenant\Order;
use App\Models\Tenant\Product;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Services\StockService;
use App\Tenants\Modules\Sales\Services\InvoiceService;
use App\Tenants\Modules\Sales\Services\SubscriptionService;
use App\Tenants\Modules\Settings\Services\SettingService;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Hybrid Sales fulfillment orchestrator.
 *
 * Called from OrderService::confirmOrder inside the same DB transaction so
 * a downstream failure (e.g. insufficient stock, missing GL account) rolls
 * back the entire confirmation — the Order stays in `new`.
 *
 *   Finance path     → ALWAYS create Invoice. The invoice itself stays
 *                      `new` until a finance user (or auto-confirm setting)
 *                      flips it to `confirmed`, which posts the AR journal.
 *   Software path    → If any line is software, create one Subscription.
 *                      Confirmation/provisioning is a separate explicit step
 *                      so finance/ops can sequence it after invoicing.
 *   Hardware path    → Immediately record `out` stock movements per line
 *                      against the default warehouse. Insufficient stock
 *                      bubbles up as an exception, rolling the txn back.
 *
 * The "default warehouse" is resolved by:
 *   1. The Setting `inventory.default_warehouse_code` (preferred — admin
 *      sets this once per tenant).
 *   2. The single Warehouse row when only one exists.
 *   3. Otherwise we throw — fulfillment can't pick a warehouse for the
 *      tenant and they must configure one.
 */
class OrderFulfillmentService
{
    public function __construct(
        private readonly InvoiceService $invoices,
        private readonly SubscriptionService $subscriptions,
        private readonly StockService $stock,
        private readonly SettingService $settings,
    ) {
    }

    /**
     * Returns metadata about what was created. Useful for the calling
     * controller to surface immediate next steps to the user.
     *
     * @return array{invoice_id: ?string, subscription_id: ?string, stock_movement_ids: array<int, string>}
     */
    public function fulfill(Order $order): array
    {
        return DB::transaction(function () use ($order) {
            $invoice = $this->invoices->createFromOrder($order);

            $subscription = null;
            if ($this->hasSoftware($order)) {
                $subscription = $this->subscriptions->createFromOrder($order);
            }

            $movementIds = [];
            $hardwareItems = $order->items
                ->filter(fn ($item) => $item->product_type === Product::TYPE_HARDWARE);

            if ($hardwareItems->isNotEmpty()) {
                $warehouse = $this->resolveDefaultWarehouse();
                foreach ($hardwareItems as $line) {
                    if ($line->product_id === null) {
                        // Ad-hoc line (free-text product, no catalogue link).
                        // Skip stock deduction — nothing to deduct against.
                        continue;
                    }
                    $movement = $this->stock->recordMovement([
                        'product_id' => $line->product_id,
                        'warehouse_id' => $warehouse->id,
                        'type' => 'out',
                        'quantity' => (int) round((float) $line->quantity),
                        'reference' => "SO:{$order->order_number}",
                        'notes' => "Fulfillment of {$line->product_name}",
                    ]);
                    $movementIds[] = $movement->id;
                }
            }

            return [
                'invoice_id' => $invoice?->id,
                'subscription_id' => $subscription?->id,
                'stock_movement_ids' => $movementIds,
            ];
        });
    }

    private function hasSoftware(Order $order): bool
    {
        return $order->items->contains(
            fn ($item) => $item->product_type === Product::TYPE_SOFTWARE
        );
    }

    private function resolveDefaultWarehouse(): Warehouse
    {
        $code = $this->settings->get('inventory.default_warehouse_code');
        if (is_string($code) && $code !== '') {
            $warehouse = Warehouse::where('code', $code)->first();
            if ($warehouse) {
                return $warehouse;
            }
        }

        $count = Warehouse::query()->count();
        if ($count === 1) {
            return Warehouse::query()->first();
        }

        throw new DomainException(
            'Cannot fulfill hardware: no default warehouse resolved. ' .
            'Set `inventory.default_warehouse_code` via Settings, or create exactly one Warehouse.'
        );
    }
}
