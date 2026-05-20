<?php

namespace App\Tenants\Modules\Fleet\Services;

use App\Models\Tenant\Vehicle;
use App\Models\Tenant\MaintenanceLog;
use App\Models\Tenant\FuelLog;
use Illuminate\Support\Facades\DB;

class VehicleService
{
    /**
     * Log a maintenance event and potentially update vehicle mileage/status.
     */
    public function logMaintenance(array $data): MaintenanceLog
    {
        return DB::transaction(function () use ($data) {
            $vehicle = Vehicle::findOrFail($data['vehicle_id']);
            
            // Ensure mileage only goes up
            if ($data['mileage_at_service'] > $vehicle->current_mileage) {
                $vehicle->update(['current_mileage' => $data['mileage_at_service']]);
            }

            return MaintenanceLog::create($data);
        });
    }

    /**
     * Log a fuel event and potentially update vehicle mileage.
     */
    public function logFuel(array $data): FuelLog
    {
        return DB::transaction(function () use ($data) {
            $vehicle = Vehicle::findOrFail($data['vehicle_id']);
            
            // Ensure mileage only goes up
            if ($data['mileage_at_fill'] > $vehicle->current_mileage) {
                $vehicle->update(['current_mileage' => $data['mileage_at_fill']]);
            }

            return FuelLog::create($data);
        });
    }
}
