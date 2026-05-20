<?php

namespace App\Tenants\Modules\Projects\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Timesheet;
use App\Tenants\Modules\Projects\Resources\TimesheetResource;
use App\Tenants\Modules\Projects\Services\TaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TimesheetController extends Controller
{
    use Paginates;

    protected $taskService;

    public function __construct(TaskService $taskService)
    {
        $this->taskService = $taskService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Timesheet::query()->orderBy('log_date', 'desc');

        if ($request->has('task_id')) {
            $query->where('task_id', $request->input('task_id'));
        }

        if ($request->has('employee_id')) {
            $query->where('employee_id', $request->input('employee_id'));
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(TimesheetResource::class, $paginator, $request);
    }

    public function store(Request $request): TimesheetResource
    {
        $data = $request->validate([
            'task_id' => 'required|exists:tasks,id',
            'employee_id' => 'required|exists:employees,id',
            'log_date' => 'required|date',
            'hours_worked' => 'required|numeric|min:0.1',
            'notes' => 'nullable|string',
        ]);

        $timesheet = $this->taskService->logTime($data);
        return new TimesheetResource($timesheet);
    }
}
