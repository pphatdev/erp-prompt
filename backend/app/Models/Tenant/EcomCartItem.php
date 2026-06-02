<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EcomCartItem extends Model
{
    use BelongsToTenant;

    protected $table = 'ecom_cart_items';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'cart_id',
        'product_id',
        'variant_id',
        'quantity',
        'unit_price',
        'line_total',
        'reservation_id',
        'tenant_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(EcomCart::class, 'cart_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    public function reservation(): BelongsTo
    {
        return $this->belongsTo(StockReservation::class, 'reservation_id');
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
