<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CashAdvanceSettlementLine extends Model
{
    use BelongsToTenant, Auditable;

    protected $table = 'cash_advance_settlement_lines';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'settlement_id', 'account_id',
        'description', 'amount', 'receipt_attachment',
        'tenant_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(CashAdvanceSettlement::class, 'settlement_id');
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
        });
    }
}
