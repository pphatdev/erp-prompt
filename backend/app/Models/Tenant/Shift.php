<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Shift extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'start_time',
        'end_time',
        'grace_period_minutes',
        'half_day_threshold_minutes',
        'tenant_id',
    ];

    protected $casts = [
        // Cast TIME columns to plain strings — Carbon stamping confuses
        // payroll math because Postgres returns "08:00:00" without a date.
        'start_time' => 'string',
        'end_time' => 'string',
        'grace_period_minutes' => 'integer',
        'half_day_threshold_minutes' => 'integer',
    ];

    public function assignments(): HasMany
    {
        return $this->hasMany(EmployeeShift::class);
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
