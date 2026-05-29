<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\Application;
use App\Models\Tenant\JobVacancy;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class RecruitmentService
{
    public function __construct(private readonly WorkflowStatusService $statuses)
    {
    }

    /**
     * Build the vacancy listing query with optional filters.
     * Filters: status, departmentId, employmentType, search.
     */
    public function buildVacancyQuery(array $filters = []): Builder
    {
        $query = JobVacancy::query()
            ->with(['department', 'position'])
            ->withCount('applications');

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['departmentId'])) {
            $query->where('department_id', $filters['departmentId']);
        }
        if (!empty($filters['employmentType'])) {
            $query->where('employment_type', $filters['employmentType']);
        }
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'ilike', $term)
                  ->orWhere('location', 'ilike', $term);
            });
        }

        $query->orderBy('created_at', 'desc');

        return $query;
    }

    public function createVacancy(array $data): JobVacancy
    {
        $data['status'] ??= $this->statuses->initialFor('hrm.vacancy');

        return DB::transaction(fn () => JobVacancy::create($data));
    }

    public function updateVacancy(JobVacancy $vacancy, array $data): JobVacancy
    {
        return DB::transaction(function () use ($vacancy, $data) {
            $vacancy->update($data);
            return $vacancy->fresh(['department', 'position']);
        });
    }

    /**
     * Publish a draft vacancy. Sets status=open and stamps posted_at.
     */
    public function publishVacancy(JobVacancy $vacancy): JobVacancy
    {
        $this->statuses->validateTransition('hrm.vacancy', $vacancy->status, 'open');

        $vacancy->update([
            'status' => 'open',
            'posted_at' => $vacancy->posted_at ?? now()->toDateString(),
        ]);

        return $vacancy->fresh();
    }

    public function closeVacancy(JobVacancy $vacancy, string $reason = 'closed'): JobVacancy
    {
        if (!in_array($reason, ['closed', 'filled'], true)) {
            throw new DomainException('Reason must be "closed" or "filled".');
        }

        $this->statuses->validateTransition('hrm.vacancy', $vacancy->status, $reason);

        $vacancy->update(['status' => $reason]);

        return $vacancy->fresh();
    }

    /**
     * Application listing query. Optional filters: jobVacancyId, status, search.
     */
    public function buildApplicationQuery(array $filters = []): Builder
    {
        $with = ['vacancy', 'referrer'];
        if (Schema::hasTable('employee_appointments')) {
            $with[] = 'pendingAppointments';
        }
        $query = Application::query()->with($with);

        if (!empty($filters['jobVacancyId'])) {
            $query->where('job_vacancy_id', $filters['jobVacancyId']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('applicant_name', 'ilike', $term)
                  ->orWhere('applicant_email', 'ilike', $term);
            });
        }

        $query->orderBy('applied_at', 'desc');

        return $query;
    }

    /**
     * Store an uploaded candidate resume on the tenant-isolated local disk.
     * Returns a payload the frontend can attach as `resume_path` on submit.
     *
     * Storage path is relative to the tenant disk (FilesystemTenancyBootstrapper
     * re-roots `local` to storage/tenants/{handle}/), so the saved string is
     * portable across environments — never an absolute path.
     */
    public function storeResume(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension() ?: $file->extension() ?: 'bin');
        $allowedExtensions = ['pdf', 'doc', 'docx'];
        if (!in_array($extension, $allowedExtensions, true)) {
            throw new DomainException("Resume file extension '.{$extension}' is not allowed. Use PDF, DOC, or DOCX.");
        }

        $mime = $file->getMimeType();
        $allowedMimes = [
            'application/pdf',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            // Some browsers/clients report DOC/DOCX as octet-stream; we already
            // gate by extension above, so allow it here without widening MIME.
        ];
        if ($mime && !in_array($mime, $allowedMimes, true) && $mime !== 'application/octet-stream') {
            throw new DomainException("Resume MIME type '{$mime}' is not allowed.");
        }

        $originalName = basename($file->getClientOriginalName());
        $originalName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $originalName) ?? 'resume.' . $extension;

        $filename = (string) Str::uuid() . '.' . $extension;
        // Path follows rules/uploads/skill.md §4: <module>/<feature>/<sub_feature>/.
        // Tenant prefix is applied automatically by FilesystemTenancyBootstrapper.
        $path = $file->storeAs('hrm/recruitment/application/resume', $filename, 'local');

        return [
            'path'          => $path,
            'original_name' => $originalName,
            'size'          => $file->getSize(),
            'mime_type'     => $mime,
        ];
    }

    public function submitApplication(array $data): Application
    {
        $data['applied_at'] ??= now();
        $data['status'] = $this->statuses->initialFor('hrm.application');

        return DB::transaction(fn () => Application::create($data));
    }

    /**
     * Update editable application fields. Does NOT touch status or
     * job_vacancy_id — those are controlled by the transition endpoint
     * (status flow) and locked after submission (vacancy attribution).
     */
    public function updateApplication(Application $application, array $data): Application
    {
        return DB::transaction(function () use ($application, $data) {
            $application->update($data);
            return $application->fresh(['vacancy', 'referrer']);
        });
    }

    /**
     * Bulk-withdraw applications by id. Only rows in `applied` or `screening`
     * are eligible — anything further along has audit/business state we don't
     * want to nuke implicitly. Returns counts so the UI can surface partial
     * outcomes without per-row 404 noise.
     *
     * @param  array<int, string>  $ids
     * @return array{deleted:int, skipped:array<int,string>, missing:array<int,string>}
     */
    public function bulkDeleteApplications(array $ids): array
    {
        if (empty($ids)) {
            return ['deleted' => 0, 'skipped' => [], 'missing' => []];
        }

        return DB::transaction(function () use ($ids) {
            $found = Application::whereIn('id', $ids)->get(['id', 'status']);
            $missing = array_values(array_diff($ids, $found->pluck('id')->all()));

            $eligible = $found->filter(
                fn ($a) => in_array($a->status, ['applied', 'screening'], true)
            );
            $skipped = $found->reject(
                fn ($a) => in_array($a->status, ['applied', 'screening'], true)
            )->pluck('id')->values()->all();

            $deleted = $eligible->isEmpty()
                ? 0
                : Application::whereIn('id', $eligible->pluck('id')->all())->delete();

            return [
                'deleted' => (int) $deleted,
                'skipped' => $skipped,
                'missing' => $missing,
            ];
        });
    }

    /**
     * Move an application to the next pipeline stage. Validates the transition
     * against the workflow_statuses table for module hrm.application.
     *
     * Note: Transitioning to `hired` no longer creates an Employee record
     * implicitly. Conversion is an explicit, audit-bounded step — see
     * {@see self::convertToEmployee()} and the matching
     * POST /applications/{application}/convert-to-employee endpoint.
     */
    public function transitionApplication(Application $application, string $toStatus): Application
    {
        $this->statuses->validateTransition('hrm.application', $application->status, $toStatus);

        return DB::transaction(function () use ($application, $toStatus) {
            $application->update(['status' => $toStatus]);
            return $application->fresh(['vacancy', 'referrer']);
        });
    }

    /**
     * Bulk-convert hired applications. Partitions inputs into
     * - converted: ids that produced (or already linked to) an employee this call
     * - alreadyLinked: ids that were linked before this call (no-op)
     * - ineligible: ids that weren't in `hired` status
     * - missing:    ids the tenant doesn't have rows for
     *
     * Each conversion runs through {@see self::convertToEmployee()} so the
     * dedupe-by-email guarantee is preserved.
     *
     * @param  array<int, string>  $ids
     * @return array{converted:int, alreadyLinked:array<int,string>, ineligible:array<int,string>, missing:array<int,string>, errors:array<int,array{id:string,message:string}>}
     */
    public function bulkConvertToEmployees(array $ids): array
    {
        if (empty($ids)) {
            return ['converted' => 0, 'alreadyLinked' => [], 'ineligible' => [], 'missing' => [], 'errors' => []];
        }

        $found = Application::whereIn('id', $ids)->get();
        $missing = array_values(array_diff($ids, $found->pluck('id')->all()));

        $converted = 0;
        $linkedExisting = [];
        $alreadyLinked = [];
        $ineligible = [];
        $errors = [];

        foreach ($found as $app) {
            if ($app->status !== 'hired') {
                $ineligible[] = $app->id;
                continue;
            }
            if ($app->employee_id) {
                $alreadyLinked[] = $app->id;
                continue;
            }
            try {
                $result = $this->convertToEmployee($app);
                if ($result['created']) {
                    $converted++;
                } elseif ($result['linkedExisting']) {
                    $linkedExisting[] = $app->id;
                }
            } catch (DomainException $e) {
                $errors[] = ['id' => $app->id, 'message' => $e->getMessage()];
            }
        }

        return [
            'converted'      => $converted,
            'linkedExisting' => $linkedExisting,
            'alreadyLinked'  => $alreadyLinked,
            'ineligible'     => $ineligible,
            'missing'        => $missing,
            'errors'         => $errors,
        ];
    }

    /**
     * Convert a hired application into an Employee record.
     *
     * Returns an array `{ employee: Employee, created: bool, linkedExisting: bool }`
     * so the caller (and the UI) can distinguish three outcomes that all
     * look identical in a bare Employee response:
     *
     * - `created=true`             — a fresh Employee row was inserted.
     * - `linkedExisting=true`      — the conversion linked to an Employee
     *                                that **already existed** under the same
     *                                email. No new row was created. The
     *                                returned `employee` belongs to whoever
     *                                already held that email — likely with
     *                                a different name from the applicant.
     * - both false (re-link only)  — the application was previously linked,
     *                                and the link was re-confirmed (the
     *                                idempotent fast-path on top of this fn).
     *
     * Email dedupe is intentional (re-hires share the same employee record),
     * but it silently surprises users on test data with email collisions —
     * surface it explicitly via the `linkedExisting` flag so the UI can toast
     * "Linked to <existing-name>" instead of "Employee created".
     *
     * @return array{employee: \App\Models\Tenant\Employee, created: bool, linkedExisting: bool}
     */
    public function convertToEmployee(Application $application, array $overrides = []): array
    {
        if ($application->status !== 'hired') {
            throw new DomainException('Only hired applications can be converted to employees.');
        }

        if ($application->employee_id) {
            $existing = \App\Models\Tenant\Employee::find($application->employee_id);
            if ($existing) {
                return ['employee' => $existing, 'created' => false, 'linkedExisting' => false];
            }
            // Linked employee_id was deleted out from under us — fall through
            // and re-create. The link will be repointed below.
        }

        return DB::transaction(function () use ($application, $overrides) {
            $application->loadMissing('vacancy');

            $parts = preg_split('/\s+/', trim($application->applicant_name)) ?: [];
            $firstName = (string) ($overrides['first_name'] ?? array_shift($parts) ?: 'Hire');
            $lastName  = (string) ($overrides['last_name']  ?? (implode(' ', $parts) ?: 'Hired'));

            $existing = \App\Models\Tenant\Employee::where('email', $application->applicant_email)->first();
            $linkedExisting = (bool) $existing;
            $employee = $existing;

            if (!$employee) {
                $payload = [
                    'employee_id'   => $this->generateNextEmployeeId(),
                    'first_name'    => $firstName,
                    'last_name'     => $lastName,
                    'email'         => $application->applicant_email,
                    'phone'         => $overrides['phone']         ?? $application->applicant_phone,
                    'hired_at'      => $overrides['hired_at']      ?? now()->toDateString(),
                    'base_salary'   => $overrides['base_salary']   ?? $application->expected_salary,
                    'department_id' => $overrides['department_id'] ?? $application->vacancy?->department_id,
                    'position_id'   => $overrides['position_id']   ?? $application->vacancy?->position_id,
                    'status'        => 'active',
                ];
                // Only emit the newer columns if the tenant has migrated them —
                // legacy tenants without the appointment migration still get a
                // working conversion via the admin bulk shortcut.
                if (Schema::hasColumn('employees', 'manager_id')) {
                    $payload['manager_id'] = $overrides['manager_id'] ?? null;
                }
                if (Schema::hasColumn('employees', 'employment_type')) {
                    $payload['employment_type'] = $overrides['employment_type'] ?? 'full_time';
                }
                $employee = \App\Models\Tenant\Employee::create($payload);
            }

            $application->update([
                'employee_id'  => $employee->id,
                'converted_at' => now(),
            ]);

            return [
                'employee'       => $employee,
                'created'        => !$linkedExisting,
                'linkedExisting' => $linkedExisting,
            ];
        });
    }

    /**
     * Prefix for auto-generated employee IDs produced by the hire→employee
     * conversion. Kept as a class constant so a future "tenant-configurable
     * prefix" feature can swap the source without touching every call site.
     */
    public const EMPLOYEE_ID_PREFIX = 'TT';

    /**
     * Minimum width of the numeric component (zero-padded). The format grows
     * naturally past this — TT-9999 → TT-10000 — so this is a floor, not a
     * ceiling.
     */
    public const EMPLOYEE_ID_PAD = 4;

    /**
     * Generate the next sequential employee_id following the pattern
     * `<prefix>-<NNNN>` (e.g. TT-0000, TT-0001, ..., TT-9999, TT-10000).
     *
     * The first auto-issued id on a fresh tenant is `TT-0000` — i.e. the
     * sequence is **zero-indexed**. Once any matching id exists, subsequent
     * calls return `MAX(numeric_suffix) + 1` so collisions never happen
     * against historical rows.
     *
     * - Scans the `employees` table (including soft-deleted rows — IDs must
     *   never be reused even after termination).
     * - Always returns at least `EMPLOYEE_ID_PAD` digits, zero-padded; widens
     *   automatically once the sequence overflows.
     * - Safe to call inside a DB transaction. Concurrent callers may race —
     *   the unique constraint on `employees.employee_id` is the final guard;
     *   callers should be prepared to retry on a 23505 violation. The
     *   conversion service already runs inside a transaction so the window
     *   is small in practice.
     */
    public function generateNextEmployeeId(): string
    {
        // Prefix is tenant-configurable via Settings → Numbering. Stored WITH
        // separator (default "TT-") so the generator concatenates directly.
        $prefix = app(\App\Tenants\Modules\Settings\Services\SettingService::class)
            ->get('numbering.employee_id_prefix');
        if (empty($prefix)) {
            $prefix = self::EMPLOYEE_ID_PREFIX . '-';
        }
        $pad = self::EMPLOYEE_ID_PAD;

        $rows = \App\Models\Tenant\Employee::withTrashed()
            ->where('employee_id', 'like', $prefix . '%')
            ->pluck('employee_id');

        $max = 0;
        $found = false;
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';
        foreach ($rows as $id) {
            if (preg_match($pattern, (string) $id, $m)) {
                $found = true;
                $n = (int) $m[1];
                if ($n > $max) {
                    $max = $n;
                }
            }
        }

        $next = $found ? $max + 1 : 0;
        return $prefix . str_pad((string) $next, $pad, '0', STR_PAD_LEFT);
    }

    /**
     * Window after conversion during which a recruiter can undo the link.
     * After this expires, the link is treated as settled — payroll/leave/etc.
     * may have begun pointing at the Employee and reverting could break
     * downstream state.
     */
    public const REVERT_CONVERSION_WINDOW_DAYS = 7;

    /**
     * Undo a hire→employee conversion. Soft-deletes the linked Employee
     * (preserves audit trail; the next conversion creates a fresh row since
     * Eloquent excludes trashed records from the default email lookup) and
     * nulls `employee_id` + `converted_at` on the application.
     *
     * Refuses if:
     * - The application isn't hired.
     * - The application isn't linked to an employee.
     * - More than {@see self::REVERT_CONVERSION_WINDOW_DAYS} days have passed
     *   since the conversion (window expired — caller should re-hire instead).
     */
    public function revertEmployeeConversion(Application $application): Application
    {
        if ($application->status !== 'hired') {
            throw new DomainException('Only hired applications can have their conversion reverted.');
        }
        if (!$application->employee_id) {
            throw new DomainException('This application is not linked to an employee.');
        }
        if (!$application->converted_at) {
            throw new DomainException('Cannot revert: missing conversion timestamp.');
        }

        $ageDays = $application->converted_at->diffInDays(now(), false);
        if ($ageDays > self::REVERT_CONVERSION_WINDOW_DAYS) {
            throw new DomainException(sprintf(
                'Revert window has expired (%d-day limit). Use the normal off-boarding flow instead.',
                self::REVERT_CONVERSION_WINDOW_DAYS
            ));
        }

        return DB::transaction(function () use ($application) {
            $employee = \App\Models\Tenant\Employee::find($application->employee_id);

            // Employee may already be soft-deleted out-of-band; that's fine —
            // just clean up the link on the application.
            if ($employee) {
                // Free the employee_id sequence number for reuse. Revert is an
                // "undo a mistake" action — unlike termination, the linked ID
                // should be re-issuable to the next conversion. We rename the
                // soft-deleted row's `employee_id` so it no longer matches
                // {@see self::generateNextEmployeeId()}'s `^TT-(\d+)$` pattern,
                // then soft-delete as before. The original number is preserved
                // inline (e.g. `TT-0003-REV-64f8a1b2.12345678`) so an auditor
                // can still trace what happened from the row alone.
                //
                // The rename goes through `update()` rather than `saveQuietly()`
                // so the Auditable trait captures the rename as an explicit
                // step in the audit trail before the delete event fires.
                $originalId = (string) $employee->employee_id;
                $suffix     = '-REV-' . uniqid('', true);
                $employee->update(['employee_id' => $originalId . $suffix]);
                $employee->delete();
            }

            $application->update([
                'employee_id'  => null,
                'converted_at' => null,
            ]);

            return $application->fresh(['vacancy', 'referrer']);
        });
    }
}
