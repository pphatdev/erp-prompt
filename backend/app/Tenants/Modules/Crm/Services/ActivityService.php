<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Services;

use App\Models\Tenant\CrmActivity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ActivityService
{
    /**
     * @description Build a query for activities loaded with their actors sorted chronologically
     * @returns { Builder } Query builder instance
     */
    public function buildQuery(): Builder
    {
        return CrmActivity::query()->with('actor')->orderByDesc('created_at');
    }

    /**
     * @description Log a new polymorphic activity, enforcing cross-tenant security boundaries
     * @param { array } data Validated activity parameters
     * @returns { CrmActivity } The newly logged activity instance
     * @throws { \DomainException } If trackable is invalid or belongs to another tenant
     */
    public function logActivity(array $data): CrmActivity
    {
        $class = $data['trackable_type'];
        if (!class_exists($class)) {
            throw new \DomainException("Trackable class [{$class}] does not exist.");
        }

        // Querying within the active tenant database connection
        $trackable = $class::find($data['trackable_id']);
        if (!$trackable) {
            throw new \DomainException("Trackable target entity not found or unauthorized.");
        }

        return CrmActivity::create($data)->load('actor');
    }

    /**
     * @description Complete a pending activity transactionally
     * @param { CrmActivity } activity Activity model instance to complete
     * @returns { CrmActivity } Completed activity refreshed with its actor
     * @throws { \DomainException } If activity is already completed
     */
    public function completeActivity(CrmActivity $activity): CrmActivity
    {
        if ($activity->status === 'completed') {
            throw new \DomainException('Activity is already completed.');
        }

        return DB::transaction(function () use ($activity) {
            $activity->update(['status' => 'completed']);
            return $activity->fresh('actor');
        });
    }
}
