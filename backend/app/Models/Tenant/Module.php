<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Module extends Model
{
    use BelongsToTenant, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'slug', 'prefix', 'name', 'icon', 'description',
        'route', 'group', 'sort_order', 'is_active', 'is_core', 'parent_id', 'tenant_id',
    ];

    protected $casts = [
        'is_active'  => 'boolean',
        'is_core'    => 'boolean',
        'sort_order' => 'integer',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Module::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Module::class, 'parent_id')->orderBy('sort_order');
    }

    public function products(): BelongsToMany
    {
        return $this->belongsToMany(Product::class, 'product_modules');
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
