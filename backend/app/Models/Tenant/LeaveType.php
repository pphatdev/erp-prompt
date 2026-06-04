<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class LeaveType extends Model
{
    use BelongsToTenant, Auditable;

    protected $keyType = 'string';
    public $incrementing = false;

    public const GENDER_ANY = 'any';
    public const GENDER_MALE = 'male';
    public const GENDER_FEMALE = 'female';

    public const GENDERS = [self::GENDER_ANY, self::GENDER_MALE, self::GENDER_FEMALE];

    protected $fillable = [
        'name',
        'annual_allowance',
        'applicable_gender',
        'tenant_id',
    ];

    protected $attributes = [
        'applicable_gender' => self::GENDER_ANY,
    ];

    protected $casts = [
        'annual_allowance' => 'integer',
    ];

    public function leaves(): HasMany
    {
        return $this->hasMany(Leave::class);
    }

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
