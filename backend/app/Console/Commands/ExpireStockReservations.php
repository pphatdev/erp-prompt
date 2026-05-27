<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant as CentralTenant;
use App\Tenants\Modules\Inventory\Services\StockReservationService;
use Illuminate\Console\Command;

/**
 * Per-tenant fan-out for stock reservation expiry. Flips `active`
 * reservations whose `expires_at` is in the past to `expired`, releasing
 * their hold on net-available stock so the next checkout can use the
 * freed units.
 *
 * Usage:
 *   php artisan inventory:expire-reservations               # all tenants
 *   php artisan inventory:expire-reservations --tenant=acme # one tenant
 *
 * Schedule (registered in routes/console.php) runs every 2 minutes — fast
 * enough that an abandoned cart's reservation typically expires within the
 * default 15-min TTL plus at most one polling interval.
 */
class ExpireStockReservations extends Command
{
    protected $signature = 'inventory:expire-reservations
                            {--tenant= : Handle of a specific tenant to process (omit for all)}';

    protected $description = 'Release expired stock reservations across tenants';

    public function handle(StockReservationService $service): int
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
                $affected = $service->expireDue();
                $totalAffected += $affected;
                if ($affected > 0) {
                    $this->line(sprintf('  [%s] expired %d reservation(s)', $tenant->handle, $affected));
                }
            });
        }

        $this->info("Done. Expired {$totalAffected} reservation(s) across {$tenants->count()} tenant(s).");
        return self::SUCCESS;
    }
}
