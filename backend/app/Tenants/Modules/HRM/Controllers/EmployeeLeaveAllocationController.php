<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeLeaveAllocation;
use App\Tenants\Modules\HRM\Requests\StoreEmployeeLeaveAllocationRequest;
use App\Tenants\Modules\HRM\Requests\UpdateEmployeeLeaveAllocationRequest;
use App\Tenants\Modules\HRM\Resources\EmployeeLeaveAllocationResource;
use App\Tenants\Modules\HRM\Services\LeaveAllocationService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeLeaveAllocationController extends Controller
{
    use Paginates;

    public function __construct(private readonly LeaveAllocationService $allocations) {}

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EmployeeLeaveAllocation::class);

        $query = EmployeeLeaveAllocation::query()
            ->with(['employee:id,employee_id,first_name,last_name', 'leaveType']);

        if ($employeeId = $request->query('employeeId')) {
            $query->where('employee_id', $employeeId);
        }
        if ($leaveTypeId = $request->query('leaveTypeId')) {
            $query->where('leave_type_id', $leaveTypeId);
        }
        if ($year = $request->query('year')) {
            $query->where('year', (int) $year);
        }

        $query->orderByDesc('year')->orderBy('leave_type_id');

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(EmployeeLeaveAllocationResource::class, $paginator, $request);
    }

    /**
     * Aggregate balance sheet for one employee, current year. Powers
     * the right-pane allocations grid on /hrm/timeoff/allocations.
     */
    public function balanceSheet(Request $request, Employee $employee): JsonResponse
    {
        // Same gate as `index` — self-service users can still hit
        // /api/v1/employees/{id}/leave-balance for their own row.
        if (!$request->user()?->hasPermission('hrm.leave_allocation.read')
            && !$request->user()?->hasPermission('hrm.leave.read')
        ) {
            // Allow self if linked
            $linked = $request->user()?->employee?->id === $employee->id;
            if (!$linked) {
                abort(403);
            }
        }

        $year = (int) ($request->query('year') ?: CarbonImmutable::now()->year);
        return response()->json([
            'data' => $this->allocations->balanceSheetFor($employee, $year),
            'meta' => ['year' => $year, 'employeeId' => $employee->id],
        ]);
    }

    public function store(StoreEmployeeLeaveAllocationRequest $request): EmployeeLeaveAllocationResource
    {
        $this->authorize('create', EmployeeLeaveAllocation::class);

        $data = $request->validated();
        $allocation = EmployeeLeaveAllocation::firstOrCreate(
            [
                'employee_id'   => $data['employee_id'],
                'leave_type_id' => $data['leave_type_id'],
                'year'          => $data['year'],
            ],
            [
                'allocated_days' => $data['allocated_days'],
                'used_days'      => 0,
                'pending_days'   => 0,
                'note'           => $data['note'] ?? null,
            ]
        );

        // If the row already existed, treat store as an upsert so the
        // admin "Add allocation" form works even when an auto-provisioned
        // row is already in place.
        if (!$allocation->wasRecentlyCreated) {
            $this->allocations->adjust(
                $allocation,
                (float) $data['allocated_days'],
                $data['note'] ?? null,
            );
            $allocation->refresh();
        }

        return new EmployeeLeaveAllocationResource(
            $allocation->load(['employee', 'leaveType'])
        );
    }

    public function show(EmployeeLeaveAllocation $allocation): EmployeeLeaveAllocationResource
    {
        $this->authorize('view', $allocation);

        return new EmployeeLeaveAllocationResource(
            $allocation->load(['employee', 'leaveType'])
        );
    }

    public function update(UpdateEmployeeLeaveAllocationRequest $request, EmployeeLeaveAllocation $allocation): EmployeeLeaveAllocationResource
    {
        $this->authorize('update', $allocation);

        try {
            $updated = $this->allocations->adjust(
                $allocation,
                (float) $request->validated()['allocated_days'],
                $request->validated()['note'] ?? null,
            );
        } catch (DomainException $e) {
            abort(422, $e->getMessage());
        }

        return new EmployeeLeaveAllocationResource(
            $updated->load(['employee', 'leaveType'])
        );
    }

    public function destroy(EmployeeLeaveAllocation $allocation): JsonResponse
    {
        $this->authorize('delete', $allocation);

        if ((float) $allocation->used_days > 0 || (float) $allocation->pending_days > 0) {
            return response()->json([
                'message' => 'Cannot delete an allocation with used or pending days. Reset counters first.',
            ], 422);
        }

        $allocation->delete();

        return response()->json(['message' => 'Allocation archived.'], 200);
    }
}
