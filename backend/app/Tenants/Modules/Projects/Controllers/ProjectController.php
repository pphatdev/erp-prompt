<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Projects\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Project;
use App\Models\Tenant\Task;
use App\Models\Tenant\Timesheet;
use App\Tenants\Modules\Projects\Resources\ProjectResource;
use App\Tenants\Modules\Projects\Services\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ProjectController extends Controller
{
    use Paginates;

    public function __construct(private readonly ProjectService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        $query = Project::query()
            ->with('manager')
            ->withCount('tasks')
            ->orderByDesc('created_at');

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', $like)
                ->orWhere('description', 'ilike', $like));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($managerId = $request->query('manager_id')) {
            $query->where('manager_id', $managerId);
        }

        return $this->paginatedResponse(ProjectResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): ProjectResource
    {
        Gate::authorize('create', Project::class);

        $data = $this->validatePayload($request);
        $project = $this->service->createProject($data);

        return new ProjectResource($project->load('manager')->loadCount('tasks'));
    }

    public function show(Project $project): ProjectResource
    {
        Gate::authorize('view', $project);
        return new ProjectResource(
            $project->load(['manager', 'tasks.assignee'])->loadCount('tasks')
        );
    }

    public function update(Request $request, Project $project): ProjectResource
    {
        Gate::authorize('update', $project);

        $data = $this->validatePayload($request, false);
        $project->fill($data)->save();

        return new ProjectResource($project->load('manager')->loadCount('tasks'));
    }

    public function destroy(Project $project): JsonResponse
    {
        Gate::authorize('delete', $project);
        $project->delete();
        return response()->json(['data' => ['deleted' => true]]);
    }

    public function budgetStatus(Project $project): JsonResponse
    {
        Gate::authorize('view', $project);
        return response()->json(['data' => $this->service->getBudgetStatus($project)]);
    }

    /**
     * Lightweight registry KPIs used by the /projects dashboard strip.
     */
    public function kpis(): JsonResponse
    {
        Gate::authorize('viewAny', Project::class);

        $total      = Project::query()->count();
        $active     = Project::query()->where('status', 'active')->count();
        $completed  = Project::query()->where('status', 'completed')->count();
        $unassigned = Task::query()->whereNull('assignee_id')->whereNotIn('status', ['done'])->count();

        $monthStart = now()->startOfMonth()->toDateString();
        $monthEnd   = now()->endOfMonth()->toDateString();
        $hoursThisMonth = (float) Timesheet::query()
            ->whereDate('log_date', '>=', $monthStart)
            ->whereDate('log_date', '<=', $monthEnd)
            ->sum('hours_worked');

        // Over-budget: actual cost (simplified, mirrors getBudgetStatus's flat $50/hr) > budget.
        $assumedHourlyRate = 50;
        $overBudget = 0;
        Project::query()
            ->where('budget', '>', 0)
            ->with(['tasks' => fn ($q) => $q->withSum('timesheets', 'hours_worked')])
            ->chunk(50, function ($chunk) use (&$overBudget, $assumedHourlyRate) {
                foreach ($chunk as $p) {
                    $hours = (float) $p->tasks->sum('timesheets_sum_hours_worked');
                    if ($hours * $assumedHourlyRate > (float) $p->budget) {
                        $overBudget++;
                    }
                }
            });

        return response()->json(['data' => [
            'total'           => $total,
            'active'          => $active,
            'completed'       => $completed,
            'overBudget'      => $overBudget,
            'unassignedTasks' => $unassigned,
            'hoursThisMonth'  => round($hoursThisMonth, 2),
        ]]);
    }

    private function validatePayload(Request $request, bool $required = true): array
    {
        $prefix = $required ? 'required' : 'sometimes';
        return $request->validate([
            'name'        => "$prefix|string|max:255",
            'description' => 'sometimes|nullable|string',
            'start_date'  => 'sometimes|nullable|date',
            'end_date'    => 'sometimes|nullable|date|after_or_equal:start_date',
            'budget'      => 'sometimes|nullable|numeric|min:0',
            'status'      => 'sometimes|nullable|string|in:planning,active,on_hold,completed',
            'manager_id'  => 'sometimes|nullable|uuid|exists:employees,id',
        ]);
    }
}
