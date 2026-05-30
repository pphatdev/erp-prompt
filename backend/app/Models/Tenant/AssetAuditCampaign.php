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

class AssetAuditCampaign extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    public const STATUS_DRAFT     = 'draft';
    public const STATUS_ACTIVE    = 'active';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'frequency',
        'starts_at',
        'ends_at',
        'status',
        'assigned_to',
        'expected_asset_count',
        'started_at',
        'completed_at',
        'tenant_id',
    ];

    protected $casts = [
        'starts_at'             => 'date',
        'ends_at'               => 'date',
        'started_at'            => 'datetime',
        'completed_at'          => 'datetime',
        'expected_asset_count'  => 'integer',
    ];

    public function verifications(): HasMany
    {
        return $this->hasMany(AssetVerificationLog::class, 'campaign_id');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
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
