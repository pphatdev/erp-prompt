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
}
