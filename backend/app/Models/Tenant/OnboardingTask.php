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
 * Single provisioning step on an OnboardingChecklist.
 *
 * `owner_role` (hr | it | finance | manager | facilities | other) is the
 * authority that must close the task. The service layer resolves
 * role -> user(s) at runtime so re-assigning the IT lead does not
 * require rewriting history.
 *
 * `due_offset_days` is relative to the Offer's `effective_date` and is
 * resolved into the concrete `due_date` column when OnboardingService
 * seeds the checklist.
 *
 * Lifecycle (per `hrm.onboarding_task` workflow_statuses module):
 *   pending -> in_progress -> completed | skipped.
 */
class OnboardingTask extends Model
{
    use BelongsToTenant, Auditable, SoftDeletes;

    protected $table = 'onboarding_tasks';

    protected $keyType = 'string';
    public $incrementing = false;

    public const STATUS_PENDING     = 'pending';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED   = 'completed';
    public const STATUS_SKIPPED     = 'skipped';

    public const ROLES = ['hr', 'it', 'finance', 'manager', 'facilities', 'other'];

    protected $fillable = [
        'checklist_id',
        'title',
        'description',
        'owner_role',
        'owner_user_id',
        'due_offset_days',
        'due_date',
        'status',
        'sort_order',
        'completed_at',
        'completed_by_user_id',
        'completion_notes',
        'tenant_id',
    ];

    protected $casts = [
        'due_offset_days' => 'integer',
        'sort_order'      => 'integer',
        'due_date'        => 'date',
        'completed_at'    => 'datetime',
    ];

    public function checklist(): BelongsTo
    {
        return $this->belongsTo(OnboardingChecklist::class, 'checklist_id');
    }

    public function ownerUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by_user_id');
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
