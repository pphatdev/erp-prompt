<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class CrmActivity extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'crm_activities';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'trackable_type', 'trackable_id',
        'activity_type', 'subject', 'description',
        'due_date', 'status', 'actor_id', 'tenant_id',
    ];

    protected $casts = [
        'due_date' => 'datetime',
    ];

    public const TYPES    = ['call', 'email', 'meeting', 'note', 'task'];
    public const STATUSES = ['pending', 'completed', 'cancelled'];

    public function trackable(): MorphTo
    {
        return $this->morphTo();
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
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
