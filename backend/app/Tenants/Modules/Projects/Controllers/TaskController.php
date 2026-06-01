<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Projects\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Task;
use App\Tenants\Modules\Projects\Resources\TaskResource;
use App\Tenants\Modules\Projects\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TaskController extends Controller
{
    use Paginates;

    public function __construct(private readonly TaskService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Task::class);

        $query = Task::query()->with(['assignee', 'project'])->orderByDesc('created_at');

        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($assigneeId = $request->query('assignee_id')) {
            $query->where('assignee_id', $assigneeId);
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }
        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('title', 'ilike', $like)
                ->orWhere('description', 'ilike', $like));
        }

        return $this->paginatedResponse(TaskResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): TaskResource
    {
        Gate::authorize('create', Task::class);
        $data = $this->validatePayload($request);
        $task = Task::create($data);
        return new TaskResource($task->load(['assignee', 'project']));
    }

    public function show(Task $task): TaskResource
    {
        Gate::authorize('view', $task);
        return new TaskResource(
            $task->load(['assignee', 'project', 'timesheets.employee'])
        );
    }

    public function update(Request $request, Task $task): TaskResource
    {
        Gate::authorize('update', $task);
        $data = $this->validatePayload($request, false);
        $task->fill($data)->save();
        return new TaskResource($task->load(['assignee', 'project']));
    }

    public function destroy(Task $task): JsonResponse
    {
        Gate::authorize('delete', $task);
        $task->delete();
        return response()->json(['data' => ['deleted' => true]]);
    }

    public function updateStatus(Request $request, Task $task): TaskResource
    {
        Gate::authorize('update', $task);
        $data = $request->validate(['status' => 'required|string|in:todo,in_progress,review,done']);
        $updated = $this->service->updateStatus($task, $data['status']);
        return new TaskResource($updated->load(['assignee', 'project']));
    }

    private function validatePayload(Request $request, bool $required = true): array
    {
        $prefix = $required ? 'required' : 'sometimes';
        return $request->validate([
            'project_id'  => "$prefix|uuid|exists:projects,id",
            'title'       => "$prefix|string|max:255",
            'description' => 'sometimes|nullable|string',
            'due_date'    => 'sometimes|nullable|date',
            'status'      => 'sometimes|nullable|string|in:todo,in_progress,review,done',
            'priority'    => 'sometimes|nullable|string|in:low,medium,high,urgent',
            'assignee_id' => 'sometimes|nullable|uuid|exists:employees,id',
        ]);
    }
}
