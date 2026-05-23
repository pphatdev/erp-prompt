<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\AttendanceLog;
use App\Models\Tenant\Employee;
use App\Tenants\Modules\HRM\Requests\ClockInRequest;
use App\Tenants\Modules\HRM\Requests\ClockOutRequest;
use App\Tenants\Modules\HRM\Resources\AttendanceLogResource;
use App\Tenants\Modules\HRM\Services\AttendanceService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AttendanceController extends Controller
{
    use Paginates;

    public function __construct(private readonly AttendanceService $attendance)
    {
    }

    /**
     * Logs index. Self-service callers (no `hrm.attendance.read`) are
     * force-filtered to their own employee_id — the policy still gates
     * each row but this avoids leaking row counts via the paginator.
     */
    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', AttendanceLog::class);

        $filters = $request->only(['employeeId', 'status', 'from', 'to']);

        $user    = $request->user();
        $isAdmin = $user?->hasPermission('hrm.attendance.read');
        if (!$isAdmin) {
            $selfId = $user?->employee?->id;
            if ($selfId === null) {
                return response()->json([
                    'data' => [],
                    'pagination' => ['page' => 1, 'limit' => 0, 'total' => 0, 'totalPages' => 0],
                ], 200);
            }
            $filters['employeeId'] = $selfId;
        }

        $paginator = $this->paginateQuery($this->attendance->buildIndexQuery($filters), $request);

        return $this->paginatedResponse(AttendanceLogResource::class, $paginator, $request);
    }

    public function show(AttendanceLog $attendanceLog): AttendanceLogResource
    {
        $this->authorize('view', $attendanceLog);

        return new AttendanceLogResource($attendanceLog->load('employee'));
    }

    /**
     * Self-service clock-in. Resolves the employee from the auth context —
     * admins backdating a clock for someone else should use the
     * `hrm.attendance.write` write path (slice 4 manual edit endpoint).
     */
    public function clockIn(ClockInRequest $request): AttendanceLogResource|JsonResponse
    {
        $this->authorize('clock', AttendanceLog::class);

        $employee = $request->user()?->employee;
        if (!$employee) {
            return response()->json([
                'message' => 'No employee record linked to this account.',
            ], 404);
        }

        $employee->loadMissing('department');

        $data = $request->validated();
        $data['client_ip'] = $request->ip();

        try {
            $log = $this->attendance->clockIn($employee, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new AttendanceLogResource($log);
    }

    public function clockOut(ClockOutRequest $request): AttendanceLogResource|JsonResponse
    {
        $this->authorize('clock', AttendanceLog::class);

        $employee = $request->user()?->employee;
        if (!$employee) {
            return response()->json([
                'message' => 'No employee record linked to this account.',
            ], 404);
        }

        $employee->loadMissing('department');

        $data = $request->validated();
        $data['client_ip'] = $request->ip();

        try {
            $log = $this->attendance->clockOut($employee, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new AttendanceLogResource($log);
    }

    /**
     * Admin endpoint — manually trigger reconciliation for the given date
     * (defaults to yesterday). The daily cron calls the same service but
     * this gives ops a hatch when the schedule misses (clock failure,
     * deployment freeze, etc).
     */
    public function reconcile(Request $request): JsonResponse
    {
        $this->authorize('create', AttendanceLog::class);

        $validated = $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
        ]);

        $date = $validated['date'] ?? now()->subDay()->toDateString();
        $result = $this->attendance->reconcileAll($date);

        return response()->json([
            'date'      => $date,
            'processed' => $result['processed'],
            'created'   => $result['created'],
            'skipped'   => $result['skipped'],
        ], 200);
    }
}
