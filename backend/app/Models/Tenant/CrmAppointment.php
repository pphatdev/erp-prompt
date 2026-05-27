<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CrmAppointment extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'crm_appointments';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'subject', 'starts_at', 'ends_at', 'location', 'attendees', 'notes',
        'opportunity_id', 'lead_id', 'actor_id',
        'status', 'cancel_reason', 'completed_at', 'cancelled_at',
        'tenant_id',
    ];

    protected $casts = [
        'starts_at'    => 'datetime',
        'ends_at'      => 'datetime',
        'attendees'    => 'array',
        'completed_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public const STATUS_SCHEDULED = 'scheduled';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_NO_SHOW   = 'no_show';
    public const STATUSES         = [
        self::STATUS_SCHEDULED, self::STATUS_COMPLETED,
        self::STATUS_CANCELLED, self::STATUS_NO_SHOW,
    ];
    public const TERMINAL_STATUSES = [
        self::STATUS_COMPLETED, self::STATUS_CANCELLED, self::STATUS_NO_SHOW,
    ];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function isScheduled(): bool
    {
        return $this->status === self::STATUS_SCHEDULED;
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, self::TERMINAL_STATUSES, true);
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
