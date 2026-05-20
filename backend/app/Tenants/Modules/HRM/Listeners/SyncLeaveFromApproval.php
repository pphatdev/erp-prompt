<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Listeners;

use App\Models\Tenant\Leave;
use App\Tenants\Modules\Approvals\Events\ApprovalRequestFinalized;
use App\Tenants\Modules\HRM\Services\LeaveService;
use DomainException;
use Illuminate\Support\Facades\Log;

class SyncLeaveFromApproval
{
    public function __construct(private readonly LeaveService $leaves)
    {
    }

    public function handle(ApprovalRequestFinalized $event): void
    {
        $request = $event->request;

        if ($request->requestable_type !== Leave::class) {
            return;
        }

        $leave = $request->requestable;
        if (!$leave instanceof Leave) {
            return;
        }

        // approved | rejected are the only HRM-meaningful terminal states the
        // workflow can emit. sent_back leaves the leave in pending so the
        // requester can amend and resubmit.
        if (!in_array($event->finalStatus, ['approved', 'rejected'], true)) {
            return;
        }

        try {
            $this->leaves->syncFromApproval($leave, $event->finalStatus);
        } catch (DomainException $e) {
            // Listener runs after the ApprovalRequest is already updated, so a
            // domain failure here (e.g. insufficient balance discovered late)
            // is logged rather than thrown — surfacing it would 500 the
            // approval-action HTTP response after the decision is recorded.
            Log::warning('Leave sync after approval failed', [
                'leave_id'           => $leave->id,
                'approval_request_id'=> $request->id,
                'final_status'       => $event->finalStatus,
                'error'              => $e->getMessage(),
            ]);
        }
    }
}
