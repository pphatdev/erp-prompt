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

class Appraisal extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'employee_id',
        'reviewer_id',
        'cycle',
        'period_start',
        'period_end',
        'overall_rating',
        'strengths',
        'improvements',
        'goals',
        'status',
        'submitted_at',
        'reviewed_at',
        'tenant_id',
    ];

    protected $casts = [
        'period_start' => 'date',
        'period_end' => 'date',
        'submitted_at' => 'datetime',
        'reviewed_at' => 'datetime',
        'overall_rating' => 'decimal:2',
        'goals' => 'array',
    ];

    /**
     * Status transitions are configured per tenant in the `workflow_statuses`
     * table (module = 'hrm.appraisal'). See WorkflowStatusService.
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'reviewer_id');
    }

    public function peerFeedbacks(): HasMany
    {
        return $this->hasMany(AppraisalPeerFeedback::class)->orderBy('invited_at');
    }

    /**
     * Aggregate OKR progress from the `goals` JSON column. Expected shape:
     *   [{ id, objective, weight, keyResults: [{ description, target, current, weight }] }, ...]
     *
     * Returns a 0..100 percentage. Goals with no key results contribute 0.
     * Goal-level weights are used; missing weights default to equal weight
     * per goal. Within a goal, key-result weights default to equal weight.
     */
    public function okrProgress(): float
    {
        $goals = is_array($this->goals) ? $this->goals : [];
        if ($goals === []) {
            return 0.0;
        }

        $totalGoalWeight = 0.0;
        $weightedSum    = 0.0;

        foreach ($goals as $goal) {
            $goalWeight = (float) ($goal['weight'] ?? (100 / max(1, count($goals))));
            $totalGoalWeight += $goalWeight;

            $keyResults = is_array($goal['keyResults'] ?? null) ? $goal['keyResults'] : [];
            if ($keyResults === []) {
                continue;
            }

            $totalKrWeight = 0.0;
            $krWeightedProgress = 0.0;
            foreach ($keyResults as $kr) {
                $target  = (float) ($kr['target']  ?? 0);
                $current = (float) ($kr['current'] ?? 0);
                $weight  = (float) ($kr['weight']  ?? (100 / max(1, count($keyResults))));
                $totalKrWeight += $weight;

                $progress = $target > 0 ? min(100.0, ($current / $target) * 100) : 0.0;
                $krWeightedProgress += $progress * $weight;
            }

            $goalProgress = $totalKrWeight > 0 ? $krWeightedProgress / $totalKrWeight : 0.0;
            $weightedSum += $goalProgress * $goalWeight;
        }

        if ($totalGoalWeight <= 0) {
            return 0.0;
        }

        return round($weightedSum / $totalGoalWeight, 2);
    }

    /**
     * Convenience accessor for the average peer-feedback rating across all
     * submitted rows. Returns null when no submitted peer feedback exists.
     */
    public function averagePeerRating(): ?float
    {
        $rows = $this->peerFeedbacks->where('status', AppraisalPeerFeedback::STATUS_SUBMITTED);
        if ($rows->isEmpty()) {
            return null;
        }
        $vals = $rows->pluck('rating')->filter(fn ($v) => $v !== null);
        if ($vals->isEmpty()) {
            return null;
        }
        return round((float) $vals->avg(), 2);
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
