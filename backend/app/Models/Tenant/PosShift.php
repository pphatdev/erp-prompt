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

/**
 * Cashier shift session on a terminal. Float-controls and variance flow:
 *
 *   open                 - drawer is taking sales
 *   closed               - drawer counted, variance == 0
 *   variance_pending     - drawer counted, variance != 0, awaiting supervisor
 *   reconciled           - supervisor approved; variance journal posted
 *
 * Sums of cash payments + skims feed `expected_cash`; the cashier types
 * `closing_cash`; service computes variance.
 */
class PosShift extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'pos_shifts';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'terminal_id',
        'cashier_id',
        'opened_at',
        'closed_at',
        'opening_float',
        'expected_cash',
        'closing_cash',
        'variance',
        'status',
        'reconciled_by',
        'reconciled_at',
        'variance_journal_entry_id',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'opening_float' => 'decimal:2',
        'expected_cash' => 'decimal:2',
        'closing_cash' => 'decimal:2',
        'variance' => 'decimal:2',
    ];

    public const STATUS_OPEN              = 'open';
    public const STATUS_CLOSED            = 'closed';
    public const STATUS_VARIANCE_PENDING  = 'variance_pending';
    public const STATUS_RECONCILED        = 'reconciled';
    public const STATUSES = [
        self::STATUS_OPEN,
        self::STATUS_CLOSED,
        self::STATUS_VARIANCE_PENDING,
        self::STATUS_RECONCILED,
    ];

    public function terminal(): BelongsTo
    {
        return $this->belongsTo(PosTerminal::class, 'terminal_id');
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function reconciledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reconciled_by');
    }

    public function varianceJournalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class, 'variance_journal_entry_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class, 'shift_id');
    }

    public function isOpen(): bool
    {
        return $this->status === self::STATUS_OPEN;
    }

    public function isClosed(): bool
    {
        return in_array($this->status, [
            self::STATUS_CLOSED,
            self::STATUS_VARIANCE_PENDING,
            self::STATUS_RECONCILED,
        ], true);
    }

    public function hasVariance(): bool
    {
        return $this->variance !== null && (float) $this->variance !== 0.0;
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
