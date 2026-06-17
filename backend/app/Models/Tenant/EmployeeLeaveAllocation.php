<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Per-employee, per-leave-type, per-year allocation ledger.
 *
 * Phase 12 replaces LeaveService's on-the-fly accrual math with a stored
 * counter row. Each row tracks:
 *   - `allocated_days`   total entitlement for the year (annual or accrual)
 *   - `used_days`        approved leaves that consumed balance
 *   - `pending_days`     pending requests holding balance until decided
 *
 * Remaining = allocated - used - pending. The model exposes a computed
 * accessor for callers + resource serialization.
 */
class EmployeeLeaveAllocation extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'year',
        'allocated_days',
        'used_days',
        'pending_days',
        'note',
        'tenant_id',
    ];

    protected $casts = [
        'year'           => 'integer',
        'allocated_days' => 'float',
        'used_days'      => 'float',
        'pending_days'   => 'float',
    ];

    protected $attributes = [
        'allocated_days' => 0,
        'used_days'      => 0,
        'pending_days'   => 0,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    /**
     * Allocated minus held (used + pending). Floored at 0 by default;
     * pass false to expose the raw signed value (useful for surfacing
     * over-allocation in admin views).
     */
    public function remainingDays(bool $floor = true): float
    {
        $raw = round(
            (float) $this->allocated_days
                - (float) $this->used_days
                - (float) $this->pending_days,
            2
        );
        return $floor ? max(0.0, $raw) : $raw;
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
