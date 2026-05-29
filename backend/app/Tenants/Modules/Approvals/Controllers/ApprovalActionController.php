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

        // Self-heal: ensure default HRM workflows exist and back-fill missing
        // ApprovalRequests so freshly-submitted forms (and any rows created
        // before a workflow was wired) show up under My Requests.
        $this->ensureHrmWorkflowsAndBackfill($user);
        
        $query = ApprovalRequest::query()
            ->with([
                'workflow.levels',
                'requester.employee',
                'currentLevel',
                'history.approver.employee',
                'requestable' => function ($morphTo) {
                    $morphTo->morphWith([
                        \App\Models\Tenant\Leave::class => ['leaveType', 'employee'],
                        \App\Models\Tenant\PurchaseOrder::class => ['supplier', 'warehouse', 'items.product'],
                        \App\Models\Tenant\EmployeeAppointment::class => ['department', 'position', 'manager', 'application', 'employee'],
                        \App\Models\Tenant\Appraisal::class => ['employee', 'reviewer'],
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
            'requester.employee',
            'history.approver.employee',
            'workflow.levels',
            'currentLevel', 
            'requestable' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\Tenant\Leave::class => ['leaveType', 'employee'],
                    \App\Models\Tenant\PurchaseOrder::class => ['supplier', 'warehouse', 'items.product'],
                    \App\Models\Tenant\EmployeeAppointment::class => ['department', 'position', 'manager', 'application', 'employee'],
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
            'requester.employee',
            'history.approver.employee',
            'workflow.levels',
            'currentLevel',
            'requestable' => function ($morphTo) {
                $morphTo->morphWith([
                    \App\Models\Tenant\Leave::class => ['leaveType', 'employee'],
                    \App\Models\Tenant\PurchaseOrder::class => ['supplier', 'warehouse', 'items.product'],
                    \App\Models\Tenant\EmployeeAppointment::class => ['department', 'position', 'manager', 'application', 'employee'],
                ]);
            }
        ]));
    }

    /**
     * Ensure default HRM workflows (leave, overtime, appraisal) exist and
     * back-fill ApprovalRequests for any pre-existing rows that pre-date the
     * workflow being wired. Keeps My Requests usable without manual config.
     */
    protected function ensureHrmWorkflowsAndBackfill(\App\Models\Tenant\User $user): void
    {
        $sources = [
            ['type' => 'leave',                'name' => 'Leave Approval Workflow',                'model' => \App\Models\Tenant\Leave::class,                'pendingStatuses' => ['pending']],
            ['type' => 'overtime',             'name' => 'Overtime Approval Workflow',             'model' => \App\Models\Tenant\OvertimeRequest::class,      'pendingStatuses' => ['pending']],
            ['type' => 'appraisal',            'name' => 'Appraisal Approval Workflow',            'model' => \App\Models\Tenant\Appraisal::class,            'pendingStatuses' => ['draft', 'submitted']],
            ['type' => 'employee_appointment', 'name' => 'Employee Appointment Approval Workflow', 'model' => \App\Models\Tenant\EmployeeAppointment::class,  'pendingStatuses' => ['pending']],
        ];

        $service = app(\App\Tenants\Modules\Approvals\Services\ApprovalService::class);

        foreach ($sources as $source) {
            if (!class_exists($source['model'])) {
                continue;
            }

            // Skip if the tenant hasn't migrated the source table yet. Keeps
            // /approval-requests usable across tenants on different migration
            // generations.
            $table = (new $source['model'])->getTable();
            if (!\Illuminate\Support\Facades\Schema::hasTable($table)) {
                continue;
            }

            $workflow = \App\Models\Tenant\ApprovalWorkflow::where('module', 'hrm')
                ->where('type', $source['type'])
                ->first();

            if (!$workflow) {
                $workflow = \App\Models\Tenant\ApprovalWorkflow::create([
                    'name' => $source['name'],
                    'module' => 'hrm',
                    'type' => $source['type'],
                ]);
                $workflow->levels()->create([
                    'sequence' => 1,
                    'approver_role' => 'admin',
                ]);
            }

            $pending = $source['model']::whereIn('status', $source['pendingStatuses'])->get();
            foreach ($pending as $row) {
                $hasRequest = \App\Models\Tenant\ApprovalRequest::where('requestable_type', $source['model'])
                    ->where('requestable_id', $row->id)
                    ->exists();

                if ($hasRequest) {
                    continue;
                }

                $requesterId = $row->submitted_by
                    ?? $row->employee?->user_id
                    ?? $user->id;
                if (!$requesterId) {
                    continue;
                }

                try {
                    $service->submitRequest(
                        workflowId: $workflow->id,
                        requesterId: (string) $requesterId,
                        requestableType: $source['model'],
                        requestableId: (string) $row->id,
                    );
                } catch (\Exception $e) {
                    // Ignore — keep the index endpoint resilient.
                }
            }
        }
    }
}
