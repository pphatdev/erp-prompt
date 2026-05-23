<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\Auditable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'sku',
        'name',
        'product_type',
        'description',
        'description_long',
        'unit_price',
        'minimum_stock_level',
        'is_active',
        'tenant_id',
    ];

    protected $casts = [
        'unit_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Drives the fulfillment split in OrderService::confirm. Hardware items
     * deduct from stock; software items provision a subscription. Both
     * paths still create an invoice for the finance side.
     */
    public const TYPE_HARDWARE = 'hardware';
    public const TYPE_SOFTWARE = 'software';
    public const TYPES = [self::TYPE_HARDWARE, self::TYPE_SOFTWARE];

    public function modules(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(Module::class, 'product_modules');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    /**
     * Calculate current stock by aggregating movements.
     */
    public function currentStock(): int
    {
        return $this->stockMovements()->sum('quantity');
    }

    public function isSoftware(): bool
    {
        return $this->product_type === self::TYPE_SOFTWARE;
    }

    public function isHardware(): bool
    {
        return $this->product_type === self::TYPE_HARDWARE;
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
