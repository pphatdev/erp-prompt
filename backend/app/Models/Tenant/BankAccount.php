<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class BankAccount extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'account_id',
        'name',
        'bank_name',
        'branch',
        'account_number',
        'account_holder',
        'swift',
        'iban',
        'currency',
        'opening_balance',
        'last_reconciled_at',
        'last_reconciled_balance',
        'notes',
        'is_active',
        'is_default',
        'tenant_id',
    ];

    protected $casts = [
        'opening_balance'         => 'decimal:2',
        'last_reconciled_balance' => 'decimal:2',
        'last_reconciled_at'      => 'datetime',
        'is_active'               => 'boolean',
        'is_default'              => 'boolean',
    ];

    public function glAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'account_id');
    }

    /**
     * Live book balance pulled from the linked GL Account. Single source of
     * truth — never cached here. Returns 0.0 if no GL link.
     */
    public function bookBalance(): float
    {
        return $this->glAccount ? (float) $this->glAccount->balance : 0.0;
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (!isset($model->is_active))  $model->is_active = true;
            if (!isset($model->is_default)) $model->is_default = false;
            if (!empty($model->currency))    $model->currency = strtoupper($model->currency);
        });

        static::updating(function (self $model) {
            if ($model->isDirty('currency') && !empty($model->currency)) {
                $model->currency = strtoupper($model->currency);
            }
        });
    }
}
