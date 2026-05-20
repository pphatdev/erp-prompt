<?php

namespace App\Tenants\Modules\Projects\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Tenants\Modules\Projects\Resources\ProjectResource;
use App\Tenants\Modules\Projects\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProjectController extends Controller
{
    use Paginates;

    protected $projectService;

    public function __construct(ProjectService $projectService)
    {
        $this->projectService = $projectService;
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery(
            Project::query()->with('manager')->orderBy('created_at', 'desc'),
            $request
        );

        return $this->paginatedResponse(ProjectResource::class, $paginator, $request);
    }

    public function store(Request $request): ProjectResource
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'start_date' => 'nullable|date',
            'end_date' => 'nullable|date|after_or_equal:start_date',
            'budget' => 'nullable|numeric|min:0',
            'status' => 'nullable|string',
            'manager_id' => 'nullable|exists:employees,id',
        ]);

        $project = $this->projectService->createProject($data);
        return new ProjectResource($project);
    }

    public function show(Project $project): ProjectResource
    {
        return new ProjectResource($project->load('tasks', 'manager'));
    }

    public function budgetStatus(Project $project): \Illuminate\Http\JsonResponse
    {
        $status = $this->projectService->getBudgetStatus($project);
        return response()->json($status);
    }
}
