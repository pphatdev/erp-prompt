<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Listeners;

use App\Models\Tenant\PurchaseOrder;
use App\Tenants\Modules\Approvals\Events\ApprovalRequestFinalized;
use DomainException;
use Illuminate\Support\Facades\Log;

/**
 * Bridge from eApprovals finalisation back to the PO state machine.
 *
 * Mirror of HRM\Listeners\SyncLeaveFromApproval but for purchase orders.
 *
 *   approved  → flip PO to `approved` (unlocks the receive() path)
 *   rejected  → flip PO back to `cancelled` with the rejection trail
 *   sent_back → noop; requester edits and resubmits
 *
 * Note: we DON'T call ProcurementService::approve() because that requires
 * Auth::id() for the approved_by stamp; the approver is recorded on the
 * ApprovalRequest itself, so we set approved_by from the last history
 * entry's approver_id instead.
 */
class SyncPurchaseOrderFromApproval
{
    public function handle(ApprovalRequestFinalized $event): void
    {
        $request = $event->request;

        if ($request->requestable_type !== PurchaseOrder::class) {
            return;
        }

        /** @var PurchaseOrder|null $po */
        $po = $request->requestable;
        if (!$po instanceof PurchaseOrder) {
            return;
        }
        if (!in_array($event->finalStatus, ['approved', 'rejected'], true)) {
            return;
        }

        try {
            $approverId = $request->history()->latest()->first()?->approver_id;

            if ($event->finalStatus === 'approved') {
                // Workflow can finalise even if the PO has since been
                // cancelled by another path — guard against double-handling.
                if (!$po->isSubmitted()) {
                    return;
                }
                $po->update([
                    'status'      => PurchaseOrder::STATUS_APPROVED,
                    'approved_by' => $approverId,
                    'approved_at' => now(),
                ]);
            } else {
                // Rejection takes the PO out of the live pipeline. We park
                // it in `cancelled` rather than back to `draft` so the
                // history trail (submitted_at / cancel_reason) survives a
                // future re-submit (creating a new PO from a copy is the
                // intended escape hatch).
                $po->update([
                    'status'        => PurchaseOrder::STATUS_CANCELLED,
                    'cancelled_by'  => $approverId,
                    'cancelled_at'  => now(),
                    'cancel_reason' => 'Rejected via approval workflow',
                ]);
            }
        } catch (DomainException $e) {
            // After-the-fact failures shouldn't 500 the approval HTTP call —
            // the decision itself is already persisted on ApprovalRequest.
            Log::warning('Purchase order sync after approval failed', [
                'po_id'              => $po->id,
                'approval_request_id'=> $request->id,
                'final_status'       => $event->finalStatus,
                'error'              => $e->getMessage(),
            ]);
        }
    }
}
