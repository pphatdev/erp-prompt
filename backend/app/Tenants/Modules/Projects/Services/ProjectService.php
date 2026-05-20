<?php

namespace App\Tenants\Modules\Projects\Services;

use App\Models\Tenant\Project;
use Illuminate\Support\Facades\DB;

class ProjectService
{
    /**
     * Create a new project.
     */
    public function createProject(array $data): Project
    {
        return DB::transaction(function () use ($data) {
            return Project::create($data);
        });
    }

    /**
     * Get project budget vs actual based on timesheets and generic costs (simplified).
     */
    public function getBudgetStatus(Project $project): array
    {
        // For a real app, this would query expenses and multiply timesheet hours by employee rates
        $totalHoursLogged = $project->tasks()->withSum('timesheets', 'hours_worked')->get()->sum('timesheets_sum_hours_worked');
        $assumedHourlyRate = 50; // Simplified assumption
        
        $actualCost = $totalHoursLogged * $assumedHourlyRate;
        
        return [
            'budget' => $project->budget,
            'actual_cost' => $actualCost,
            'variance' => $project->budget - $actualCost,
            'percentage_used' => $project->budget > 0 ? ($actualCost / $project->budget) * 100 : 0,
        ];
    }
}
