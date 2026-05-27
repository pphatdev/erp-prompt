<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Opportunity extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'title', 'lead_id', 'customer_id', 'stage',
        'estimated_value', 'probability', 'close_date',
        'loss_reason', 'notes', 'tenant_id',
    ];

    protected $casts = [
        'estimated_value' => 'decimal:2',
        'probability'     => 'integer',
        'close_date'      => 'date',
    ];

    public const STAGE_NEW         = 'new';
    public const STAGE_SCHEDULES   = 'schedules';
    public const STAGE_CONTACTED   = 'contacted';
    public const STAGE_QUALIFIED   = 'qualified';   // legacy
    public const STAGE_PROPOSAL    = 'proposal';    // legacy
    public const STAGE_NEGOTIATION = 'negotiation'; // legacy
    public const STAGE_WON         = 'won';
    public const STAGE_LOST        = 'lost';
    public const STAGES = [
        self::STAGE_NEW,
        self::STAGE_SCHEDULES,
        self::STAGE_CONTACTED,
        self::STAGE_QUALIFIED,
        self::STAGE_PROPOSAL,
        self::STAGE_NEGOTIATION,
        self::STAGE_WON,
        self::STAGE_LOST,
    ];
    public const TERMINAL_STAGES = [self::STAGE_WON, self::STAGE_LOST];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(Lead::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'trackable_id')
            ->where('trackable_type', self::class);
    }

    public function productSchedule(): HasMany
    {
        return $this->hasMany(OpportunityProductSchedule::class);
    }

    public function isTerminal(): bool
    {
        return in_array($this->stage, self::TERMINAL_STAGES, true);
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
