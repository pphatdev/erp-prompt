<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PosPayment extends Model
{
    use BelongsToTenant;

    protected $table = 'pos_payments';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'order_id',
        'payment_method',
        'amount',
        'tendered',
        'change_due',
        'reference_number',
        'currency',
        'tenant_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'tendered' => 'decimal:2',
        'change_due' => 'decimal:2',
    ];

    public const METHOD_CASH   = 'cash';
    public const METHOD_CARD   = 'card';
    public const METHOD_WALLET = 'wallet';
    public const METHOD_MANUAL = 'manual';
    public const PAYMENT_METHODS = [
        self::METHOD_CASH,
        self::METHOD_CARD,
        self::METHOD_WALLET,
        self::METHOD_MANUAL,
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(PosOrder::class, 'order_id');
    }

    public function isCash(): bool
    {
        return $this->payment_method === self::METHOD_CASH;
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
