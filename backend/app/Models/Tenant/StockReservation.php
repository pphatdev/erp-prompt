<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class StockReservation extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'product_id', 'warehouse_id', 'variant_id',
        'quantity', 'reference',
        'status', 'expires_at',
        'committed_at', 'cancelled_at', 'expired_at', 'cancel_reason',
        'actor_id',
        'tenant_id',
    ];

    protected $casts = [
        'quantity'     => 'decimal:2',
        'expires_at'   => 'datetime',
        'committed_at' => 'datetime',
        'cancelled_at' => 'datetime',
        'expired_at'   => 'datetime',
    ];

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_COMMITTED = 'committed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_EXPIRED   = 'expired';

    public const STATUSES = [
        self::STATUS_ACTIVE, self::STATUS_COMMITTED,
        self::STATUS_CANCELLED, self::STATUS_EXPIRED,
    ];

    /** Anything that still holds stock against net availability. */
    public const HOLDING_STATUSES = [self::STATUS_ACTIVE];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function isActive(): bool   { return $this->status === self::STATUS_ACTIVE; }
    public function isCommitted(): bool { return $this->status === self::STATUS_COMMITTED; }
    public function isCancelled(): bool { return $this->status === self::STATUS_CANCELLED; }
    public function isExpired(): bool   { return $this->status === self::STATUS_EXPIRED; }
    public function isTerminal(): bool
    {
        return $this->status !== self::STATUS_ACTIVE;
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
