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

    public function balance(Employee $employee): JsonResponse
    {
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
