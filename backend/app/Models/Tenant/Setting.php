<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * Per-tenant configuration value.
 *
 * Lookups happen by `key` (unique per tenant). Use {@see SettingService} —
 * direct model use bypasses the in-memory cache.
 */
class Setting extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'tenant_settings';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'key',
        'value',
        'group',
        'type',
        'label',
        'description',
        'is_public',
        'tenant_id',
    ];

    protected $casts = [
        'value' => 'json',
        'is_public' => 'boolean',
    ];

    public const TYPES = ['string', 'json', 'boolean', 'integer', 'color', 'url'];

    public const GROUPS = ['branding', 'locale', 'notifications', 'security', 'numbering', 'platform', 'general'];

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }

            if (empty($model->group) && !empty($model->key)) {
                $model->group = Str::before($model->key, '.') ?: 'general';
            }
        });
    }
}
