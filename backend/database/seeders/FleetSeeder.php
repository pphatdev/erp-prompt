<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\FuelLog;
use App\Models\Tenant\MaintenanceLog;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\VehicleModel;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

/**
 * Fleet demo data: vehicles + recent maintenance + fuel history.
 *
 * Run per-tenant:
 *   php artisan tenants:run db:seed --option="class=Database\Seeders\FleetSeeder" --option="force=true"
 *
 * Idempotency:
 *  - Vehicles are keyed on `registration_number` (the table's natural unique
 *    column) with the withTrashed()+restore() pattern, because Vehicle uses
 *    SoftDeletes and the unique index does not include deleted_at.
 *  - Maintenance + fuel log lookups use a 3-field probe (vehicle + date +
 *    service_type/mileage). Re-runs become no-ops.
 *
 * Mileage invariant:
 *  - Every seeded log's mileage stays STRICTLY BELOW the parent vehicle's
 *    current_mileage. Logs represent recorded odometer readings from the past;
 *    current_mileage represents NOW. That ordering keeps VehicleService's
 *    monotonic check honest if a future log is filed through the service.
 */
class FleetSeeder extends Seeder
{
    public function run(): void
    {
        // Catalog first so it's available regardless of whether downstream
        // demos run — the Vehicle form's picker reads from this table.
        $this->seedVehicleModels();
        $this->seedVehicles();
        $this->seedMaintenanceLogs();
        $this->seedFuelLogs();
    }

    /**
     * Make/Model catalog. ~30 entries spanning the major regional brands so
     * the Vehicle Create form's datalist has reasonable coverage. Body/fuel
     * values match the suggestion vocab on the settings page
     * (`pages/settings/apps/fleet/vehicle-models.vue`).
     *
     * Idempotent via withTrashed + firstOrNew on (make, model). Re-runs
     * restore trashed entries and leave existing rows untouched (no
     * trampling — if admin edited the body_type, that survives).
     */
    private function seedVehicleModels(): void
    {
        if (!Schema::hasTable('vehicle_models')) {
            return;
        }

        $models = [
            // Toyota
            ['make' => 'Toyota',     'model' => 'Hilux',       'body_type' => 'Pickup',    'fuel_type' => 'Diesel'],
            ['make' => 'Toyota',     'model' => 'Camry',       'body_type' => 'Sedan',     'fuel_type' => 'Gasoline'],
            ['make' => 'Toyota',     'model' => 'Corolla',     'body_type' => 'Sedan',     'fuel_type' => 'Gasoline'],
            ['make' => 'Toyota',     'model' => 'RAV4',        'body_type' => 'SUV',       'fuel_type' => 'Hybrid'],
            ['make' => 'Toyota',     'model' => 'Land Cruiser','body_type' => 'SUV',       'fuel_type' => 'Diesel'],
            ['make' => 'Toyota',     'model' => 'Prius',       'body_type' => 'Hatchback', 'fuel_type' => 'Hybrid'],
            // Ford
            ['make' => 'Ford',       'model' => 'Ranger',      'body_type' => 'Pickup',    'fuel_type' => 'Diesel'],
            ['make' => 'Ford',       'model' => 'F-150',       'body_type' => 'Pickup',    'fuel_type' => 'Gasoline'],
            ['make' => 'Ford',       'model' => 'Everest',     'body_type' => 'SUV',       'fuel_type' => 'Diesel'],
            ['make' => 'Ford',       'model' => 'Transit',     'body_type' => 'Van',       'fuel_type' => 'Diesel'],
            // Honda
            ['make' => 'Honda',      'model' => 'Civic',       'body_type' => 'Sedan',     'fuel_type' => 'Gasoline'],
            ['make' => 'Honda',      'model' => 'Accord',      'body_type' => 'Sedan',     'fuel_type' => 'Gasoline'],
            ['make' => 'Honda',      'model' => 'CR-V',        'body_type' => 'SUV',       'fuel_type' => 'Gasoline'],
            // Nissan
            ['make' => 'Nissan',     'model' => 'NV200',       'body_type' => 'Van',       'fuel_type' => 'Gasoline'],
            ['make' => 'Nissan',     'model' => 'Navara',      'body_type' => 'Pickup',    'fuel_type' => 'Diesel'],
            ['make' => 'Nissan',     'model' => 'Patrol',      'body_type' => 'SUV',       'fuel_type' => 'Diesel'],
            // Mitsubishi
            ['make' => 'Mitsubishi', 'model' => 'Triton',      'body_type' => 'Pickup',    'fuel_type' => 'Diesel'],
            ['make' => 'Mitsubishi', 'model' => 'Pajero',      'body_type' => 'SUV',       'fuel_type' => 'Diesel'],
            ['make' => 'Mitsubishi', 'model' => 'Outlander',   'body_type' => 'SUV',       'fuel_type' => 'Hybrid'],
            // Mazda
            ['make' => 'Mazda',      'model' => 'BT-50',       'body_type' => 'Pickup',    'fuel_type' => 'Diesel'],
            ['make' => 'Mazda',      'model' => 'CX-5',        'body_type' => 'SUV',       'fuel_type' => 'Gasoline'],
            // Hyundai
            ['make' => 'Hyundai',    'model' => 'Tucson',      'body_type' => 'SUV',       'fuel_type' => 'Gasoline'],
            ['make' => 'Hyundai',    'model' => 'Santa Fe',    'body_type' => 'SUV',       'fuel_type' => 'Diesel'],
            // Kia
            ['make' => 'Kia',        'model' => 'Sorento',     'body_type' => 'SUV',       'fuel_type' => 'Diesel'],
            ['make' => 'Kia',        'model' => 'Carnival',    'body_type' => 'Van',       'fuel_type' => 'Diesel'],
            // Isuzu
            ['make' => 'Isuzu',      'model' => 'D-Max',       'body_type' => 'Pickup',    'fuel_type' => 'Diesel'],
            ['make' => 'Isuzu',      'model' => 'MU-X',        'body_type' => 'SUV',       'fuel_type' => 'Diesel'],
            // Tesla
            ['make' => 'Tesla',      'model' => 'Model 3',     'body_type' => 'Sedan',     'fuel_type' => 'Electric'],
            ['make' => 'Tesla',      'model' => 'Model Y',     'body_type' => 'SUV',       'fuel_type' => 'Electric'],
            // Volkswagen
            ['make' => 'Volkswagen', 'model' => 'Amarok',      'body_type' => 'Pickup',    'fuel_type' => 'Diesel'],
            ['make' => 'Volkswagen', 'model' => 'Tiguan',      'body_type' => 'SUV',       'fuel_type' => 'Gasoline'],
        ];

        foreach ($models as $row) {
            $entry = VehicleModel::withTrashed()
                ->firstOrNew([
                    'make' => $row['make'],
                    'model' => $row['model'],
                ]);

            if (!$entry->exists) {
                $entry->fill($row)->save();
            } elseif ($entry->trashed()) {
                $entry->restore();
            }
            // If the row exists and is live, leave it alone — admin may have
            // edited body_type/fuel_type/notes and we don't want to clobber.
        }
    }

