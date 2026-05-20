<?php

namespace App\Tenants\Modules\Projects\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Task;
use App\Tenants\Modules\Projects\Resources\TaskResource;
use App\Tenants\Modules\Projects\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TaskController extends Controller
{
    use Paginates;

    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Task::query()->orderBy('created_at', 'desc');

        if ($request->has('project_id')) {
            $query->where('project_id', $request->input('project_id'));
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(TaskResource::class, $paginator, $request);
    }

    public function store(Request $request): TaskResource
    {
        $data = $request->validate([
            'project_id' => 'required|exists:projects,id',
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'due_date' => 'nullable|date',
            'status' => 'nullable|string',
            'priority' => 'nullable|string',
            'assignee_id' => 'nullable|exists:employees,id',
        ]);

        $task = Task::create($data);
        return new TaskResource($task);
    }

    public function show(Task $task): TaskResource
    {
        return new TaskResource($task->load('timesheets', 'assignee'));
    }

    public function updateStatus(Request $request, Task $task): TaskResource
    {
        $request->validate(['status' => 'required|string']);
        $updatedTask = $this->taskService->updateStatus($task, $request->input('status'));
        return new TaskResource($updatedTask);
    }
}
