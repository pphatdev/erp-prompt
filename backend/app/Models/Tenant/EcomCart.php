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

class EcomCart extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'ecom_carts';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'customer_id',
        'session_token',
        'status',
        'subtotal',
        'currency',
        'expires_at',
        'converted_at',
        'converted_order_id',
        'tenant_id',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'expires_at' => 'datetime',
        'converted_at' => 'datetime',
    ];

    public const STATUS_ACTIVE    = 'active';
    public const STATUS_MERGED    = 'merged';
    public const STATUS_EXPIRED   = 'expired';
    public const STATUS_CONVERTED = 'converted';
    public const STATUSES = [
        self::STATUS_ACTIVE,
        self::STATUS_MERGED,
        self::STATUS_EXPIRED,
        self::STATUS_CONVERTED,
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(EcomCustomer::class, 'customer_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(EcomCartItem::class, 'cart_id');
    }

    public function convertedOrder(): BelongsTo
    {
        return $this->belongsTo(EcomOrder::class, 'converted_order_id');
    }

    public function isGuest(): bool
    {
        return $this->customer_id === null;
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
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
