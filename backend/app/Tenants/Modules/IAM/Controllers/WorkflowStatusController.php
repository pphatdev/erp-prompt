<?php

declare(strict_types=1);

namespace App\Tenants\Modules\IAM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\WorkflowStatus;
use App\Tenants\Modules\IAM\Requests\ReorderWorkflowStatusesRequest;
use App\Tenants\Modules\IAM\Requests\StoreWorkflowStatusRequest;
use App\Tenants\Modules\IAM\Requests\UpdateWorkflowStatusRequest;
use App\Tenants\Modules\IAM\Resources\WorkflowStatusResource;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use DomainException;
use Illuminate\Auth\Access\AuthorizationException;
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
        $data = $request->validated();
        $status = WorkflowStatus::create($data);

        // Maintain the one-initial-per-module invariant. Demotes any
        // sibling row that was previously the initial.
        if (!empty($data['is_initial'])) {
            $this->statuses->enforceSingleInitial($status->module, $status->id);
        }
        $this->statuses->flushCache();

        return new WorkflowStatusResource($status->fresh());
    }

    public function show(WorkflowStatus $workflowStatus): WorkflowStatusResource
    {
        return new WorkflowStatusResource($workflowStatus);
    }

    public function update(UpdateWorkflowStatusRequest $request, WorkflowStatus $workflowStatus): WorkflowStatusResource
    {
        $data = $request->validated();
        $workflowStatus->update($data);

        // Same invariant on edits: a status promoted to initial demotes
        // the prior initial in the same module.
        if (array_key_exists('is_initial', $data) && (bool) $data['is_initial'] === true) {
            $this->statuses->enforceSingleInitial($workflowStatus->module, $workflowStatus->id);
        }
        $this->statuses->flushCache();

        return new WorkflowStatusResource($workflowStatus->fresh());
    }

    public function destroy(WorkflowStatus $workflowStatus): JsonResponse
    {
        try {
            $this->statuses->assertDeletable($workflowStatus);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $workflowStatus->delete();
        $this->statuses->flushCache();

        return response()->json(['message' => 'Workflow status archived.'], 200);
    }

    /**
     * Bulk reorder candidate-pipeline stages. Request body:
     *   { "module": "hrm.application", "orderedKeys": ["applied", "screening", ...] }
     * Each key gets a fresh sequence (1..N). Off-ramp terminals not
     * in the list keep their existing high sequence.
     */
    public function reorder(ReorderWorkflowStatusesRequest $request): JsonResponse
    {
        if (!$request->user()?->can('iam.workflow_statuses.write')) {
            throw new AuthorizationException();
        }

        $data = $request->validated();
        try {
            $this->statuses->reorderModule($data['module'], $data['orderedKeys']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Workflow statuses reordered.'], 200);
    }

    /**
     * Promote a status to the module's initial state. Demotes any
     * sibling row that previously carried is_initial=true.
     */
    public function setDefault(WorkflowStatus $workflowStatus): JsonResponse
    {
        if (!request()->user()?->can('iam.workflow_statuses.write')) {
            throw new AuthorizationException();
        }

        try {
            $this->statuses->setAsInitial($workflowStatus);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json([
            'message' => 'Status promoted to initial.',
            'data'    => (new WorkflowStatusResource($workflowStatus->fresh()))->toArray(request()),
        ], 200);
    }
}
