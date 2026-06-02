<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Holiday extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    public const TYPE_PUBLIC   = 'public';
    public const TYPE_COMPANY  = 'company';
    public const TYPE_OPTIONAL = 'optional';

    public const TYPES = [self::TYPE_PUBLIC, self::TYPE_COMPANY, self::TYPE_OPTIONAL];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name', 'date', 'type', 'is_recurring', 'notes',
        'overtime_multiplier', 'branch_id',
        'tenant_id',
    ];

    protected $casts = [
        'date'                => 'date',
        'is_recurring'        => 'boolean',
        'overtime_multiplier' => 'decimal:2',
    ];

    /**
     * True when the holiday falls on a Saturday or Sunday for the given
     * calendar year. Used by HolidayService::getCompensatoryDay to decide
     * whether to mint a Monday compensatory entry.
     *
     * For recurring holidays, the comparison uses MM-DD applied to $year.
     * For one-off holidays, the stored `date` is the ground truth.
     */
    public function isOnWeekend(?int $year = null): bool
    {
        $date = $this->resolveDateForYear($year ?? (int) date('Y'));
        $dow = (int) $date->format('N'); // 6=Saturday, 7=Sunday
        return $dow === 6 || $dow === 7;
    }

    /**
     * Returns the effective Carbon date for this holiday in the given year.
     * Recurring holidays anchor MM-DD on `$year`; one-off holidays keep
     * their stored date regardless of `$year`.
     */
    public function resolveDateForYear(int $year): \Carbon\CarbonImmutable
    {
        $base = \Carbon\CarbonImmutable::parse($this->date);
        return $this->is_recurring
            ? $base->setYear($year)
            : $base;
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->type)) $model->type = self::TYPE_PUBLIC;
        });
    }
}
