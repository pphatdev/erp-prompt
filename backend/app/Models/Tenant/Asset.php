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

class Asset extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'asset_code',
        'serial_number',
        'name',
        'description',
        'category',
        'vendor_name',
        'purchase_date',
        'purchase_price',
        'salvage_value',
        'accumulated_depreciation',
        'useful_life_months',
        'depreciation_method',
        'status',
        'condition',
        'qr_code_url',
        'notes',
        'custodian_employee_id',
        'location_id',
        'tenant_id',
    ];

    protected $casts = [
        'purchase_date'            => 'date',
        'purchase_price'           => 'decimal:2',
        'salvage_value'            => 'decimal:2',
        'accumulated_depreciation' => 'decimal:2',
        'useful_life_months'       => 'integer',
    ];

    public function custodian(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'custodian_employee_id');
    }

    public function depreciationLogs(): HasMany
    {
        return $this->hasMany(DepreciationLog::class);
    }

    public function revaluations(): HasMany
    {
        return $this->hasMany(AssetRevaluationLog::class);
    }

    public function disposals(): HasMany
    {
        return $this->hasMany(AssetDisposal::class);
    }

    public function verifications(): HasMany
    {
        return $this->hasMany(AssetVerificationLog::class);
    }

    public function getNetBookValueAttribute(): float
    {
        return round((float) $this->purchase_price - (float) $this->accumulated_depreciation, 2);
    }

    public function getDepreciableBaseAttribute(): float
    {
        return round((float) $this->purchase_price - (float) $this->salvage_value, 2);
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
