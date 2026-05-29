<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Catalog row for the Vehicle Make/Model picker.
 *
 * Vehicles themselves still carry free-text `make` + `model` columns — this
 * model is a SUGGESTION CATALOG that drives the picker on the Vehicle Create
 * form. Adopting the catalog doesn't migrate existing free-text rows; users
 * just get autocomplete on new entries.
 */
class VehicleModel extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'make',
        'model',
        'body_type',
        'fuel_type',
        'notes',
        'tenant_id',
    ];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }
}
