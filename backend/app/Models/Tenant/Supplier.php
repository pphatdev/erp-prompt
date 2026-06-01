<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Supplier extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code', 'name', 'contact_name',
        'email', 'phone', 'address', 'website', 'tax_id',
        'payment_terms', 'lead_time_days', 'rating',
        'is_active', 'notes',
        // AP / Vendor extension — see migration 000078.
        'is_vendor', 'payment_method',
        'bank_name', 'bank_account_name', 'bank_account_number', 'bank_swift',
        'default_payable_account_id', 'default_expense_account_id',
        'tenant_id',
    ];

    protected $casts = [
        'lead_time_days' => 'integer',
        'rating'         => 'integer',
        'is_active'      => 'boolean',
        'is_vendor'      => 'boolean',
    ];

    public function defaultPayableAccount()
    {
        return $this->belongsTo(Account::class, 'default_payable_account_id');
    }

    public function defaultExpenseAccount()
    {
        return $this->belongsTo(Account::class, 'default_expense_account_id');
    }

    public function isActive(): bool
    {
        return (bool) $this->is_active;
    }

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (!isset($model->is_active)) $model->is_active = true;
        });
    }
}
