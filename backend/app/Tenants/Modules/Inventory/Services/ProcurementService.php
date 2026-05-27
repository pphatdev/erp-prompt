<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Services;

use App\Models\Tenant\ApprovalWorkflow;
use App\Models\Tenant\Product;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\PurchaseOrderItem;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Approvals\Services\ApprovalService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Procure-to-Pay service. Owns the PO state machine and the receiving
 * pipeline that turns received_qty deltas into stock_movement (type=in) rows
 * via StockService.
 */
class ProcurementService
{
    public function __construct(
        private readonly StockService $stock,
        private readonly ApprovalService $approvals,
    ) {}

    public function buildQuery(): Builder
    {
        return PurchaseOrder::query()
            ->with(['supplier', 'warehouse', 'items'])
            ->orderByDesc('created_at');
    }

    /**
     * Create a draft PO with lines. Each line snapshots product_name and
     * unit_cost so subsequent catalogue edits don't drift the historical totals.
     */
    public function createDraft(array $data): PurchaseOrder
    {
        Supplier::findOrFail($data['supplier_id']);
        Warehouse::findOrFail($data['warehouse_id']);

        if (empty($data['items'])) {
            throw new DomainException('A purchase order needs at least one line item.');
        }

        return DB::transaction(function () use ($data) {
            $po = PurchaseOrder::create([
                'po_number'    => $this->generatePoNumber(),
                'supplier_id'  => $data['supplier_id'],
                'warehouse_id' => $data['warehouse_id'],
                'status'       => PurchaseOrder::STATUS_DRAFT,
                'order_date'   => $data['order_date']  ?? now()->toDateString(),
                'expected_at'  => $data['expected_at'] ?? null,
                'notes'        => $data['notes']       ?? null,
                'ordered_by'   => Auth::id(),
            ]);

            foreach ($data['items'] as $row) {
                $this->buildItem($po, $row);
            }

            return $this->recalcTotals($po->fresh('items'));
        });
    }

    public function addItem(PurchaseOrder $po, array $row): PurchaseOrderItem
    {
        if (!$po->isDraft()) {
            throw new DomainException("Cannot add items: PO is {$po->status}; only drafts are editable.");
        }
        return DB::transaction(function () use ($po, $row) {
            $item = $this->buildItem($po, $row);
            $this->recalcTotals($po->fresh('items'));
            return $item->fresh();
        });
    }

    public function submit(PurchaseOrder $po): PurchaseOrder
    {
        $this->assertOpen($po);
        if (!$po->isDraft()) {
            throw new DomainException("PO is {$po->status}; only drafts can be submitted.");
        }
        if ($po->items()->count() === 0) {
            throw new DomainException('Cannot submit a PO with no items.');
        }

        return DB::transaction(function () use ($po) {
            $po->update([
                'status'       => PurchaseOrder::STATUS_SUBMITTED,
                'submitted_by' => Auth::id(),
                'submitted_at' => now(),
            ]);

            // Fan out into eApprovals when a workflow is configured for
            // inventory/purchase_order. Absent that, the PO stays in
            // `submitted` and an admin can manually call approve() — the
            // legacy escape hatch for tenants that haven't set up workflows.
            $this->maybeRouteToApproval($po);

            return $po->fresh(['supplier', 'warehouse', 'items']);
        });
    }

    /**
     * Look up the inventory→purchase_order workflow for this tenant and, if
     * one exists, file an ApprovalRequest against the PO. The PO stays in
     * `submitted` until the workflow finalises — at which point
     * `SyncPurchaseOrderFromApproval` flips it to approved (or back to
     * draft on rejection).
     *
     * A missing workflow is NOT an error — it's the explicit signal that the
     * tenant wants manual approval via the admin button.
     */
    private function maybeRouteToApproval(PurchaseOrder $po): void
    {
        $workflow = ApprovalWorkflow::query()
            ->where('module', 'inventory')
            ->where('type', 'purchase_order')
            ->first();

        if (!$workflow) {
            return;
        }

        $requesterId = Auth::id() ?? $po->ordered_by;
        if (!$requesterId) {
            Log::warning('PO submit cannot route to eApprovals — no requester id available.', [
                'po_id' => $po->id,
            ]);
            return;
        }

        $this->approvals->submitRequest(
            workflowId:      $workflow->id,
            requesterId:     $requesterId,
            requestableType: PurchaseOrder::class,
            requestableId:   $po->id,
        );
    }

    public function approve(PurchaseOrder $po): PurchaseOrder
    {
        $this->assertOpen($po);
        if (!$po->isSubmitted()) {
            throw new DomainException("PO is {$po->status}; only submitted POs can be approved.");
        }

        $po->update([
            'status'      => PurchaseOrder::STATUS_APPROVED,
            'approved_by' => Auth::id(),
            'approved_at' => now(),
        ]);
        return $po->fresh(['supplier', 'warehouse', 'items']);
    }

