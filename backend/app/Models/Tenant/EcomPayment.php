<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class EcomPayment extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'ecom_payments';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'order_id',
        'provider',
        'provider_charge_id',
        'status',
        'amount',
        'gateway_fee',
        'currency',
        'client_uuid',
        'raw_payload',
        'failure_code',
        'failure_message',
        'captured_at',
        'failed_at',
        'tenant_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'gateway_fee' => 'decimal:2',
        'raw_payload' => 'array',
        'captured_at' => 'datetime',
        'failed_at' => 'datetime',
    ];

    public const PROVIDER_STRIPE = 'stripe';
    public const PROVIDER_ABA    = 'aba';
    public const PROVIDER_WING   = 'wing';
    public const PROVIDER_MANUAL = 'manual';

    public const STATUS_PENDING         = 'pending';
    public const STATUS_SUCCEEDED       = 'succeeded';
    public const STATUS_FAILED          = 'failed';
    public const STATUS_REFUNDED        = 'refunded';
    public const STATUS_PARTIAL_REFUND  = 'partial_refund';

    public function order(): BelongsTo
    {
        return $this->belongsTo(EcomOrder::class, 'order_id');
    }

    public function isSucceeded(): bool
    {
        return $this->status === self::STATUS_SUCCEEDED;
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