    private function seedVehicles(): void
    {
        if (!Schema::hasTable('vehicles')) {
            return;
        }

        $vehicles = [
            ['registration_number' => 'TT-0001', 'make' => 'Toyota',     'model' => 'Hilux',  'year' => 2020, 'vin' => 'JTFFG02P5L0010001', 'status' => 'active',      'current_mileage' => 45000],
            ['registration_number' => 'TT-0002', 'make' => 'Ford',       'model' => 'Ranger', 'year' => 2021, 'vin' => '1FTER4FH5MLB10002', 'status' => 'active',      'current_mileage' => 32000],
            ['registration_number' => 'TT-0003', 'make' => 'Honda',      'model' => 'Civic',  'year' => 2019, 'vin' => '2HGFC2F58KH10003',  'status' => 'active',      'current_mileage' => 60000],
            ['registration_number' => 'TT-0004', 'make' => 'Toyota',     'model' => 'Camry',  'year' => 2018, 'vin' => '4T1B11HK1JU10004',  'status' => 'maintenance', 'current_mileage' => 95000],
            ['registration_number' => 'TT-0005', 'make' => 'Nissan',     'model' => 'NV200',  'year' => 2022, 'vin' => '3N6CM0KN6NK10005',  'status' => 'active',      'current_mileage' => 15000],
            ['registration_number' => 'TT-0006', 'make' => 'Mitsubishi', 'model' => 'Triton', 'year' => 2017, 'vin' => 'MMBJYKB40HD10006',  'status' => 'retired',     'current_mileage' => 180000],
        ];

        foreach ($vehicles as $row) {
            $vehicle = Vehicle::withTrashed()
                ->firstOrNew(['registration_number' => $row['registration_number']]);

            if (!$vehicle->exists) {
                $vehicle->fill($row)->save();
            } elseif ($vehicle->trashed()) {
                $vehicle->restore();
            }
        }
    }