    /**
     * Receive a batch — `$received` is `[item_id => qty_received_now]`. Posts
     * one stock_movement (in) per delta and bumps `received_qty` on each line.
     * Flips status to `receiving` (partial) or `received` (everything in).
     */
    public function receive(PurchaseOrder $po, array $received, ?string $notes = null): PurchaseOrder
    {
        if (!$po->isReceivable()) {
            throw new DomainException("PO is {$po->status}; only approved/receiving POs accept receipts.");
        }
        if (empty($received)) {
            throw new DomainException('Receive payload must contain at least one line.');
        }

        return DB::transaction(function () use ($po, $received, $notes) {
            $items = $po->items()->lockForUpdate()->get()->keyBy('id');

            foreach ($received as $itemId => $deltaRaw) {
                $delta = (float) $deltaRaw;
                if ($delta <= 0) continue;

                $line = $items[$itemId] ?? null;
                if (!$line) {
                    throw new DomainException("Unknown PO line: {$itemId}");
                }
                if ($delta > $line->outstandingQty()) {
                    throw new DomainException(
                        "Over-receipt blocked on line for '{$line->product_name}': " .
                        "outstanding {$line->outstandingQty()}, requested {$delta}."
                    );
                }

                // 1) Stock-in via StockService. Pass unit_cost so WAC
                //    recomputes on every receipt at the PO-line's snapshotted
                //    cost — not the catalogue price, which may have drifted.
                $this->stock->recordMovement([
                    'product_id'   => $line->product_id,
                    'warehouse_id' => $po->warehouse_id,
                    'type'         => 'in',
                    'quantity'     => $delta,
                    'unit_cost'    => (float) $line->unit_cost,
                    'reference'    => "PO:{$po->po_number}",
                    'notes'        => $notes ?? "Receipt against {$po->po_number}",
                ]);

                // 2) Bump received_qty on the line.
                $line->update(['received_qty' => (float) $line->received_qty + $delta]);
            }

            // 3) Recompute PO status based on outstanding totals.
            $fresh = $po->fresh('items');
            $allReceived = $fresh->items->every(fn ($i) => $i->isFullyReceived());

            $fresh->update([
                'status'      => $allReceived ? PurchaseOrder::STATUS_RECEIVED : PurchaseOrder::STATUS_RECEIVING,
                'received_at' => $allReceived ? now() : $fresh->received_at,
            ]);

            return $fresh->fresh(['supplier', 'warehouse', 'items']);
        });
    }

    public function cancel(PurchaseOrder $po, ?string $reason = null): PurchaseOrder
    {
        if ($po->isCancelled()) return $po;
        if (!$po->isCancellable()) {
            throw new DomainException(
                "Cannot cancel a {$po->status} PO. Reverse downstream stock receipts first."
            );
        }

        $po->update([
            'status'        => PurchaseOrder::STATUS_CANCELLED,
            'cancelled_by'  => Auth::id(),
            'cancelled_at'  => now(),
            'cancel_reason' => $reason,
        ]);
        return $po->fresh(['supplier', 'warehouse', 'items']);
    }

    private function buildItem(PurchaseOrder $po, array $row): PurchaseOrderItem
    {
        /** @var Product $product */
        $product = Product::findOrFail($row['product_id']);
        $orderedQty = (float) $row['ordered_qty'];
        $unitCost   = (float) ($row['unit_cost'] ?? $product->unit_price);

        if ($orderedQty <= 0) {
            throw new DomainException('ordered_qty must be positive.');
        }

        return PurchaseOrderItem::create([
            'purchase_order_id' => $po->id,
            'product_id'        => $product->id,
            'variant_id'        => $row['variant_id'] ?? null,
            'product_name'      => $product->name,
            'variant_sku'       => $row['variant_sku'] ?? null,
            'ordered_qty'       => $orderedQty,
            'received_qty'      => 0,
            'unit_cost'         => $unitCost,
            'line_total'        => round($orderedQty * $unitCost, 2),
            'notes'             => $row['notes'] ?? null,
        ]);
    }

    private function recalcTotals(PurchaseOrder $po): PurchaseOrder
    {
        $subtotal = (float) $po->items->sum('line_total');
        $po->update([
            'subtotal'     => $subtotal,
            'tax_amount'   => 0,
            'total_amount' => $subtotal,
        ]);
        return $po->fresh(['supplier', 'warehouse', 'items']);
    }

    private function assertOpen(PurchaseOrder $po): void
    {
        if ($po->isCancelled()) {
            throw new DomainException('PO is cancelled.');
        }
        if ($po->isReceived()) {
            throw new DomainException('PO is already fully received.');
        }
    }

    private function generatePoNumber(): string
    {
        $prefix = app(\App\Tenants\Modules\Settings\Services\SettingService::class)
            ->get('numbering.po_prefix');
        if (empty($prefix)) {
            $prefix = 'PO-';
        }
        return $prefix . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
