<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
