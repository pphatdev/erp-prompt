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
     *
     * Coerce every numeric field to float. PostgreSQL decimal columns return as
     * strings by default, which breaks Number.toFixed() in the frontend.
     */
    public function getBudgetStatus(Project $project): array
    {
        $totalHoursLogged = (float) $project->tasks()
            ->withSum('timesheets', 'hours_worked')
            ->get()
            ->sum('timesheets_sum_hours_worked');

        $assumedHourlyRate = 50; // Phase 2 will swap this for the cost-rate fallback resolver.

        $budget     = (float) $project->budget;
        $actualCost = round($totalHoursLogged * $assumedHourlyRate, 2);
        $variance   = round($budget - $actualCost, 2);
        $pctUsed    = $budget > 0 ? round(($actualCost / $budget) * 100, 2) : 0.0;

        return [
            'budget'          => $budget,
            'actual_cost'     => $actualCost,
            'variance'        => $variance,
            'percentage_used' => $pctUsed,
        ];
    }
}
