<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Leave extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    public const SESSION_FULL_DAY  = 'full_day';
    public const SESSION_MORNING   = 'morning';
    public const SESSION_AFTERNOON = 'afternoon';

    public const HALF_DAY_SESSIONS = [self::SESSION_MORNING, self::SESSION_AFTERNOON];

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'start_date',
        'end_date',
        'days',
        'leave_session',
        'reason',
        'status',
        'tenant_id',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date'   => 'date',
        // Decimal so half-day requests (0.5) round-trip without truncation.
        'days'       => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function approvalRequests(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'requestable');
    }

    public function activeApprovalRequest(): ?ApprovalRequest
    {
        if ($this->relationLoaded('approvalRequests')) {
            return $this->approvalRequests
                ->where('status', 'pending')
                ->sortByDesc('created_at')
                ->first();
        }

        return $this->approvalRequests()->where('status', 'pending')->latest()->first();
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
