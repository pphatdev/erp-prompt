<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\LeaveType;
use App\Tenants\Modules\HRM\Requests\StoreLeaveTypeRequest;
use App\Tenants\Modules\HRM\Resources\LeaveTypeResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', LeaveType::class);

        $paginator = $this->paginateQuery(LeaveType::query()->orderBy('name'), $request);

        return $this->paginatedResponse(LeaveTypeResource::class, $paginator, $request);
    }

    public function store(StoreLeaveTypeRequest $request): LeaveTypeResource
    {
        $this->authorize('create', LeaveType::class);

        return new LeaveTypeResource(LeaveType::create($request->validated()));
    }

    public function show(LeaveType $leaveType): LeaveTypeResource
    {
        $this->authorize('view', $leaveType);

        return new LeaveTypeResource($leaveType);
    }

    public function update(StoreLeaveTypeRequest $request, LeaveType $leaveType): LeaveTypeResource
    {
        $this->authorize('update', $leaveType);

        $leaveType->update($request->validated());

        return new LeaveTypeResource($leaveType);
    }

    public function destroy(LeaveType $leaveType): JsonResponse
    {
        $this->authorize('delete', $leaveType);

        $leaveType->delete();

        return response()->json(['message' => 'Leave type removed.'], 200);
    }
}
