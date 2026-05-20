<?php

declare(strict_types=1);

namespace App\Tenants\Modules\IAM\Services;

use App\Models\Tenant\WorkflowStatus;
use DomainException;
use Illuminate\Database\Eloquent\Collection;

/**
 * Authoritative lookup for tenant status flows.
 *
 * Caches per request (the service is request-scoped via Laravel's container)
 * to avoid hitting the DB on every transition validation.
 */
class WorkflowStatusService
{
    /** @var array<string, Collection<int, WorkflowStatus>> */
    private array $cache = [];

    /**
     * @return Collection<int, WorkflowStatus>
     */
    public function for(string $module): Collection
    {
        return $this->cache[$module] ??= WorkflowStatus::query()
            ->forModule($module)
            ->get();
    }

    public function lookup(string $module, string $key): ?WorkflowStatus
    {
        return $this->for($module)->firstWhere('key', $key);
    }

    /**
     * Resolve the initial (default) status key for a module. Used by domain
     * services when creating a new record without an explicit status.
     */
    public function initialFor(string $module): string
    {
        $initial = $this->for($module)->firstWhere('is_initial', true);

        if (!$initial) {
            throw new DomainException("No initial status configured for module '{$module}'.");
        }

        return $initial->key;
    }

    /**
     * Throws DomainException when the transition isn't permitted. Domain
     * services catch it and let the controller translate to a 422.
     */
    public function validateTransition(string $module, string $from, string $to): void
    {
        $current = $this->lookup($module, $from);

        if (!$current) {
            throw new DomainException("Unknown status '{$from}' for module '{$module}'.");
        }

        $allowed = $current->allowed_next ?? [];

        if (!in_array($to, $allowed, true)) {
            throw new DomainException(
                "Cannot transition '{$module}' from '{$from}' to '{$to}'."
            );
        }

        if (!$this->lookup($module, $to)) {
            throw new DomainException("Unknown target status '{$to}' for module '{$module}'.");
        }
    }

    /**
     * Flush the in-memory cache. Call after mutating the table (e.g. seeder).
     */
    public function flushCache(): void
    {
        $this->cache = [];
    }
}
