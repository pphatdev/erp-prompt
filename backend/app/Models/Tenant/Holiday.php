<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Holiday extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    public const TYPE_PUBLIC   = 'public';
    public const TYPE_COMPANY  = 'company';
    public const TYPE_OPTIONAL = 'optional';

    public const TYPES = [self::TYPE_PUBLIC, self::TYPE_COMPANY, self::TYPE_OPTIONAL];

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name', 'date', 'type', 'is_recurring', 'notes',
        'tenant_id',
    ];

    protected $casts = [
        'date'         => 'date',
        'is_recurring' => 'boolean',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->type)) $model->type = self::TYPE_PUBLIC;
        });
    }
}
