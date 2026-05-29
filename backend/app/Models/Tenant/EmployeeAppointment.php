<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Casts\EncryptedWithFallback;
use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EmployeeAppointment extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    public const STATUS_PENDING   = 'pending';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_REJECTED  = 'rejected';
    public const STATUS_CANCELLED = 'cancelled';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'application_id',
        'employee_id',
        'submitted_by',
        'first_name',
        'last_name',
        'email',
        'phone',
        'department_id',
        'position_id',
        'manager_id',
        'start_date',
        'base_salary',
        'employment_type',
        'notes',
        'status',
        'processed_at',
        'tenant_id',
    ];

    protected $casts = [
        'start_date'   => 'date',
        'base_salary'  => EncryptedWithFallback::class,
        'processed_at' => 'datetime',
    ];

    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'manager_id');
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function submitter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'submitted_by');
    }

    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'requestable');
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
