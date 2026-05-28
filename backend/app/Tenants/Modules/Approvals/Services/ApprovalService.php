<?php

namespace App\Tenants\Modules\Approvals\Services;

use App\Models\Tenant\ApprovalRequest;
use App\Models\Tenant\ApprovalHistory;
use App\Models\Tenant\ApprovalWorkflow;
use App\Models\Tenant\User;
use App\Tenants\Modules\Approvals\Events\ApprovalRequestFinalized;
use Illuminate\Support\Facades\DB;
use Exception;

class ApprovalService
{
    /**
     * Submit a new request to the approval engine.
     */
    public function submitRequest(string $workflowId, string $requesterId, string $requestableType, string $requestableId): ApprovalRequest
    {
        return DB::transaction(function () use ($workflowId, $requesterId, $requestableType, $requestableId) {
            $workflow = ApprovalWorkflow::with('levels')->findOrFail($workflowId);
            $firstLevel = $workflow->levels->first();

            if (!$firstLevel) {
                throw new Exception("Approval workflow has no levels defined.");
            }

            $request = ApprovalRequest::create([
                'workflow_id' => $workflowId,
                'requester_id' => $requesterId,
                'current_level_id' => $firstLevel->id,
                'requestable_type' => $requestableType,
                'requestable_id' => $requestableId,
                'status' => 'pending',
            ]);

            // Real system would trigger notifications to approvers here
            \App\Tenants\Modules\Approvals\Events\ApprovalPendingEvent::dispatch($request);
            
            return $request;
        });
    }

    /**
     * Process an action on a pending approval request.
     */
    public function processAction(ApprovalRequest $request, User $approver, string $action, ?string $comment = null): ApprovalRequest
    {
        if ($request->status !== 'pending') {
            throw new Exception("Cannot process action on a non-pending request.");
        }

        // 1. Verify Approver Authorization (Role or Specific User)
        $currentLevel = $request->currentLevel;
        if (!$this->isAuthorizedApprover($approver, $currentLevel)) {
            throw new Exception("User is not authorized for this approval level.");
        }

        return DB::transaction(function () use ($request, $approver, $action, $comment, $currentLevel) {
            // 2. Log History
            ApprovalHistory::create([
                'approval_request_id' => $request->id,
                'approver_id' => $approver->id,
                'action' => $action,
                'comment' => $comment,
            ]);

            // 3. State Machine Transitions
            switch ($action) {
                case 'approved':
                    $nextLevel = $request->workflow->levels()->where('sequence', '>', $currentLevel->sequence)->first();
                    
                    if ($nextLevel) {
                        // Advance to next level
                        $request->update(['current_level_id' => $nextLevel->id]);
                        \App\Tenants\Modules\Approvals\Events\ApprovalPendingEvent::dispatch($request);
                    } else {
                        // Fully approved
                        $request->update(['status' => 'approved', 'current_level_id' => null]);
                        $this->notifyOriginModule($request, 'approved');
                    }
                    break;

                case 'rejected':
                    $request->update(['status' => 'rejected', 'current_level_id' => null]);
                    $this->notifyOriginModule($request, 'rejected');
                    break;
                    
                case 'sent_back':
                    $request->update(['status' => 'sent_back', 'current_level_id' => null]);
                    // Require requester to modify and resubmit
                    break;
                    
                default:
                    throw new Exception("Invalid action: {$action}");
            }

            return $request;
        });
    }
    
    /**
     * Verify if the user meets the role or ID requirement for the level.
     */
    protected function isAuthorizedApprover(User $user, $level): bool
    {
        if ($level->approver_id === $user->id) {
            return true;
        }
        
        if ($level->approver_role) {
            return $user->roles()->where('slug', $level->approver_role)->exists();
        }
        
        return false;
    }

    /**
     * Fan-out to the source module (e.g., HRM Leave) so it can sync the
     * requestable's own status. Listeners decide what to do per requestable_type.
     */
    protected function notifyOriginModule(ApprovalRequest $request, string $finalStatus): void
    {
        ApprovalRequestFinalized::dispatch($request, $finalStatus);
    }
    
    /**
     * Delegate approval authority for a specific request to another user.
     */
    public function delegateApproval(ApprovalRequest $request, User $delegator, User $delegatee, ?string $comment = null): ApprovalRequest
    {
        if ($request->status !== 'pending') {
            throw new Exception("Cannot delegate a non-pending request.");
        }

        if (!$this->isAuthorizedApprover($delegator, $request->currentLevel)) {
            throw new Exception("Only the current authorized approver can delegate this request.");
        }

        return DB::transaction(function () use ($request, $delegator, $delegatee, $comment) {
            // Re-assign the current level to require the delegatee (This would require an override or adding a delegator tracking mechanism)
            // For simplicity, we just add a history log and manually adjust the current level's approver requirement for this request.
            
            ApprovalHistory::create([
                'approval_request_id' => $request->id,
                'approver_id' => $delegator->id,
                'action' => 'delegated',
                'comment' => "Delegated to " . $delegatee->email . ($comment ? ": $comment" : ""),
            ]);

            // Assuming we have a dedicated field for delegated_to_id on the request or we handle it via polymorphic override
            // We can just create a record or directly update the request to allow the delegatee
            $request->update(['delegated_to_id' => $delegatee->id]);

            return $request;
        });
    }

    /**
     * Automatically escalate pending approvals that have exceeded their SLA time.
     */
    public function escalatePendingRequests(): int
    {
        // This is a placeholder for the logic to find pending requests > X days old
        // and advance them or notify the next level up.
        // It should return the number of requests escalated.
        $escalatedCount = 0;
        
        $pendingRequests = ApprovalRequest::where('status', 'pending')
            ->where('created_at', '<', now()->subDays(3))
            ->get();
            
        foreach ($pendingRequests as $req) {
            // Notify next level or re-trigger notification
            // ApprovalPendingEvent::dispatch($req);
            $escalatedCount++;
        }
        
        return $escalatedCount;
    }
}
