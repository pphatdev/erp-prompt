<?php

declare(strict_types=1);

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\WorkflowStatus;
use App\Tenants\Modules\IAM\Requests\StoreWorkflowStatusRequest;
use App\Tenants\Modules\IAM\Requests\UpdateWorkflowStatusRequest;
use App\Tenants\Modules\IAM\Resources\WorkflowStatusResource;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowStatusController extends Controller
{
    use Paginates;

    public function __construct(private readonly WorkflowStatusService $statuses)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = WorkflowStatus::query();

        if ($module = $request->query('module')) {
            $query->where('module', $module);
        }

        $paginator = $this->paginateQuery($query->orderBy('module')->orderBy('sequence'), $request);

        return $this->paginatedResponse(WorkflowStatusResource::class, $paginator, $request);
    }

    /**
     * Distinct list of modules currently configured. Cheap helper for the
     * admin UI to populate the module filter dropdown.
     */
    public function modules(): JsonResponse
    {
        $modules = WorkflowStatus::query()
            ->select('module')
            ->distinct()
            ->orderBy('module')
            ->pluck('module');

        return response()->json(['data' => $modules]);
    }

    public function store(StoreWorkflowStatusRequest $request): WorkflowStatusResource
    {
        $status = WorkflowStatus::create($request->validated());
        $this->statuses->flushCache();

        return new WorkflowStatusResource($status);
    }

    public function show(WorkflowStatus $workflowStatus): WorkflowStatusResource
    {
        return new WorkflowStatusResource($workflowStatus);
    }

    public function update(UpdateWorkflowStatusRequest $request, WorkflowStatus $workflowStatus): WorkflowStatusResource
    {
        $workflowStatus->update($request->validated());
        $this->statuses->flushCache();

        return new WorkflowStatusResource($workflowStatus);
    }

    public function destroy(WorkflowStatus $workflowStatus): JsonResponse
    {
        $workflowStatus->delete();
        $this->statuses->flushCache();

        return response()->json(['message' => 'Workflow status archived.'], 200);
    }
}
