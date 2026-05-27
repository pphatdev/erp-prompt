<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class OpportunityProductSchedule extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'opportunity_product_schedules';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'opportunity_id', 'product_id', 'variant_id',
        'quantity', 'estimated_unit_price', 'cadence',
        'notes', 'tenant_id',
    ];

    protected $casts = [
        'quantity'             => 'decimal:2',
        'estimated_unit_price' => 'decimal:2',
    ];

    public const CADENCE_ONE_TIME = 'one_time';
    public const CADENCE_MONTHLY  = 'monthly';
    public const CADENCE_ANNUAL   = 'annual';
    public const CADENCES = [self::CADENCE_ONE_TIME, self::CADENCE_MONTHLY, self::CADENCE_ANNUAL];

    public function opportunity(): BelongsTo
    {
        return $this->belongsTo(Opportunity::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'variant_id');
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
