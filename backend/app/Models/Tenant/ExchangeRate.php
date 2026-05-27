<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ExchangeRate extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'base_currency',
        'quote_currency',
        'rate',
        'effective_date',
        'source',
        'notes',
        'is_active',
        'tenant_id',
    ];

    protected $casts = [
        'rate'           => 'decimal:6',
        'effective_date' => 'date',
        'is_active'      => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (!empty($model->base_currency)) {
                $model->base_currency = strtoupper($model->base_currency);
            }
            if (!empty($model->quote_currency)) {
                $model->quote_currency = strtoupper($model->quote_currency);
            }
        });

        static::updating(function (self $model) {
            if (!empty($model->base_currency)) {
                $model->base_currency = strtoupper($model->base_currency);
            }
            if (!empty($model->quote_currency)) {
                $model->quote_currency = strtoupper($model->quote_currency);
            }
        });
    }
}
