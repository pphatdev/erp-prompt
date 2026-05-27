<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant as CentralTenant;
use App\Tenants\Modules\Sales\Services\SubscriptionService;
use Illuminate\Console\Command;

/**
 * Per-tenant fan-out for subscription expiry. Flips `active` subscriptions
 * whose `end_date` is in the past to `expired`.
 *
 * Usage:
 *   php artisan subscriptions:expire                 # all tenants
 *   php artisan subscriptions:expire --tenant=acme   # one tenant
 *
 * Schedule (registered in routes/console.php) runs daily.
 */
class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire
                            {--tenant= : Handle of a specific tenant to process (omit for all)}';

    protected $description = 'Flip active subscriptions with past end_date to expired across tenants';

    public function handle(SubscriptionService $service): int
    {
        $handle = $this->option('tenant');

        $query = CentralTenant::query();
        if ($handle) {
            $query->where('handle', $handle);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->error($handle ? "Tenant '{$handle}' not found." : 'No tenants found.');
            return self::FAILURE;
        }

        $totalAffected = 0;
        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($tenant, $service, &$totalAffected) {
                $affected = $service->expireDueSubscriptions();
                $totalAffected += $affected;
                $this->line(sprintf('  [%s] expired %d subscription(s)', $tenant->handle, $affected));
            });
        }

        $this->info("Done. Expired {$totalAffected} subscription(s) across {$tenants->count()} tenant(s).");
        return self::SUCCESS;
    }
}
