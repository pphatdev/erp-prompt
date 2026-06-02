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

class EcomRefund extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'ecom_refunds';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'refund_number',
        'order_id',
        'payment_id',
        'credit_note_id',
        'status',
        'is_partial',
        'amount',
        'currency',
        'reason',
        'rejection_reason',
        'provider_refund_id',
        'requested_by',
        'requested_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'completed_at',
        'tenant_id',
    ];

    protected $casts = [
        'is_partial' => 'boolean',
        'amount' => 'decimal:2',
        'requested_at' => 'datetime',
        'approved_at' => 'datetime',
        'rejected_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public const STATUS_REQUESTED  = 'requested';
    public const STATUS_APPROVED   = 'approved';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED  = 'completed';
    public const STATUS_REJECTED   = 'rejected';
    public const STATUSES = [
        self::STATUS_REQUESTED,
        self::STATUS_APPROVED,
        self::STATUS_PROCESSING,
        self::STATUS_COMPLETED,
        self::STATUS_REJECTED,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcomOrder::class, 'order_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(EcomPayment::class, 'payment_id');
    }

    public function creditNote(): BelongsTo
    {
        return $this->belongsTo(CreditNote::class, 'credit_note_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EcomRefundItem::class, 'refund_id');
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_REJECTED], true);
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
