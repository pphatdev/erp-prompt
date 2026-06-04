<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One row per (target, day-of-week). Resolved by {@see WorkScheduleService}
 * which walks employee -> department -> global and picks the most specific
 * row for a given date. Targets are referenced by UUID without a hard FK
 * since the column points at different tables depending on `target_type`;
 * the service is responsible for validating target existence on write.
 */
class WorkSchedule extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'work_schedules';

    protected $keyType = 'string';
    public $incrementing = false;

    public const TARGET_GLOBAL     = 'global';
    public const TARGET_DEPARTMENT = 'department';
    public const TARGET_EMPLOYEE   = 'employee';

    public const TARGET_TYPES = [
        self::TARGET_GLOBAL,
        self::TARGET_DEPARTMENT,
        self::TARGET_EMPLOYEE,
    ];

    /** Precedence used by the resolver — most specific first. */
    public const PRECEDENCE = [
        self::TARGET_EMPLOYEE,
        self::TARGET_DEPARTMENT,
        self::TARGET_GLOBAL,
    ];

    protected $fillable = [
        'target_type',
        'target_id',
        'day_of_week',
        'is_work_day',
        'intervals',
        'tenant_id',
    ];

    protected $casts = [
        'day_of_week' => 'integer',
        'is_work_day' => 'boolean',
        'intervals'   => 'array',
    ];

    /**
     * Total scheduled minutes for the day, summed across intervals. Returns
     * 0 on non-work days or when no intervals are configured.
     */
    public function totalMinutes(): int
    {
        if (!$this->is_work_day || !is_array($this->intervals)) {
            return 0;
        }
        $total = 0;
        foreach ($this->intervals as $row) {
            $start = self::parseTime($row['start'] ?? null);
            $end   = self::parseTime($row['end'] ?? null);
            if ($start === null || $end === null || $end <= $start) {
                continue;
            }
            $total += $end - $start;
        }
        return $total;
    }

    public function totalHours(): float
    {
        return round($this->totalMinutes() / 60, 2);
    }

    /**
     * Parse 'HH:MM' (24-hour) into minutes-from-midnight. Returns null on
     * unparseable input so totalMinutes() can skip the entry without
     * propagating a 0-minute pseudo-interval.
     */
    public static function parseTime(?string $time): ?int
    {
        if (!is_string($time) || !preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
            return null;
        }
        return ((int) $m[1]) * 60 + (int) $m[2];
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
