<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Fleet\Services;

use App\Models\Tenant\Vehicle;
use App\Models\Tenant\MaintenanceLog;
use App\Models\Tenant\FuelLog;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class VehicleService
{
    /**
     * Log a maintenance event and bump vehicle mileage forward.
     *
     * Mileage must move monotonically (rules.md P0). A log claiming a mileage
     * BELOW the vehicle's current_mileage is rejected outright — it usually
     * means a paperwork mix-up and silently dropping the update would mask the
     * fact that the log was filed out of order.
     */
    public function logMaintenance(array $data): MaintenanceLog
    {
        return DB::transaction(function () use ($data) {
            $vehicle = Vehicle::lockForUpdate()->findOrFail($data['vehicle_id']);

            $this->assertMonotonic($vehicle->current_mileage, (int) $data['mileage_at_service'], 'service');

            if ((int) $data['mileage_at_service'] > $vehicle->current_mileage) {
                $vehicle->update(['current_mileage' => (int) $data['mileage_at_service']]);
            }

            return MaintenanceLog::create($data);
        });
    }

    /**
     * Log a fuel event and bump vehicle mileage forward. Same invariant as
     * logMaintenance — mileage may only increase.
     */
    public function logFuel(array $data): FuelLog
    {
        return DB::transaction(function () use ($data) {
            $vehicle = Vehicle::lockForUpdate()->findOrFail($data['vehicle_id']);

            $this->assertMonotonic($vehicle->current_mileage, (int) $data['mileage_at_fill'], 'fill');

            if ((int) $data['mileage_at_fill'] > $vehicle->current_mileage) {
                $vehicle->update(['current_mileage' => (int) $data['mileage_at_fill']]);
            }

            return FuelLog::create($data);
        });
    }

    private function assertMonotonic(int $currentMileage, int $proposedMileage, string $eventType): void
    {
        if ($proposedMileage < $currentMileage) {
            throw new InvalidArgumentException(sprintf(
                'Mileage at %s (%d) cannot be lower than the vehicle current mileage (%d).',
                $eventType,
                $proposedMileage,
                $currentMileage,
            ));
        }
    }

    /**
     * Soft-delete a single vehicle. The model's SoftDeletes trait sets
     * deleted_at; existing maintenance/fuel logs are kept for audit and remain
     * queryable via withTrashed() on the vehicle relation.
     */
    public function archiveVehicle(Vehicle $vehicle): bool
    {
        return (bool) $vehicle->delete();
    }

    /**
     * Bulk-archive vehicles. Returns the {deleted, skipped, missing} envelope
     * mandated by design.md §14.5. `missing` are ids that didn't resolve to a
     * non-trashed row; `skipped` is reserved for future business-rule blocks
     * (e.g., vehicles with open work orders) and stays empty today.
     *
     * @param array<int,string> $ids
     * @return array{deleted:int, skipped:array<int,string>, missing:array<int,string>}
     */
    public function bulkArchiveVehicles(array $ids): array
    {
        return DB::transaction(function () use ($ids) {
            $found = Vehicle::whereIn('id', $ids)->get(['id', 'status']);
            $foundIds = $found->pluck('id')->all();
            $missing = array_values(array_diff($ids, $foundIds));

            $skipped = [];
            $eligibleIds = $found
                ->reject(fn (Vehicle $v) => in_array($v->id, $skipped, true))
                ->pluck('id')
                ->all();

            $deleted = empty($eligibleIds)
                ? 0
                : Vehicle::whereIn('id', $eligibleIds)->delete();

            return compact('deleted', 'skipped', 'missing');
        });
    }
}
