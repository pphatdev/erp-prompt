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

class EcomOrder extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'ecom_orders';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'order_number',
        'customer_id',
        'cart_id',
        'sales_order_id',
        'invoice_id',
        'status',
        'subtotal',
        'tax_amount',
        'shipping_amount',
        'discount_amount',
        'total_amount',
        'currency',
        'shipping_address',
        'billing_address',
        'carrier',
        'tracking_number',
        'shipped_at',
        'delivered_at',
        'placed_at',
        'paid_at',
        'cancelled_at',
        'cancel_reason',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'shipping_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'shipping_address' => 'array',
        'billing_address' => 'array',
        'shipped_at' => 'datetime',
        'delivered_at' => 'datetime',
        'placed_at' => 'datetime',
        'paid_at' => 'datetime',
        'cancelled_at' => 'datetime',
    ];

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID            = 'paid';
    public const STATUS_FULFILLING      = 'fulfilling';
    public const STATUS_SHIPPED         = 'shipped';
    public const STATUS_DELIVERED       = 'delivered';
    public const STATUS_CANCELLED       = 'cancelled';
    public const STATUS_REFUNDED        = 'refunded';
    public const STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAID,
        self::STATUS_FULFILLING,
        self::STATUS_SHIPPED,
        self::STATUS_DELIVERED,
        self::STATUS_CANCELLED,
        self::STATUS_REFUNDED,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(EcomCustomer::class, 'customer_id');
    }

    public function cart(): BelongsTo
    {
        return $this->belongsTo(EcomCart::class, 'cart_id');
    }

    public function salesOrder(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'sales_order_id');
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class, 'invoice_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EcomOrderItem::class, 'order_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(EcomPayment::class, 'order_id');
    }

    public function refunds(): HasMany
    {
        return $this->hasMany(EcomRefund::class, 'order_id');
    }

    public function isRefundable(): bool
    {
        return in_array($this->status, [
            self::STATUS_PAID,
            self::STATUS_FULFILLING,
            self::STATUS_SHIPPED,
            self::STATUS_DELIVERED,
        ], true);
    }

    public function isPaid(): bool
    {
        return $this->status !== self::STATUS_PENDING_PAYMENT
            && $this->status !== self::STATUS_CANCELLED;
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
