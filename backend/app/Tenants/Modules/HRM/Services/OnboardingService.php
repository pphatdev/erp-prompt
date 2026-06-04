<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\Employee;
use App\Models\Tenant\Offer;
use App\Models\Tenant\OnboardingChecklist;
use App\Models\Tenant\OnboardingTask;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Provisioning todo-list orchestrator for new hires.
 *
 * Seeds a default checklist (IT / HR / Finance / Manager tasks) when an
 * Offer flips to `accepted`. Tasks carry role-keyed ownership so a tenant
 * can swap the IT lead without rewriting history.
 *
 * Transition flow:
 *   - `seedDefaultChecklist(Offer)` — idempotent. Returns the existing
 *     checklist when one is already attached.
 *   - `completeTask(OnboardingTask, completionNotes)` — marks one row done,
 *     bumps the checklist's `completed_tasks`, and flips the checklist
 *     status as the count reaches the total.
 *   - `transitionTaskStatus(OnboardingTask, status)` — generic state move
 *     validated against the `hrm.onboarding_task` workflow_statuses module.
 */
class OnboardingService
{
    public function __construct(private readonly WorkflowStatusService $statuses)
    {
    }

    /**
     * Stock task template seeded for every new hire. Stored as an inline
     * constant so a tenant override (custom title / offset / role) can plug
     * a future "onboarding template" entity in front without touching every
     * caller. Each row: title, owner_role, due_offset_days, sort_order.
     *
     * @return array<int, array{title: string, role: string, offset: int, description: ?string}>
     */
    public static function defaultTemplate(): array
    {
        return [
            // Pre-arrival (negative offset)
            ['title' => 'Send welcome packet + start-day logistics', 'role' => 'hr',         'offset' => -7, 'description' => 'Email start-day logistics, dress code, and required documents.'],
            ['title' => 'Provision laptop + peripherals',            'role' => 'it',         'offset' => -3, 'description' => 'Procure hardware, image OS, install standard tooling.'],
            ['title' => 'Create primary email account',              'role' => 'it',         'offset' => -2, 'description' => 'Create email, add to default distribution lists.'],
            ['title' => 'Issue access badge / office key',           'role' => 'facilities', 'offset' => -1, 'description' => 'Programme door access for the relevant floor.'],
            // Day one
            ['title' => 'Welcome meeting with manager',              'role' => 'manager',    'offset' => 0,  'description' => '30-minute kickoff to set expectations + buddy assignment.'],
            ['title' => 'Workspace + equipment handover',            'role' => 'facilities', 'offset' => 0,  'description' => 'Walk-through of the office, hand over keys/laptop.'],
            ['title' => 'IT account setup (SSO, VPN, internal apps)','role' => 'it',         'offset' => 0,  'description' => 'Sign in to SSO, install VPN, grant role-based app access.'],
            // First week
            ['title' => 'HR paperwork + contract signature',         'role' => 'hr',         'offset' => 1,  'description' => 'Sign the employment contract, tax forms, and benefits enrolment.'],
            ['title' => 'Bank + tax registration with Finance',      'role' => 'finance',    'offset' => 2,  'description' => 'Collect bank details + tax number for payroll provisioning.'],
            ['title' => 'Benefits enrolment briefing',               'role' => 'hr',         'offset' => 3,  'description' => 'Walk-through of health / pension / leave benefits.'],
            // First month
            ['title' => '30-day check-in with manager',              'role' => 'manager',    'offset' => 30, 'description' => 'Review ramp-up progress + open blockers.'],
        ];
    }

