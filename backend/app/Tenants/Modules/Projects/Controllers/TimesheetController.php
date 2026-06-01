<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Projects\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Timesheet;
use App\Tenants\Modules\Projects\Resources\TimesheetResource;
use App\Tenants\Modules\Projects\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class TimesheetController extends Controller
{
    use Paginates;

    public function __construct(private readonly TaskService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Timesheet::class);

        $query = Timesheet::query()
            ->with(['task', 'employee'])
            ->orderByDesc('log_date');

        if ($taskId = $request->query('task_id')) {
            $query->where('task_id', $taskId);
        }
        if ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('log_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('log_date', '<=', $to);
        }

        return $this->paginatedResponse(TimesheetResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): TimesheetResource
    {
        Gate::authorize('create', Timesheet::class);
        $data = $this->validatePayload($request);
        $timesheet = $this->service->logTime($data);
        return new TimesheetResource($timesheet->load(['task', 'employee']));
    }

    public function show(Timesheet $timesheet): TimesheetResource
    {
        Gate::authorize('view', $timesheet);
        return new TimesheetResource($timesheet->load(['task', 'employee']));
    }

    public function update(Request $request, Timesheet $timesheet): TimesheetResource
    {
        Gate::authorize('update', $timesheet);
        $data = $this->validatePayload($request, false);
        $timesheet->fill($data)->save();
        return new TimesheetResource($timesheet->load(['task', 'employee']));
    }

    public function destroy(Timesheet $timesheet): JsonResponse
    {
        Gate::authorize('delete', $timesheet);
        $timesheet->delete();
        return response()->json(['data' => ['deleted' => true]]);
    }

    private function validatePayload(Request $request, bool $required = true): array
    {
        $prefix = $required ? 'required' : 'sometimes';
        return $request->validate([
            'task_id'      => "$prefix|uuid|exists:tasks,id",
            'employee_id'  => "$prefix|uuid|exists:employees,id",
            'log_date'     => "$prefix|date",
            'hours_worked' => "$prefix|numeric|min:0.1|max:24",
            'notes'        => 'sometimes|nullable|string',
        ]);
    }
}
