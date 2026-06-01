<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class BillLine extends Model
{
    use BelongsToTenant, Auditable;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'bill_id', 'account_id',
        'description', 'quantity', 'unit_price', 'line_total',
        'tenant_id',
    ];

    protected $casts = [
        'quantity'   => 'decimal:3',
        'unit_price' => 'decimal:2',
        'line_total' => 'decimal:2',
    ];

    public function bill(): BelongsTo
    {
        return $this->belongsTo(Bill::class);
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            // Always recompute line_total server-side so client input can't
            // drift the math.
            $model->line_total = round((float) $model->quantity * (float) $model->unit_price, 2);
        });

        static::updating(function (self $model) {
            if ($model->isDirty(['quantity', 'unit_price'])) {
                $model->line_total = round((float) $model->quantity * (float) $model->unit_price, 2);
            }
        });
    }
}
