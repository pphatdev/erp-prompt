<?php

namespace App\Tenants\Modules\Projects\Services;

use App\Models\Tenant\Task;
use App\Models\Tenant\Timesheet;
use Illuminate\Support\Facades\DB;

class TaskService
{
    /**
     * Log time against a task.
     */
    public function logTime(array $data): Timesheet
    {
        return DB::transaction(function () use ($data) {
            $timesheet = Timesheet::create([
                'task_id' => $data['task_id'],
                'employee_id' => $data['employee_id'],
                'log_date' => $data['log_date'],
                'hours_worked' => $data['hours_worked'],
                'notes' => $data['notes'] ?? null,
            ]);
            
            // Optionally update task status to 'in_progress' if it's 'todo'
            $task = Task::find($data['task_id']);
            if ($task && $task->status === 'todo') {
                $task->update(['status' => 'in_progress']);
            }

            return $timesheet;
        });
    }

    /**
     * Update task status (e.g., from Kanban board).
     */
    public function updateStatus(Task $task, string $status): Task
    {
        $task->update(['status' => $status]);
        return $task;
    }
}
