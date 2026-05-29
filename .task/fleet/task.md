# Task Checklist: Fleet Management

Implementation tracking for the Fleet Management system.

## Backend (Laravel)

### 1. Database & Models
- [x] Create core migrations for `vehicles`, `maintenance_logs`, and `fuel_logs` (completed in `2024_01_01_000011_create_fleet_tables.php`)
- [x] Implement Eloquent models under `App\Models\Tenant\Vehicle`, `MaintenanceLog`, and `FuelLog`
- [x] Wire `BelongsToTenant` and `SoftDeletes` traits on fleet models (Vehicle has SoftDeletes; MaintenanceLog + FuelLog intentionally do not — logs are append-only audit history)
- [x] Wire `Auditable` trait on fleet models to capture audit logging events (verified — all three models declare `use Auditable`)

### 2. Service Layer & Controller Alignment
- [x] Scaffolding controllers `VehicleController`, `MaintenanceLogController`, and `FuelLogController`
- [x] Core service operations `VehicleService::logMaintenance` and `VehicleService::logFuel` in DB transaction
- [x] Refactor `VehicleResource`, `FuelLogResource`, and `MaintenanceLogResource` to output `camelCase` response keys
- [x] Enforce monotonic current mileage checks on log creations to reject mileage decreases (throws `InvalidArgumentException` when proposed mileage is below `vehicle.current_mileage`; row is `lockForUpdate`'d inside the transaction)

### 3. Security & IAM Integration
- [x] Create `FleetPermissionSeeder.php` containing admin permissions and self-service scopes
- [x] Call `FleetPermissionSeeder` from `TenantDatabaseSeeder`
- [x] Implement standard policies `VehiclePolicy.php`, `MaintenanceLogPolicy.php`, and `FuelLogPolicy.php`
- [x] Register policies in `TenantServiceProvider.php` and authorize controller calls
- [x] Update `ModuleSeeder.php` routing segment to point to `/fleet/vehicles`

### Backend gaps (still TODO)
- [ ] Vehicle ↔ Driver/Employee assignment table — required so `FuelLogPolicy::create` can ENFORCE the `.self` ownership check (the scope is granted, but the controller can't yet verify `driver_id === $user->employee->id` against an assigned vehicle).
- [x] `update` / `destroy` controller methods on the three Fleet controllers
  - Vehicle: full PUT (registration/make/model/year/vin/status) + DELETE (soft) + POST /vehicles/bulk-archive returning `{deleted, skipped, missing}` per design.md §14.5. `current_mileage` is intentionally NOT editable via PUT — must go through a log endpoint to preserve the monotonic invariant.
  - MaintenanceLog + FuelLog: PUT excludes `mileage_at_*` and `vehicle_id` (immutable facts); DELETE is hard since neither model uses SoftDeletes.
- [ ] `MaintenanceSchedulerJob` for date-/mileage-threshold alerting.
- [ ] Tenant-isolated storage path + signed-URL helper for fuel receipt uploads (rules.md P0).

---

## Frontend (Nuxt 3)

### 1. Composables & Stores
- [x] Create flat API wrapper `frontend/composables/useFleet.ts` routing strictly through `useApi()`
- [x] Create flat Pinia store `frontend/stores/fleet.ts` for telemetry tracking and coordinate states (skeleton — Reverb subscription wired in a later phase)

### 2. Layouts & Pages
- [x] Link `fleets` sidebar menu item in `frontend/layouts/default.vue` mapped to `/fleet/vehicles` (driven by `ModuleSeeder` row; sidebar reads from DB modules)
- [x] Create vehicles directory layout `frontend/pages/fleet/vehicles.vue`
  - [x] Add summary statistic KPI blocks (total, active, in maintenance, retired) — derived client-side from a single batched load (PAGE_BATCH=500; needs a `/vehicles/stats` endpoint once fleets routinely exceed that)
  - [x] Render vehicle list via standard Applications-style table (design.md §14 chrome)
  - [x] Collapse row actions into a 30x30 kebab dropdown (`ti-dots-vertical`)
  - [ ] Create dynamic Leaflet/Google Map overlay inside `onMounted` hook (deferred — store is shaped to plug in without page refactor)
  - [x] Implement vehicle creation modal (matches §14.6 / §12.7 conventions — uses `form-grid`, no native confirm)
  - [x] Implement edit + archive actions
    - Edit reuses the create modal via `editing` ref. `current_mileage` is shown disabled with a hint pointing at the maintenance/fuel-log path (backend rejects mileage on PUT).
    - Archive uses `toast.confirm` per design.md §15 (no native `confirm()`).
    - Bulk Archive (design.md §14.3/§14.4): row checkboxes + indeterminate select-all + sliding toolbar; surfaces the `{deleted, skipped, missing}` envelope via `toast.info` on partial success.
  - [x] Vehicle photo upload
    - Migration `2024_01_01_000066_add_image_path_to_vehicles.php` adds nullable `image_path` after `current_mileage`.
    - Endpoints: `POST/DELETE /vehicles/{id}/image` multipart, `authorize('update')`, 2 MB ceiling, prior file removed before storing the new one. Mirrors `EmployeeController::uploadAvatar`.
    - Resource exposes `imageUrl` as `asset('storage/'.image_path)`; storage path is `vehicles/{tenant_key}/...` on the public disk (vehicle photos are public assets, fuel receipts will use the §11 signed-URL pattern).
    - Frontend: form modal has the picker block (preview + Upload / Change / Remove); table column shows a 36×36 rounded thumbnail with a `ti-truck` fallback; details modal renders a 16:9 hero image when present. Upload fires as a separate multipart call AFTER the JSON create/update so the PUT/POST stays clean.
- [x] Create fuel logs page `frontend/pages/fleet/fuel/index.vue`
  - [x] Form interface for log submission (vehicle + mileage_at_fill locked in edit mode, driver picker populated from /employees, fill date defaults to today)
  - [x] Total cost surfaced in the KPI strip via `useCountUp` (decimals:2 so the cents tick smoothly). Total liters animated the same way.
  - [x] Date rendering via `formatDate` / `formatDateTime` (fillDate → formatDate; createdAt/updatedAt → formatDateTime per the date-naming rule)
  - [x] Edit + Delete via kebab; delete uses `toast.confirm` per design.md §15
  - [ ] Secure receipt attachments (deferred — design.md §11.2 path `storage/app/fleet/fuel/receipts/{uuid}.ext`; waits on tenant-isolated upload helper + signed-URL accessor)
  - [ ] Average efficiency (km/L) KPI — needs per-vehicle fill-pair sort + distance/liters; the loaded set is already sorted by fill_date desc, but the calculation hits edge cases (first fill of a vehicle, missing prior fill). Tracked as a follow-up.
  - [ ] Bulk delete (deferred — same envelope contract as vehicles bulk-archive, needs backend `/fuel-logs/bulk-delete`)
- [x] Create maintenance scheduling page `frontend/pages/fleet/maintenance.vue`
  - [x] Display historical repairs and service intervals with sorting (service_date desc from backend; client-side filter + paginate; KPI strip surfaces total / this-month / total-cost / avg-cost)
  - [x] Date rendering formatted via `formatDate`/`formatDateTime` (serviceDate → formatDate; createdAt/updatedAt → formatDateTime per the date-naming rule)
  - [x] Edit + Delete via kebab (vehicle + mileage_at_service locked in edit mode per backend invariant); delete uses `toast.confirm` per design.md §15
  - [ ] Bulk delete (deferred — same envelope contract as vehicles bulk-archive, needs backend `/maintenance-logs/bulk-delete`)
  - [ ] Receipt / invoice attachment (deferred until tenant-isolated upload helper lands per rules.md P0)
- [ ] Vehicle detail page `frontend/pages/fleet/vehicles/[id].vue` — currently surfaced as a quick-view modal; promote to a full page once maintenance + fuel logs land so the user can drill in.

---

## QA & Testing

### 1. Backend Integration Tests (Pest)
- [ ] Create `tests/Feature/Tenant/Fleet/VehicleIsolationTest.php` to verify P0 tenancy isolation
- [ ] Create `tests/Feature/Tenant/Fleet/MileageInvariantTest.php` verifying mileage monotonicity
- [ ] Create `tests/Feature/Tenant/Fleet/ResourceContractTest.php` asserting camelCase JSON schemas and pagination shape
- [ ] Create audit log tests asserting mutations register in `audit_logs`

### 2. API Documentation
- [ ] Add Fleet API endpoint schema requests and responses inside `docs/postman/erp_collection.json`
