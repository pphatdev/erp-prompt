<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Services;

use App\Models\Tenant\StockTransfer;
use App\Models\Tenant\StockTransferItem;
use App\Models\Tenant\Warehouse;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Inter-warehouse transfer service.
 *
 * Lifecycle:
 *   create()    → draft (no stock impact yet)
 *   dispatch()  → in_transit (posts transfer_out movements from source)
 *   receive()   → received (posts transfer_in movements to destination;
 *                            partial OK, with received_qty tracked per line)
 *   cancel()    → cancelled
 *                  - if draft: just status flip
 *                  - if in_transit: posts reversal transfer_in to source
 *                                    for un-received lines
 *
 * This wraps the older `StockService::transferStock()` two-movement helper
 * with a proper aggregate-root so the UI has a draftable, partially
 * receivable, cancellable object — not just a paired ledger entry.
 */
class StockTransferService
{
    public function __construct(private readonly StockService $stock) {}

    public function buildQuery(): Builder
    {
        return StockTransfer::query()
            ->with(['fromWarehouse', 'toWarehouse', 'items'])
            ->orderByDesc('created_at');
    }

    public function create(array $data): StockTransfer
    {
        if (empty($data['items'])) {
            throw new DomainException('Transfer needs at least one line item.');
        }
        if (($data['from_warehouse_id'] ?? null) === ($data['to_warehouse_id'] ?? null)) {
            throw new DomainException('Source and destination warehouse must differ.');
        }

        // Existence guard — surface a clean 422 instead of a FK error.
        Warehouse::findOrFail($data['from_warehouse_id']);
        Warehouse::findOrFail($data['to_warehouse_id']);

        return DB::transaction(function () use ($data) {
            $transfer = StockTransfer::create([
                'transfer_number'   => $this->generateNumber(),
                'from_warehouse_id' => $data['from_warehouse_id'],
                'to_warehouse_id'   => $data['to_warehouse_id'],
                'status'            => StockTransfer::STATUS_DRAFT,
                'initiated_by'      => Auth::id(),
                'initiated_at'      => now(),
                'notes'             => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                if (((float) $row['quantity']) <= 0) {
                    throw new DomainException('Each transfer line must have a positive quantity.');
                }
                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id'        => $row['product_id'],
                    'variant_id'        => $row['variant_id'] ?? null,
                    'quantity'          => (float) $row['quantity'],
                    'received_qty'      => 0,
                    'notes'             => $row['notes'] ?? null,
                ]);
            }

            return $transfer->fresh(['fromWarehouse', 'toWarehouse', 'items']);
        });
    }

    /**
     * Posts the OUT half — units leave the source warehouse and are now
     * in-transit. A subsequent receive() posts the IN half.
     *
     * Pre-flight check: every line must be physically available at source
     * BEFORE we start posting, so we don't partially deduct and then fail
     * halfway through.
     */
    public function dispatch(StockTransfer $transfer): StockTransfer
    {
        if (!$transfer->isDraft()) {
            throw new DomainException("Transfer is {$transfer->status}; only drafts can be dispatched.");
        }

        return DB::transaction(function () use ($transfer) {
            $items = $transfer->items()->lockForUpdate()->get();

            foreach ($items as $line) {
                $available = $this->stock->getPhysicalStock($line->product_id, $transfer->from_warehouse_id);
                if ($available < (float) $line->quantity) {
                    throw new DomainException(
                        "Insufficient stock for product {$line->product_id} at source warehouse. " .
                        "Available: {$available}, requested: {$line->quantity}."
                    );
                }
            }

            foreach ($items as $line) {
                $this->stock->recordMovement([
                    'product_id'   => $line->product_id,
                    'warehouse_id' => $transfer->from_warehouse_id,
                    'type'         => 'transfer_out',
                    'quantity'     => (float) $line->quantity,
                    'reference'    => "TRF:{$transfer->transfer_number}",
                    'notes'        => "Dispatched to warehouse {$transfer->to_warehouse_id}",
                ]);
            }

            $transfer->update([
                'status'        => StockTransfer::STATUS_IN_TRANSIT,
                'dispatched_by' => Auth::id(),
                'dispatched_at' => now(),
            ]);

            return $transfer->fresh(['fromWarehouse', 'toWarehouse', 'items']);
        });
    }

    /**
     * Receive into the destination. Pass `[item_id => qty]` or omit to
     * receive everything outstanding. Allows partial receipts (e.g. truck
     * delivers in two waves). When fully received, status flips terminal.
     *
     * @param array<string,float> $received
     */
    public function receive(StockTransfer $transfer, array $received = []): StockTransfer
    {
        if (!$transfer->isInTransit()) {
            throw new DomainException("Transfer is {$transfer->status}; only in_transit transfers can be received.");
        }

        return DB::transaction(function () use ($transfer, $received) {
            $items = $transfer->items()->lockForUpdate()->get()->keyBy('id');

            // Default: receive each line's remaining quantity in full.
            if (empty($received)) {
                $received = $items->mapWithKeys(fn ($i) => [
                    $i->id => max(0.0, (float) $i->quantity - (float) $i->received_qty),
                ])->all();
            }

            foreach ($received as $itemId => $deltaRaw) {
                $delta = (float) $deltaRaw;
                if ($delta <= 0) continue;

                $line = $items[$itemId] ?? null;
                if (!$line) {
                    throw new DomainException("Unknown transfer line: {$itemId}");
                }
                $outstanding = (float) $line->quantity - (float) $line->received_qty;
                if ($delta > $outstanding) {
                    throw new DomainException(
                        "Over-receipt blocked: line {$itemId} has {$outstanding} outstanding, requested {$delta}."
                    );
                }

                $this->stock->recordMovement([
                    'product_id'   => $line->product_id,
                    'warehouse_id' => $transfer->to_warehouse_id,
                    'type'         => 'transfer_in',
                    'quantity'     => $delta,
                    'reference'    => "TRF:{$transfer->transfer_number}",
                    'notes'        => "Received from warehouse {$transfer->from_warehouse_id}",
                ]);

                $line->update(['received_qty' => (float) $line->received_qty + $delta]);
            }

            $fresh = $transfer->fresh('items');
            $allReceived = $fresh->items->every(
                fn ($i) => (float) $i->received_qty >= (float) $i->quantity
            );

            if ($allReceived) {
                $fresh->update([
                    'status'      => StockTransfer::STATUS_RECEIVED,
                    'received_by' => Auth::id(),
                    'received_at' => now(),
                ]);
            }

            return $fresh->fresh(['fromWarehouse', 'toWarehouse', 'items']);
        });
    }

    /**
     * Cancel a draft (free) or in_transit (reverses un-received units back
     * to source). Received transfers are terminal — use a new transfer in
     * the opposite direction to undo.
     */
    public function cancel(StockTransfer $transfer, ?string $reason = null): StockTransfer
    {
        if ($transfer->isCancelled()) return $transfer;
        if ($transfer->isReceived()) {
            throw new DomainException('Cannot cancel a fully received transfer; create a reverse transfer instead.');
        }

        return DB::transaction(function () use ($transfer, $reason) {
            if ($transfer->isInTransit()) {
                $items = $transfer->items()->lockForUpdate()->get();
                foreach ($items as $line) {
                    $unreceived = (float) $line->quantity - (float) $line->received_qty;
                    if ($unreceived <= 0) continue;

                    // Post a reversal: credit the source warehouse for any
                    // units that never made it to the destination.
                    $this->stock->recordMovement([
                        'product_id'   => $line->product_id,
                        'warehouse_id' => $transfer->from_warehouse_id,
                        'type'         => 'transfer_in',
                        'quantity'     => $unreceived,
                        'reference'    => "TRF-CXL:{$transfer->transfer_number}",
                        'notes'        => 'Cancellation reversal',
                    ]);
                }
            }

            $transfer->update([
                'status'        => StockTransfer::STATUS_CANCELLED,
                'cancelled_by'  => Auth::id(),
                'cancelled_at'  => now(),
                'cancel_reason' => $reason,
            ]);

            return $transfer->fresh(['fromWarehouse', 'toWarehouse', 'items']);
        });
    }

    private function generateNumber(): string
    {
        return 'TRF-' . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
