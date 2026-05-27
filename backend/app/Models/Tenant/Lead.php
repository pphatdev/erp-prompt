<?php

namespace App\Models\Tenant;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Traits\BelongsToTenant;
use App\Models\Traits\Auditable;
use Illuminate\Support\Str;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Lead extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'title',
        'first_name',
        'last_name',
        'email',
        'phone',
        'customer_type',
        'address',
        'customer_id',
        'estimated_value',
        'status',
        'source',
        'tenant_id',
    ];

    public const TYPE_INDIVIDUAL = 'individual';
    public const TYPE_BUSINESS   = 'business';
    public const TYPE_TENANT     = 'tenant';
    public const TYPES = [self::TYPE_INDIVIDUAL, self::TYPE_BUSINESS, self::TYPE_TENANT];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /** Composed display name, falling back to title when name parts are missing. */
    public function getFullNameAttribute(): string
    {
        $name = trim(($this->first_name ?? '') . ' ' . ($this->last_name ?? ''));
        return $name !== '' ? $name : (string) ($this->title ?? '');
    }

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            // Auto-derive title from name so legacy consumers (Opportunity
            // Kanban etc.) that read lead.title keep getting a sensible label.
            if (empty($model->title)) {
                $name = trim(($model->first_name ?? '') . ' ' . ($model->last_name ?? ''));
                if ($name !== '') $model->title = $name;
            }
        });
    }
}
