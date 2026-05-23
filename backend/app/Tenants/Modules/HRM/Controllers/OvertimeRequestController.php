<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\OvertimeRequest;
use App\Tenants\Modules\HRM\Requests\ProcessOvertimeRequest;
use App\Tenants\Modules\HRM\Requests\SubmitOvertimeRequest;
use App\Tenants\Modules\HRM\Resources\OvertimeRequestResource;
use App\Tenants\Modules\HRM\Services\OvertimeService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OvertimeRequestController extends Controller
{
    use Paginates;

    public function __construct(private readonly OvertimeService $overtime)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OvertimeRequest::class);

        $filters = $request->only(['employeeId', 'status', 'from', 'to']);

        // Self-service: force-filter to caller's own employee_id so the
        // paginator counts can't leak total OT volume across the org.
        $user = $request->user();
        $isAdmin = $user?->hasPermission('hrm.overtime.read');
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

        $paginator = $this->paginateQuery($this->overtime->buildIndexQuery($filters), $request);

        return $this->paginatedResponse(OvertimeRequestResource::class, $paginator, $request);
    }

    public function store(SubmitOvertimeRequest $request): OvertimeRequestResource|JsonResponse
    {
        $this->authorize('create', OvertimeRequest::class);

        try {
            $ot = $this->overtime->submit($request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new OvertimeRequestResource($ot->load('employee'));
    }

    public function show(OvertimeRequest $overtimeRequest): OvertimeRequestResource
    {
        $this->authorize('view', $overtimeRequest);

        return new OvertimeRequestResource($overtimeRequest->load('employee'));
    }

    /**
     * Admin endpoint — approve or reject pending overtime in one call.
     * Keeps the audit trail compact: a single transition per row.
     */
    public function process(ProcessOvertimeRequest $request, OvertimeRequest $overtimeRequest): OvertimeRequestResource|JsonResponse
    {
        $this->authorize('process', $overtimeRequest);

        try {
            $overtime = $request->input('decision') === 'approve'
                ? $this->overtime->approve($overtimeRequest)
                : $this->overtime->reject($overtimeRequest, $request->input('reason'));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new OvertimeRequestResource($overtime->load('employee'));
    }

    public function destroy(OvertimeRequest $overtimeRequest): JsonResponse
    {
        $this->authorize('cancel', $overtimeRequest);

        try {
            $this->overtime->cancel($overtimeRequest);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Overtime request cancelled.'], 200);
    }
}
