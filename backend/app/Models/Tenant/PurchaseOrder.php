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

class PurchaseOrder extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'po_number', 'supplier_id', 'warehouse_id', 'status',
        'order_date', 'expected_at', 'received_at',
        'subtotal', 'tax_amount', 'total_amount', 'notes',
        'ordered_by', 'submitted_by', 'submitted_at',
        'approved_by', 'approved_at',
        'cancelled_by', 'cancelled_at', 'cancel_reason',
        'tenant_id',
    ];

    protected $casts = [
        'order_date'   => 'date',
        'expected_at'  => 'date',
        'received_at'  => 'datetime',
        'submitted_at' => 'datetime',
        'approved_at'  => 'datetime',
        'cancelled_at' => 'datetime',
        'subtotal'     => 'decimal:2',
        'tax_amount'   => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_APPROVED  = 'approved';
    public const STATUS_RECEIVING = 'receiving';
    public const STATUS_RECEIVED  = 'received';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_APPROVED,
        self::STATUS_RECEIVING, self::STATUS_RECEIVED, self::STATUS_CANCELLED,
    ];

    /** Open = anything that can still receive stock or be acted on. */
    public const OPEN_STATUSES = [
        self::STATUS_DRAFT, self::STATUS_SUBMITTED, self::STATUS_APPROVED, self::STATUS_RECEIVING,
    ];

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseOrderItem::class);
    }

    public function isDraft(): bool     { return $this->status === self::STATUS_DRAFT; }
    public function isSubmitted(): bool { return $this->status === self::STATUS_SUBMITTED; }
    public function isApproved(): bool  { return $this->status === self::STATUS_APPROVED; }
    public function isReceived(): bool  { return $this->status === self::STATUS_RECEIVED; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }

    public function isReceivable(): bool
    {
        return in_array($this->status, [self::STATUS_APPROVED, self::STATUS_RECEIVING], true);
    }

    /** Cancellable while nothing has been received yet. */
    public function isCancellable(): bool
    {
        if ($this->isCancelled() || $this->isReceived()) return false;
        if ($this->status === self::STATUS_RECEIVING) return false;
        return true;
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
