<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class PosTerminal extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'pos_terminals';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code',
        'name',
        'warehouse_id',
        'petty_cash_account_id',
        'location',
        'status',
        'notes',
        'tenant_id',
    ];

    public const STATUS_ACTIVE   = 'active';
    public const STATUS_DISABLED = 'disabled';
    public const STATUSES = [self::STATUS_ACTIVE, self::STATUS_DISABLED];

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function pettyCashAccount(): BelongsTo
    {
        return $this->belongsTo(Account::class, 'petty_cash_account_id');
    }

    public function shifts(): HasMany
    {
        return $this->hasMany(PosShift::class, 'terminal_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(PosOrder::class, 'terminal_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
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
