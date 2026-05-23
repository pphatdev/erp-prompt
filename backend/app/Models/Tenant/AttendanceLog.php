<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class AttendanceLog extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    public const STATUS_PRESENT      = 'present';
    public const STATUS_LATE         = 'late';
    public const STATUS_EARLY_OUT    = 'early_out';
    public const STATUS_HALF_DAY     = 'half_day';
    public const STATUS_ABSENT       = 'absent';
    public const STATUS_PAID_LEAVE   = 'paid_leave';
    public const STATUS_UNPAID_LEAVE = 'unpaid_leave';
    public const STATUS_WEEKEND      = 'weekend';
    public const STATUS_HOLIDAY      = 'holiday';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'employee_id',
        'date',
        'check_in',
        'check_out',
        'status',
        'check_in_ip',
        'check_out_ip',
        'check_in_lat',
        'check_in_lon',
        'check_out_lat',
        'check_out_lon',
        'tenant_id',
    ];

    protected $casts = [
        'date' => 'date',
        'check_in' => 'datetime',
        'check_out' => 'datetime',
        'check_in_lat' => 'float',
        'check_in_lon' => 'float',
        'check_out_lat' => 'float',
        'check_out_lon' => 'float',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
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
