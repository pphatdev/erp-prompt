<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Leave;
use App\Tenants\Modules\HRM\Requests\StoreLeaveRequest;
use App\Tenants\Modules\HRM\Resources\LeaveResource;
use App\Tenants\Modules\HRM\Services\LeaveService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveController extends Controller
{
    use Paginates;

    public function __construct(private readonly LeaveService $leaves)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Leave::class);

        $filters = $request->only(['employeeId', 'status']);

        // Self-service caller (no `hrm.leave.read` admin grant) gets their
        // own rows only — overrides any employeeId in the query string so
        // the listing endpoint can't be used to enumerate other employees'
        // leave history.
        $user = $request->user();
        if (!$user?->hasPermission('hrm.leave.read')) {
            $filters['employeeId'] = $user?->employee?->id;
        }

        $paginator = $this->paginateQuery($this->leaves->buildIndexQuery($filters), $request);

        return $this->paginatedResponse(LeaveResource::class, $paginator, $request);
    }

    public function store(StoreLeaveRequest $request): LeaveResource|JsonResponse
    {
        $this->authorize('create', Leave::class);

        try {
            $leave = $this->leaves->submitRequest($request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new LeaveResource($leave->load(['employee', 'leaveType']));
    }

    public function show(Leave $leave): LeaveResource
    {
        $this->authorize('view', $leave);

        return new LeaveResource($leave->load(['employee', 'leaveType']));
    }

    public function approve(Leave $leave): LeaveResource|JsonResponse
    {
        $this->authorize('approve', $leave);

        try {
            $leave = $this->leaves->approve($leave);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new LeaveResource($leave);
    }

    public function reject(Leave $leave): LeaveResource|JsonResponse
    {
        $this->authorize('reject', $leave);

        try {
            $leave = $this->leaves->reject($leave);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new LeaveResource($leave);
    }

    public function balance(Request $request, Employee $employee): JsonResponse
    {
        // Admin (`hrm.leave.read`) can pull any employee's balance.
        // Self-service callers (`.self`) can only pull their own.
        $user = $request->user();
        $isAdmin = $user?->hasPermission('hrm.leave.read') ?? false;
        $isSelf = $user?->employee?->id === $employee->id
            && $user?->hasPermission('hrm.leave.read.self');

        if (!$isAdmin && !$isSelf) {
            abort(403, 'Not authorized to view this employee\'s leave balance.');
        }

        return response()->json([
            'data' => $this->leaves->balanceSheetFor($employee),
        ]);
    }

    public function destroy(Leave $leave): JsonResponse
    {
        $this->authorize('delete', $leave);

        $this->leaves->withdraw($leave);

        return response()->json(['message' => 'Leave request withdrawn.'], 200);
    }
}
