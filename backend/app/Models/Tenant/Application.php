<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Application extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'job_vacancy_id',
        'referrer_employee_id',
        'employee_id',
        'candidate_code',
        'applicant_name',
        'applicant_email',
        'applicant_phone',
        'location',
        'linkedin_url',
        'resume_path',
        'cover_letter',
        'work_experience',
        'education',
        'skills',
        'expected_salary',
        'notes',
        'status',
        'applied_at',
        'converted_at',
        'tenant_id',
    ];

    protected $casts = [
        'applied_at' => 'datetime',
        'converted_at' => 'datetime',
        'expected_salary' => 'decimal:4',
        'work_experience' => 'array',
        'education' => 'array',
        'skills' => 'array',
    ];

    /**
     * Status transitions are configured per tenant in the `workflow_statuses`
     * table (module = 'hrm.application'). See WorkflowStatusService.
     */
    public function vacancy(): BelongsTo
    {
        return $this->belongsTo(JobVacancy::class, 'job_vacancy_id');
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'referrer_employee_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'employee_id');
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(EmployeeAppointment::class);
    }

    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class)->orderByDesc('created_at');
    }

    /**
     * Pending appointment requests, newest first. Modeled as HasMany rather
     * than `hasOne()->latestOfMany('created_at')` because Laravel's
     * latestOfMany always adds a `MAX(primary_key)` tie-breaker, which
     * Postgres rejects on UUID PKs (no max(uuid) function). Callers pick
     * the first row via Collection::first().
     */
    public function pendingAppointments(): HasMany
    {
        return $this->hasMany(EmployeeAppointment::class)
            ->where('status', EmployeeAppointment::STATUS_PENDING)
            ->orderByDesc('created_at');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }

            if (empty($model->candidate_code)) {
                $model->candidate_code = static::generateCandidateCode($model->applied_at);
            }
        });
    }

    /**
     * Format: `CAN-YYYYMM-NNN` (e.g. CAN-202605-001). The numeric component
     * resets each month so recruiters can scan a code and immediately tell
     * when the candidate was received. Sequence is computed from the max
     * suffix already present for the same month — soft-deleted rows included
     * so withdrawn applications don't free their numbers for reuse.
     *
     * Concurrent callers may race; the `applications.candidate_code` unique
     * constraint is the final guard. Callers should be prepared to retry on
     * a 23505 violation (the submit flow already runs in a DB transaction).
     */
    public static function generateCandidateCode(mixed $reference = null): string
    {
        $month = Carbon::parse($reference ?: now())->format('Ym');
        // Prefix is tenant-configurable via Settings → Numbering (default "CAN-").
        $base = app(\App\Tenants\Modules\Settings\Services\SettingService::class)
            ->get('numbering.candidate_code_prefix');
        if (empty($base)) {
            $base = 'CAN-';
        }
        $prefix = "{$base}{$month}-";

        $rows = static::withTrashed()
            ->where('candidate_code', 'like', $prefix . '%')
            ->pluck('candidate_code');

        $max = 0;
        $pattern = '/^' . preg_quote($prefix, '/') . '(\d+)$/';
        foreach ($rows as $code) {
            if (preg_match($pattern, (string) $code, $m)) {
                $n = (int) $m[1];
                if ($n > $max) {
                    $max = $n;
                }
            }
        }

        return sprintf('%s%03d', $prefix, $max + 1);
    }
}
