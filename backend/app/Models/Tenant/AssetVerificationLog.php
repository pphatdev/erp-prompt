<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class AssetVerificationLog extends Model
{
    use BelongsToTenant, Auditable;

    public const STATUS_MATCHED     = 'matched';
    public const STATUS_MOVED       = 'moved';
    public const STATUS_DAMAGED     = 'damaged';
    public const STATUS_MISSING     = 'missing';
    public const STATUS_TRANSFERRED = 'transferred';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'campaign_id',
        'asset_id',
        'verified_by',
        'verified_at',
        'previous_condition',
        'new_condition',
        'previous_location_id',
        'new_location_id',
        'reconciliation_status',
        'notes',
        'tenant_id',
    ];

    protected $casts = [
        'verified_at' => 'datetime',
    ];

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(AssetAuditCampaign::class, 'campaign_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
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
