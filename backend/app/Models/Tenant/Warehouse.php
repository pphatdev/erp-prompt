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

class Warehouse extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'code', 'name', 'location',
        'manager_id', 'address_line', 'city', 'country',
        'capacity', 'is_active', 'notes',
        'tenant_id',
    ];

    protected $casts = [
        'capacity'  => 'integer',
        'is_active' => 'boolean',
    ];

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function stockMovements(): HasMany
    {
        return $this->hasMany(StockMovement::class);
    }

    /**
     * Net on-hand units across all products in this warehouse — sum of the
     * stock-movements ledger filtered to this warehouse. Cheap helper for
     * list cards / capacity gauges.
     */
    public function onHandStock(): int
    {
        return (int) $this->stockMovements()->sum('quantity');
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
