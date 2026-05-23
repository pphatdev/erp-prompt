<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Employee;
use App\Models\Tenant\EmployeeShift;
use App\Models\Tenant\Shift;
use App\Tenants\Modules\HRM\Requests\AssignShiftRequest;
use App\Tenants\Modules\HRM\Requests\StoreShiftRequest;
use App\Tenants\Modules\HRM\Resources\EmployeeShiftResource;
use App\Tenants\Modules\HRM\Resources\ShiftResource;
use App\Tenants\Modules\HRM\Services\ShiftService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ShiftController extends Controller
{
    use Paginates;

    public function __construct(private readonly ShiftService $shifts)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Shift::class);

        $query = $this->shifts
            ->buildIndexQuery($request->only(['search']))
            ->withCount('assignments');

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(ShiftResource::class, $paginator, $request);
    }

    public function store(StoreShiftRequest $request): ShiftResource|JsonResponse
    {
        $this->authorize('create', Shift::class);

        try {
            $shift = $this->shifts->createShift($request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ShiftResource($shift);
    }

    public function show(Shift $shift): ShiftResource
    {
        $this->authorize('view', $shift);

        return new ShiftResource($shift->loadCount('assignments'));
    }

    public function update(StoreShiftRequest $request, Shift $shift): ShiftResource|JsonResponse
    {
        $this->authorize('update', $shift);

        try {
            $shift = $this->shifts->updateShift($shift, $request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ShiftResource($shift);
    }

    public function destroy(Shift $shift): JsonResponse
    {
        $this->authorize('delete', $shift);

        $shift->delete();

        return response()->json(['message' => 'Shift removed.'], 200);
    }

    /**
     * Assign an employee to this shift, optionally bounded by start/end dates.
     * Closes any currently-open assignment for the same employee — see
     * ShiftService::assignToEmployee() for the cascade contract.
     */
    public function assign(AssignShiftRequest $request, Shift $shift): EmployeeShiftResource|JsonResponse
    {
        $this->authorize('update', $shift);

        $data = $request->validated();

        // Belt-and-braces: confirm the employee belongs to the current tenant.
        // BelongsToTenant scopes the lookup so a cross-tenant UUID returns null.
        $employee = Employee::query()->find($data['employee_id']);
        if (!$employee) {
            return response()->json(['message' => 'Employee not found.'], 404);
        }

        try {
            $assignment = $this->shifts->assignToEmployee(
                $employee->id,
                $shift->id,
                $data['start_date'],
                $data['end_date'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new EmployeeShiftResource($assignment->load(['shift', 'employee']));
    }

    /**
     * List the assignment history for a single employee. Useful for the
     * employee profile page's Attendance tab (slice 2) — currently exposed
     * here so the UI can wire up before the attendance endpoints land.
     */
    public function assignmentsForEmployee(Request $request, Employee $employee): JsonResponse
    {
        $this->authorize('viewAny', Shift::class);

        $query = EmployeeShift::query()
            ->with('shift')
            ->where('employee_id', $employee->id)
            ->orderByDesc('start_date');

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(EmployeeShiftResource::class, $paginator, $request);
    }
}
