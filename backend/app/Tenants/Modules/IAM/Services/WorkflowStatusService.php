<?php

declare(strict_types=1);

namespace App\Tenants\Modules\IAM\Services;

use App\Models\Tenant\WorkflowStatus;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Authoritative lookup for tenant status flows.
 *
 * Caches per request (the service is request-scoped via Laravel's container)
 * to avoid hitting the DB on every transition validation.
 */
class WorkflowStatusService
{
    /** @var array<string, Collection<int, WorkflowStatus>> */
    private array $cache = [];

    /**
     * Module -> [Eloquent model class, status column]. Used by
     * assertDeletable() to count active records referencing a status
     * before allowing the row to be soft-deleted. Resolving through
     * the model class (not DB::table) keeps BelongsToTenant scoping +
     * SoftDeletes filtering in effect.
     */
    private const REFERENCE_MAP = [
        'hrm.application'     => [\App\Models\Tenant\Application::class,     'status'],
        'hrm.leave'           => [\App\Models\Tenant\Leave::class,           'status'],
        'hrm.appraisal'       => [\App\Models\Tenant\Appraisal::class,       'status'],
        'hrm.vacancy'         => [\App\Models\Tenant\JobVacancy::class,      'status'],
        'hrm.employee'        => [\App\Models\Tenant\Employee::class,        'status'],
        'hrm.payroll_period'  => [\App\Models\Tenant\PayrollPeriod::class,   'status'],
        'hrm.quiz_attempt'    => [\App\Models\Tenant\QuizAttempt::class,     'status'],
        'hrm.interview'       => [\App\Models\Tenant\Interview::class,       'status'],
        'hrm.offer'           => [\App\Models\Tenant\Offer::class,           'status'],
        'hrm.onboarding_task' => [\App\Models\Tenant\OnboardingTask::class,  'status'],
    ];

    /**
     * @return Collection<int, WorkflowStatus>
     */
    public function for(string $module): Collection
    {
        return $this->cache[$module] ??= WorkflowStatus::query()
            ->forModule($module)
            ->get();
    }

    public function lookup(string $module, string $key): ?WorkflowStatus
    {
        return $this->for($module)->firstWhere('key', $key);
    }

    /**
     * Resolve the initial (default) status key for a module. Used by domain
     * services when creating a new record without an explicit status.
     */
    public function initialFor(string $module): string
    {
        $initial = $this->for($module)->firstWhere('is_initial', true);

        if (!$initial) {
            throw new DomainException("No initial status configured for module '{$module}'.");
        }

        return $initial->key;
    }

    /**
     * Throws DomainException when the transition isn't permitted. Domain
     * services catch it and let the controller translate to a 422.
     */
    public function validateTransition(string $module, string $from, string $to): void
    {
        $current = $this->lookup($module, $from);

        if (!$current) {
            throw new DomainException("Unknown status '{$from}' for module '{$module}'.");
        }

        $allowed = $current->allowed_next ?? [];

        if (!in_array($to, $allowed, true)) {
            throw new DomainException(
                "Cannot transition '{$module}' from '{$from}' to '{$to}'."
            );
        }

        if (!$this->lookup($module, $to)) {
            throw new DomainException("Unknown target status '{$to}' for module '{$module}'.");
        }
    }

    /**
     * Demote any prior initial rows for a module so only one carries
     * is_initial=true. Pass $exceptStatusId to keep one specific row
     * untouched (the one being promoted). Returns the demoted IDs so
     * the caller can echo a note in the response if desired.
     *
     * @return list<string>
     */
    public function enforceSingleInitial(string $module, ?string $exceptStatusId = null): array
    {
        $demoted = [];

        $query = WorkflowStatus::query()
            ->where('module', $module)
            ->where('is_initial', true);

        if ($exceptStatusId !== null) {
            $query->where('id', '!=', $exceptStatusId);
        }

        foreach ($query->get() as $row) {
            $row->update(['is_initial' => false]);
            $demoted[] = $row->id;
        }

        if ($demoted !== []) {
            $this->flushCache();
        }

        return $demoted;
    }

    /**
     * Block soft-deleting a status while live records still reference
     * it. The lookup is per-module via REFERENCE_MAP; unmapped modules
     * are not gated (custom tenant workflows fall through).
     *
     * @throws DomainException
     */
    public function assertDeletable(WorkflowStatus $status): void
    {
        $entry = self::REFERENCE_MAP[$status->module] ?? null;
        if ($entry === null) {
            return;
        }

        [$modelClass, $column] = $entry;
        if (!class_exists($modelClass)) {
            return;
        }

        /** @var class-string<\Illuminate\Database\Eloquent\Model> $modelClass */
        $count = $modelClass::query()->where($column, $status->key)->count();
        if ($count > 0) {
            throw new DomainException(
                "Cannot archive status '{$status->label}' ({$status->key}): {$count} active record(s) in module '{$status->module}' still reference it."
            );
        }
    }

    /**
     * Build a unique snake_case key from a label for a given module.
     * Lower-cases, replaces non-alphanumerics with underscores,
     * collapses runs, trims, and suffixes _2, _3, ... if needed.
     */
    public function slugFromLabel(string $label, string $module, ?string $exceptId = null): string
    {
        $base = strtolower($label);
        $base = preg_replace('/[^a-z0-9]+/', '_', $base) ?? '';
        $base = trim($base, '_');
        if ($base === '') {
            $base = 'status';
        }
        $base = substr($base, 0, 30); // leave room for _NN suffix

        $candidate = $base;
        $i = 2;
        while ($this->slugExists($module, $candidate, $exceptId)) {
            $candidate = $base . '_' . $i;
            $i++;
        }

        return $candidate;
    }

    private function slugExists(string $module, string $key, ?string $exceptId): bool
    {
        $query = WorkflowStatus::query()
            ->where('module', $module)
            ->where('key', $key);

        if ($exceptId !== null) {
            $query->where('id', '!=', $exceptId);
        }

        return $query->exists();
    }

    /**
     * Bulk-update sequences for a module. Pass the keys in display
     * order; each gets a fresh sequence (1..N). Off-ramp terminals
     * not in $orderedKeys are left untouched so their high sequences
     * (>=50) remain stable across reorders.
     *
     * @param list<string> $orderedKeys
     * @throws DomainException  when a key isn't in the module
     */
    public function reorderModule(string $module, array $orderedKeys): void
    {
        $rows = WorkflowStatus::query()
            ->where('module', $module)
            ->get(['id', 'key']);

        $known = $rows->pluck('key')->all();
        foreach ($orderedKeys as $key) {
            if (!in_array($key, $known, true)) {
                throw new DomainException("Unknown key '{$key}' in module '{$module}'.");
            }
        }

        DB::transaction(function () use ($module, $orderedKeys) {
            foreach ($orderedKeys as $i => $key) {
                WorkflowStatus::query()
                    ->where('module', $module)
                    ->where('key', $key)
                    ->update(['sequence' => $i + 1]);
            }
        });

        $this->flushCache();
    }

    /**
     * Promote a status to the module's initial state. Demotes any
     * prior initial in the same transaction so the invariant holds
     * (one initial per module).
     */
    public function setAsInitial(WorkflowStatus $status): void
    {
        if ($status->is_terminal) {
            throw new DomainException(
                "Cannot promote terminal status '{$status->key}' to initial."
            );
        }

        DB::transaction(function () use ($status) {
            $this->enforceSingleInitial($status->module, $status->id);
            $status->update(['is_initial' => true]);
        });

        $this->flushCache();
    }

    /**
     * Flush the in-memory cache. Call after mutating the table (e.g. seeder).
     */
    public function flushCache(): void
    {
        $this->cache = [];
    }
}
