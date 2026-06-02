<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class EcomRefundItem extends Model
{
    use BelongsToTenant;

    protected $table = 'ecom_refund_items';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'refund_id',
        'order_item_id',
        'quantity',
        'unit_price',
        'line_total',
        'restock',
        'tenant_id',
    ];

    protected $casts = [
        'quantity' => 'decimal:2',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
        'restock' => 'boolean',
    ];

    public function refund(): BelongsTo
    {
        return $this->belongsTo(EcomRefund::class, 'refund_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(EcomOrderItem::class, 'order_item_id');
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