    /**
     * Materialise the default checklist for an accepted Offer. Idempotent —
     * subsequent calls return the existing checklist without re-seeding.
     */
    public function seedDefaultChecklist(Offer $offer): OnboardingChecklist
    {
        if ($offer->status !== Offer::STATUS_ACCEPTED) {
            throw new DomainException('Onboarding checklist can only be seeded on accepted offers.');
        }

        $existing = OnboardingChecklist::query()->where('offer_id', $offer->id)->first();
        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($offer) {
            $checklist = OnboardingChecklist::create([
                'offer_id'               => $offer->id,
                'employee_id'            => $offer->employee_id,
                'name'                   => 'Default onboarding',
                'status'                 => OnboardingChecklist::STATUS_PENDING,
                'total_tasks'            => 0,
                'completed_tasks'        => 0,
                'target_completion_date' => $offer->effective_date
                    ? CarbonImmutable::parse($offer->effective_date)->addDays(30)->toDateString()
                    : null,
            ]);

            $template = self::defaultTemplate();
            foreach ($template as $i => $row) {
                $dueDate = $offer->effective_date
                    ? CarbonImmutable::parse($offer->effective_date)->addDays($row['offset'])->toDateString()
                    : null;

                OnboardingTask::create([
                    'checklist_id'    => $checklist->id,
                    'title'           => $row['title'],
                    'description'     => $row['description'] ?? null,
                    'owner_role'      => $row['role'],
                    'due_offset_days' => $row['offset'],
                    'due_date'        => $dueDate,
                    'status'          => OnboardingTask::STATUS_PENDING,
                    'sort_order'      => $i,
                ]);
            }

            $checklist->update(['total_tasks' => count($template)]);

            return $checklist->fresh(['tasks']);
        });
    }

    /**
     * Validated status transition on a single task. Recomputes the
     * checklist's aggregate counters and flips it to completed when every
     * task lands in a terminal state.
     */
    public function transitionTaskStatus(OnboardingTask $task, string $next, ?string $notes = null): OnboardingTask
    {
        $this->statuses->validateTransition('hrm.onboarding_task', $task->status, $next);

        return DB::transaction(function () use ($task, $next, $notes) {
            $update = ['status' => $next];

            if ($next === OnboardingTask::STATUS_COMPLETED || $next === OnboardingTask::STATUS_SKIPPED) {
                $update['completed_at'] = now();
                $update['completed_by_user_id'] = Auth::id();
                if ($notes !== null) {
                    $update['completion_notes'] = $notes;
                }
            }
            $task->update($update);

            $this->refreshChecklistProgress($task->checklist_id);

            return $task->fresh();
        });
    }

    /**
     * Convenience wrapper: pending|in_progress -> completed.
     */
    public function completeTask(OnboardingTask $task, ?string $notes = null): OnboardingTask
    {
        return $this->transitionTaskStatus($task, OnboardingTask::STATUS_COMPLETED, $notes);
    }

    /**
     * Recompute total / completed counters and roll the checklist status
     * up. Called after every task transition. Kept private so callers go
     * through the validated transition path.
     */
    private function refreshChecklistProgress(string $checklistId): void
    {
        $checklist = OnboardingChecklist::find($checklistId);
        if (!$checklist) {
            return;
        }

        $total = $checklist->tasks()->count();
        $closed = $checklist->tasks()->whereIn('status', [
            OnboardingTask::STATUS_COMPLETED,
            OnboardingTask::STATUS_SKIPPED,
        ])->count();

        $status = $checklist->status;
        $completedAt = $checklist->completed_at;

        if ($total > 0 && $closed === $total) {
            $status = OnboardingChecklist::STATUS_COMPLETED;
            $completedAt = $completedAt ?? now();
        } elseif ($closed > 0 && $status === OnboardingChecklist::STATUS_PENDING) {
            $status = OnboardingChecklist::STATUS_IN_PROGRESS;
            $completedAt = null;
        } elseif ($closed === 0) {
            $status = OnboardingChecklist::STATUS_PENDING;
            $completedAt = null;
        }

        $checklist->update([
            'total_tasks'     => $total,
            'completed_tasks' => $closed,
            'status'          => $status,
            'completed_at'    => $completedAt,
        ]);
    }
}
