<?php

namespace App\Tenants\Modules\Approvals\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ApprovalRequest;
use App\Tenants\Modules\Approvals\Resources\ApprovalRequestResource;
use App\Tenants\Modules\Approvals\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApprovalActionController extends Controller
{
    use Paginates;

    protected $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * Display a listing of approval requests.
     */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        // Self-heal: ensure default Leave workflow exists
        $leaveWorkflow = \App\Models\Tenant\ApprovalWorkflow::where('module', 'hrm')
            ->where('type', 'leave')
            ->first();
            
        if (!$leaveWorkflow) {
            $leaveWorkflow = \App\Models\Tenant\ApprovalWorkflow::create([
                'name' => 'Leave Approval Workflow',
                'module' => 'hrm',
                'type' => 'leave',
            ]);
            $leaveWorkflow->levels()->create([
                'sequence' => 1,
                'approver_role' => 'admin',
            ]);
        }

        // Self-heal: Back-fill missing ApprovalRequests for pending leaves
        if (class_exists(\App\Models\Tenant\Leave::class)) {
            $pendingLeaves = \App\Models\Tenant\Leave::where('status', 'pending')->get();
            foreach ($pendingLeaves as $leave) {
                $hasRequest = \App\Models\Tenant\ApprovalRequest::where('requestable_type', \App\Models\Tenant\Leave::class)
                    ->where('requestable_id', $leave->id)
                    ->exists();
                    
                if (!$hasRequest) {
                    $requesterId = $leave->employee?->user_id ?? $user->id;
                    if ($requesterId) {
                        try {
                            app(\App\Tenants\Modules\Approvals\Services\ApprovalService::class)->submitRequest(
                                workflowId: $leaveWorkflow->id,
                                requesterId: (string) $requesterId,
                                requestableType: \App\Models\Tenant\Leave::class,
                                requestableId: (string) $leave->id,
                            );
                        } catch (\Exception $e) {
                            // Ignore any errors to prevent index route from failing
                        }
                    }
                }
            }
        }
        
        $query = ApprovalRequest::query()
            ->with([
                'workflow.levels',
                'requester',
                'currentLevel',
                'history',
                'requestable' => function ($morphTo) {
                    $morphTo->morphWith([
                        \App\Models\Tenant\Leave::class => ['leaveType', 'employee'],
                        \App\Models\Tenant\PurchaseOrder::class => ['supplier', 'warehouse', 'items.product']
                    ]);
                }
            ]);

        // If the user has approvals.requests.read (admin), they can see everything.
        // Otherwise, they can only see requests they submitted OR requests where they are the active approver.
        if (!$user->hasPermission('approvals.requests.read')) {
            $userRoles = $user->roles()->pluck('slug')->toArray();
            
            $query->where(function ($q) use ($user, $userRoles) {
                $q->where('requester_id', $user->id)
                  ->orWhereHas('currentLevel', function ($lq) use ($user, $userRoles) {
                      $lq->where('approver_id', $user->id)
                         ->orWhereIn('approver_role', $userRoles);
                  });
            });
        }

        $asApprover = $request->query('role') === 'approver' || $request->boolean('asApprover');
        if ($asApprover) {
            $userRoles = $user->roles()->pluck('slug')->toArray();
            $query->where('status', 'pending')
                  ->whereHas('currentLevel', function ($lq) use ($user, $userRoles) {
                      $lq->where('approver_id', $user->id)
                         ->orWhereIn('approver_role', $userRoles);
                  });
        }

        $query->orderBy('created_at', 'desc');

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(ApprovalRequestResource::class, $paginator, $request);
    }

    /**
     * Display the specified approval request.
     */
    public function show(Request $request, ApprovalRequest $approvalRequest): ApprovalRequestResource
    {
        $user = $request->user();
        
        // If not admin, verify ownership or active approver status
        if (!$user->hasPermission('approvals.requests.read')) {
            $userRoles = $user->roles()->pluck('slug')->toArray();
            
            $isRequester = $approvalRequest->requester_id === $user->id;
            $isApprover = false;
            
            if ($approvalRequest->currentLevel) {
                $isApprover = $approvalRequest->currentLevel->approver_id === $user->id
                    || in_array($approvalRequest->currentLevel->approver_role, $userRoles);
            }
            
            if (!$isRequester && !$isApprover) {
                abort(403, 'Unauthorized to view this approval request.');
            }
        }

        return new ApprovalRequestResource($approvalRequest->load([
            'requester', 
            'history', 
            'workflow.levels', 
            'currentLevel', 
            'requestable' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\Tenant\Leave::class => ['leaveType', 'employee'],
                    \App\Models\Tenant\PurchaseOrder::class => ['supplier', 'warehouse', 'items.product']
                ]);
            }
        ]));
    }

    /**
     * Process an action (Approve/Reject/Send Back) on a request.
     */
    public function process(Request $request, ApprovalRequest $approvalRequest): ApprovalRequestResource
    {
        if (!$request->user()->hasPermission('approvals.actions.execute')) {
            abort(403, 'Unauthorized. Missing approvals.actions.execute permission.');
        }

        $data = $request->validate([
            'action' => 'required|in:approved,rejected,sent_back',
            'comment' => 'required_if:action,rejected,sent_back|nullable|string',
        ]);

        $processedRequest = $this->approvalService->processAction(
            $approvalRequest,
            $request->user(),
            $data['action'],
            $data['comment'] ?? null
        );

        return new ApprovalRequestResource($processedRequest->load([
            'requester', 
            'history', 
            'workflow.levels', 
            'currentLevel', 
            'requestable' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\Tenant\Leave::class => ['leaveType', 'employee'],
                    \App\Models\Tenant\PurchaseOrder::class => ['supplier', 'warehouse', 'items.product']
                ]);
            }
        ]));
    }
}
