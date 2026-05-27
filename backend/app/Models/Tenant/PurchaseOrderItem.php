<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PurchaseOrderItem extends Model
{
    use BelongsToTenant, Auditable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'purchase_order_id', 'product_id', 'variant_id',
        'product_name', 'variant_sku',
        'ordered_qty', 'received_qty', 'unit_cost', 'line_total',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'ordered_qty'  => 'decimal:2',
        'received_qty' => 'decimal:2',
        'unit_cost'    => 'decimal:2',
        'line_total'   => 'decimal:2',
    ];

    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
    }

    /** Units still pending receipt (ordered − received). Never negative. */
    public function outstandingQty(): float
    {
        return max(0.0, (float) $this->ordered_qty - (float) $this->received_qty);
    }

    public function isFullyReceived(): bool
    {
        return $this->outstandingQty() <= 0;
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
