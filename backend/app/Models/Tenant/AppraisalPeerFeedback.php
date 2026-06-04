<?php

declare(strict_types=1);

namespace App\Models\Tenant;

use App\Models\Traits\Auditable;
use App\Models\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * One peer's contribution to a 360-degree appraisal. Lifecycle:
 *   invited (admin sends the request) -> submitted | declined.
 *
 * Aggregation lives in PerformanceService::aggregatePeerFeedback —
 * average rating + submitted count + pending count — and the result
 * blends into the final appraisal score when the
 * `hrm.appraisal.peer_evaluation_weight` setting is configured.
 */
class AppraisalPeerFeedback extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'appraisal_peer_feedbacks';

    protected $keyType = 'string';
    public $incrementing = false;

    public const STATUS_INVITED   = 'invited';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_DECLINED  = 'declined';

    protected $fillable = [
        'appraisal_id',
        'reviewer_id',
        'rating',
        'strengths',
        'concerns',
        'notes',
        'status',
        'invited_at',
        'submitted_at',
        'tenant_id',
    ];

    protected $casts = [
        'rating'       => 'decimal:1',
        'invited_at'   => 'datetime',
        'submitted_at' => 'datetime',
    ];

    public function appraisal(): BelongsTo
    {
        return $this->belongsTo(Appraisal::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
            if (empty($model->invited_at)) {
                $model->invited_at = now();
            }
        });
    }
}
