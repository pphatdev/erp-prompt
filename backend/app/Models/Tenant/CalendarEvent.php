<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Custom tenant calendar event. Used for ad-hoc entries that don't already
 * exist in leaves / shifts / CRM appointments - those source tables are
 * unioned at query time by CalendarEventService.
 *
 * Categories:
 *   general   - default catch-all (e.g. "Office closed for renovations")
 *   meeting   - all-hands, team sync, board meeting
 *   training  - workshop, certification, onboarding
 *   company   - founder's day, anniversary, blackout day
 *   personal  - employee-private entry (must set employee_id)
 *
 * The optional polymorphic `eventable` morphTo points back to a domain
 * object the event is anchored to (e.g. a CRM lead's calendar reminder).
 * v1 leaves this nullable everywhere; future controllers can populate it.
 */
class CalendarEvent extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'calendar_events';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'title',
        'description',
        'start_time',
        'end_time',
        'category',
        'is_all_day',
        'employee_id',
        'eventable_type',
        'eventable_id',
        'tenant_id',
    ];

    protected $casts = [
        'start_time' => 'datetime',
        'end_time' => 'datetime',
        'is_all_day' => 'boolean',
    ];

    public const CATEGORY_GENERAL  = 'general';
    public const CATEGORY_MEETING  = 'meeting';
    public const CATEGORY_TRAINING = 'training';
    public const CATEGORY_COMPANY  = 'company';
    public const CATEGORY_PERSONAL = 'personal';
    public const CATEGORIES = [
        self::CATEGORY_GENERAL,
        self::CATEGORY_MEETING,
        self::CATEGORY_TRAINING,
        self::CATEGORY_COMPANY,
        self::CATEGORY_PERSONAL,
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function eventable(): MorphTo
    {
        return $this->morphTo();
    }

    public function isPersonal(): bool
    {
        return $this->category === self::CATEGORY_PERSONAL;
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->category)) {
                $model->category = self::CATEGORY_GENERAL;
            }
        });
    }
}
