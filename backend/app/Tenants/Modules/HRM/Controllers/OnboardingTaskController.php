<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\OnboardingChecklist;
use App\Models\Tenant\OnboardingTask;
use App\Tenants\Modules\HRM\Resources\OnboardingChecklistResource;
use App\Tenants\Modules\HRM\Resources\OnboardingTaskResource;
use App\Tenants\Modules\HRM\Services\OnboardingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OnboardingTaskController extends Controller
{
    use Paginates;

    public function __construct(private readonly OnboardingService $onboarding)
    {
    }

    public function indexChecklists(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OnboardingChecklist::class);

        $query = OnboardingChecklist::query()->with(['offer', 'employee', 'tasks']);

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($employeeId = $request->query('employeeId')) {
            $query->where('employee_id', $employeeId);
        }

        $paginator = $this->paginateQuery($query->orderByDesc('created_at'), $request);

        return $this->paginatedResponse(OnboardingChecklistResource::class, $paginator, $request);
    }

    public function showChecklist(OnboardingChecklist $checklist): OnboardingChecklistResource
    {
        $this->authorize('view', $checklist);

        return new OnboardingChecklistResource($checklist->load(['offer', 'employee', 'tasks']));
    }

    public function indexTasks(Request $request): JsonResponse
    {
        $this->authorize('viewAny', OnboardingTask::class);

        $query = OnboardingTask::query();

        if ($checklistId = $request->query('checklistId')) {
            $query->where('checklist_id', $checklistId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($ownerRole = $request->query('ownerRole')) {
            $query->where('owner_role', $ownerRole);
        }

        $paginator = $this->paginateQuery($query->orderBy('due_date')->orderBy('sort_order'), $request);

        return $this->paginatedResponse(OnboardingTaskResource::class, $paginator, $request);
    }

    public function transition(Request $request, OnboardingTask $task): OnboardingTaskResource|JsonResponse
    {
        $this->authorize('update', $task);

        $validated = $request->validate([
            'status' => 'required|string|max:40',
            'notes'  => 'nullable|string|max:2000',
        ]);

        try {
            $task = $this->onboarding->transitionTaskStatus($task, $validated['status'], $validated['notes'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new OnboardingTaskResource($task);
    }
}