    private function seedMaintenanceLogs(): void
    {
        if (!Schema::hasTable('maintenance_logs')) {
            return;
        }

        // Resolve registration → id once; map lookups stay O(1) in the loop.
        $vehicleIds = Vehicle::withTrashed()
            ->whereIn('registration_number', [
                'TT-0001', 'TT-0002', 'TT-0003', 'TT-0004', 'TT-0005', 'TT-0006',
            ])
            ->pluck('id', 'registration_number');

        if ($vehicleIds->isEmpty()) {
            return;
        }

        $logs = [
            ['reg' => 'TT-0001', 'service_type' => 'oil_change',          'days_ago' => 90,  'mileage' => 40000,  'cost' => 75.00,   'notes' => 'Synthetic oil + filter replacement.'],
            ['reg' => 'TT-0001', 'service_type' => 'tire_rotation',       'days_ago' => 60,  'mileage' => 42500,  'cost' => 45.00,   'notes' => null],
            ['reg' => 'TT-0002', 'service_type' => 'inspection',          'days_ago' => 120, 'mileage' => 28000,  'cost' => 120.00,  'notes' => 'Annual safety inspection passed.'],
            ['reg' => 'TT-0003', 'service_type' => 'brake_service',       'days_ago' => 45,  'mileage' => 58000,  'cost' => 380.00,  'notes' => 'Front pads + rotors replaced.'],
            ['reg' => 'TT-0004', 'service_type' => 'oil_change',          'days_ago' => 30,  'mileage' => 93000,  'cost' => 75.00,   'notes' => null],
            ['reg' => 'TT-0004', 'service_type' => 'repair',              'days_ago' => 10,  'mileage' => 94500,  'cost' => 1250.00, 'notes' => 'Transmission service — pulled vehicle off active rotation.'],
            ['reg' => 'TT-0005', 'service_type' => 'oil_change',          'days_ago' => 15,  'mileage' => 14000,  'cost' => 80.00,   'notes' => 'First service after delivery.'],
            ['reg' => 'TT-0006', 'service_type' => 'battery_replacement', 'days_ago' => 180, 'mileage' => 175000, 'cost' => 220.00,  'notes' => 'Replaced before retirement.'],
        ];

        foreach ($logs as $row) {
            $vehicleId = $vehicleIds->get($row['reg']);
            if (!$vehicleId) {
                continue;
            }

            $serviceDate = now()->subDays($row['days_ago'])->toDateString();

            $exists = MaintenanceLog::where('vehicle_id', $vehicleId)
                ->where('service_date', $serviceDate)
                ->where('service_type', $row['service_type'])
                ->exists();
            if ($exists) {
                continue;
            }

            MaintenanceLog::create([
                'vehicle_id'         => $vehicleId,
                'service_type'       => $row['service_type'],
                'service_date'       => $serviceDate,
                'mileage_at_service' => $row['mileage'],
                'cost'               => $row['cost'],
                'notes'              => $row['notes'],
            ]);
        }
    }

    private function seedFuelLogs(): void
    {
        if (!Schema::hasTable('fuel_logs')) {
            return;
        }

        $vehicleIds = Vehicle::withTrashed()
            ->whereIn('registration_number', [
                'TT-0001', 'TT-0002', 'TT-0003', 'TT-0004', 'TT-0005',
            ])
            ->pluck('id', 'registration_number');

        if ($vehicleIds->isEmpty()) {
            return;
        }

        $logs = [
            ['reg' => 'TT-0001', 'days_ago' => 30, 'liters' => 50.5, 'cost' => 75.00, 'mileage' => 43500],
            ['reg' => 'TT-0001', 'days_ago' => 15, 'liters' => 48.2, 'cost' => 72.00, 'mileage' => 44200],
            ['reg' => 'TT-0001', 'days_ago' => 5,  'liters' => 52.0, 'cost' => 78.00, 'mileage' => 44950],
            ['reg' => 'TT-0002', 'days_ago' => 20, 'liters' => 60.0, 'cost' => 90.00, 'mileage' => 31200],
            ['reg' => 'TT-0002', 'days_ago' => 7,  'liters' => 58.5, 'cost' => 87.50, 'mileage' => 31800],
            ['reg' => 'TT-0003', 'days_ago' => 25, 'liters' => 35.0, 'cost' => 52.00, 'mileage' => 58500],
            ['reg' => 'TT-0003', 'days_ago' => 10, 'liters' => 36.8, 'cost' => 55.00, 'mileage' => 59500],
            ['reg' => 'TT-0004', 'days_ago' => 14, 'liters' => 45.0, 'cost' => 67.50, 'mileage' => 94000],
            ['reg' => 'TT-0005', 'days_ago' => 18, 'liters' => 42.0, 'cost' => 63.00, 'mileage' => 14500],
            ['reg' => 'TT-0005', 'days_ago' => 3,  'liters' => 38.5, 'cost' => 57.00, 'mileage' => 14900],
        ];

        foreach ($logs as $row) {
            $vehicleId = $vehicleIds->get($row['reg']);
            if (!$vehicleId) {
                continue;
            }

            $fillDate = now()->subDays($row['days_ago'])->toDateString();

            $exists = FuelLog::where('vehicle_id', $vehicleId)
                ->where('fill_date', $fillDate)
                ->where('mileage_at_fill', $row['mileage'])
                ->exists();
            if ($exists) {
                continue;
            }

            FuelLog::create([
                'vehicle_id'      => $vehicleId,
                'fill_date'       => $fillDate,
                'liters'          => $row['liters'],
                'cost'            => $row['cost'],
                'mileage_at_fill' => $row['mileage'],
                // driver_id intentionally null — driver-assignment table lands
                // in a future round; .self ownership enforcement waits on it.
                'driver_id'       => null,
            ]);
        }
    }
}
