<?php

namespace App\Policies;

use App\Models\Tenant\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class UserPolicy
{
    use HandlesAuthorization;

    /**
     * Cross-tenant guard — defense-in-depth.
     *
     * Tenant isolation is already enforced two layers below this policy:
     *   1. Multi-DB tenancy switches the DB connection per request, so the
     *      `users` table for tenant A is physically separate from tenant B.
     *   2. The `BelongsToTenant` global scope filters every Eloquent query
     *      by `tenant_id = current_tenant`.
     *
     * This guard is a third layer that catches the case where either of the
     * above is bypassed (a raw query, a `withoutGlobalScopes()` call, an
     * admin CLI tool, a future shared-DB tenancy mode, etc.). If the actor
     * and the target belong to different tenants, refuse regardless of the
     * permission slug.
     */
    private function sameTenant(User $user, User $model): bool
    {
        return (string) $user->tenant_id !== ''
            && (string) $user->tenant_id === (string) $model->tenant_id;
    }

    /**
     * Determine whether the user can view any models.
     *
     * No `$model` argument — the listing is implicitly scoped to the actor's
     * tenant by the BelongsToTenant global scope on UserService::buildIndexQuery().
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('iam.users.read');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, User $model): bool
    {
        return $this->sameTenant($user, $model)
            && $user->hasPermission('iam.users.read');
    }

    /**
     * Determine whether the user can create models.
     *
     * No `$model` argument — BelongsToTenant's `creating` hook auto-fills
     * `tenant_id` with the actor's tenant, so the created user can never
     * land in a foreign tenant.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('iam.users.write');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, User $model): bool
    {
        return $this->sameTenant($user, $model)
            && $user->hasPermission('iam.users.write');
    }

    /**
     * Determine whether the user can delete the model.
     *
     * Also refuses self-deletion — a tenant admin must not be able to
     * accidentally lock themselves out of their own tenant (last admin
     * standing). Demoting via role change is still allowed.
     */
    public function delete(User $user, User $model): bool
    {
        if ((string) $user->id === (string) $model->id) {
            return false;
        }

        return $this->sameTenant($user, $model)
            && $user->hasPermission('iam.users.delete');
    }
}
