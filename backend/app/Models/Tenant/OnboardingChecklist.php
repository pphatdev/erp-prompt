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

/**
 * Provisioning todo list attached to an Offer (or created ad-hoc for an
 * Employee). Aggregates `total_tasks` / `completed_tasks` counters so the
 * dashboard can render progress without a JOIN-heavy query.
 *
 * Lifecycle: pending -> in_progress -> completed | cancelled. Status flips
 * happen in OnboardingService when individual OnboardingTask rows transition.
 */
class OnboardingChecklist extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'onboarding_checklists';

    protected $keyType = 'string';
    public $incrementing = false;

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_CANCELLED   = 'cancelled';

    protected $fillable = [
        'offer_id',
        'employee_id',
        'name',
        'status',
        'total_tasks',
        'completed_tasks',
        'target_completion_date',
        'completed_at',
        'tenant_id',
    ];

    protected $casts = [
        'total_tasks'            => 'integer',
        'completed_tasks'        => 'integer',
        'target_completion_date' => 'date',
        'completed_at'           => 'datetime',
    ];

    public function offer(): BelongsTo
    {
        return $this->belongsTo(Offer::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(OnboardingTask::class, 'checklist_id')->orderBy('sort_order');
    }

    public function progressPercent(): int
    {
        if ($this->total_tasks <= 0) {
            return 0;
        }
        return (int) round(($this->completed_tasks / $this->total_tasks) * 100);
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
