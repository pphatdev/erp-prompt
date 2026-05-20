<?php

namespace App\Tenants\Modules\Approvals\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ApprovalWorkflow;
use App\Tenants\Modules\Approvals\Resources\WorkflowResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class WorkflowController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery(
            ApprovalWorkflow::query()->with('levels')->orderBy('name'),
            $request
        );

        return $this->paginatedResponse(WorkflowResource::class, $paginator, $request);
    }

    public function store(Request $request): WorkflowResource
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'module' => 'required|string',
            'type' => 'required|string',
            'levels' => 'required|array|min:1',
            'levels.*.sequence' => 'required|integer|min:1',
            'levels.*.approver_role' => 'nullable|string|exists:roles,slug',
            'levels.*.approver_id' => 'nullable|uuid|exists:users,id',
        ]);

        $workflow = ApprovalWorkflow::create([
            'name' => $data['name'],
            'module' => $data['module'],
            'type' => $data['type'],
        ]);

        foreach ($data['levels'] as $level) {
            $workflow->levels()->create($level);
        }

        return new WorkflowResource($workflow->load('levels'));
    }
}
