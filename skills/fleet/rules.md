# Fleet Management Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `fleet`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix — Admin Scope:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `vehicles` | `fleet.vehicles.read` | `fleet.vehicles.write` | `fleet.vehicles.delete` | `fleet.vehicles.export` |
| `tracking` | `fleet.tracking.read` | - | - | - |
| `maintenance`| `fleet.maintenance.read`| `fleet.maintenance.write`| `fleet.maintenance.delete`| `fleet.maintenance.export`|
| `fuel` | `fleet.fuel.read` | `fleet.fuel.write` | `fleet.fuel.delete` | `fleet.fuel.export` |

### Feature Matrix — `.self` Scope (Driver Self-Service):
Drivers/employees can only access their assigned vehicles or logs:
- `fleet.vehicles.read.self` — Read assigned vehicle profile.
- `fleet.fuel.write.self` — Upload fuel logs/receipts for assigned vehicle.

---

## 2. Implementation Standards

### Fleet Operational Flow
1. **Asset Entry**: Register vehicle with unique registration number, make, model, year, and telematics identifiers.
2. **Tracking**: Ingest real-time telemetry updates.
3. **Monitoring**: Evaluate vehicle current mileage against maintenance threshold configurations.
4. **Alerting**: Raise alert notifications when maintenance milestones are approached or exceeded.
5. **Logging**: Record granular maintenance costs and fuel transactions.
6. **Analysis**: Update real-time TCO (Total Cost of Ownership) calculations and efficiency metrics.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\Fleet`
- **Service Layer**: Business operations live in `Services/VehicleService.php` and `Services/TrackingService.php`.
- **Model Invariants (P0)**:
  - All models must reside flat under `App\Models\Tenant\` (e.g. `Vehicle`, `MaintenanceLog`, `FuelLog`) and utilize `BelongsToTenant`, `SoftDeletes`, and `Auditable` traits.
  - Mileage updates inside services (e.g. `logMaintenance`, `logFuel`) must be atomic, wrapped in `DB::transaction()`, and enforce that current mileage only increases monotonically.
- **API Security & Policies**:
  - Always enforce authorization inside controllers via Policies (`VehiclePolicy`, `MaintenanceLogPolicy`, `FuelLogPolicy`) using standard permission keys.
  - Driver self-service routes must enforce that the caller matches the assigned driver/employee on the record.
- **Resource JSON Format (P0)**:
  - Keys returned from resources (`VehicleResource`, `FuelLogResource`, `MaintenanceLogResource`) **MUST** use camelCase (e.g. `registrationNumber`, `currentMileage`, `mileageAtService`, `fillDate`, etc.). Never return raw snake_case database columns directly.
  - Return JsonResource instances directly from controllers to preserve the validation/missing value pipeline.
- **Pagination**:
  - Index endpoints must return the standard pagination envelope: `{ data: [...], pagination: { page, limit, total, totalPages } }`.

### Frontend (Nuxt/PrimeVue)
- **Directory Path Structure**:
  - Pages organize strictly by URL: `frontend/pages/fleet/vehicles.vue`, `frontend/pages/fleet/maintenance.vue`, and `frontend/pages/fleet/fuel.vue`. Nested per-module assets inside a `src/modules/` folder are forbidden.
  - Composables, stores, and components must be flat: `frontend/composables/useFleet.ts`, `frontend/stores/fleet.ts`, `frontend/components/VehicleSelector.vue`.
- **Premium UI & Interactive Map Shell**:
  - Dashboard maps (Leaflet/Google Maps) must load dynamically on the client side (`onMounted` hook) with styled visual overlays.
  - Implement full responsive custom card styles using existing design variables (e.g., `--color-primary-rgb`, `.glass-card`).
- **Standard Layout Mechanics (P2)**:
  - **Confirmations**: No native browser `confirm()` or `alert()`. Destructive or critical updates must go through `useToast().confirm()`.
  - **Date Formatting**: Every date or datetime render must use formatting helpers `formatDate` or `formatDateTime` from `~/composables/useDateFormat.ts`.
  - **Table Row Actions**: List tables with $\ge 2$ row actions must use the standard 30x30 kebab trigger (`ti-dots-vertical`) with fixed dropdown positioning and outside-click dismissal.
- **API Fetching**:
  - Always route through `useApi()`. Direct `$fetch` or `useFetch` are prohibited because they bypass tenant-scoping context headers (`X-Tenant-Handle`).

---

## 3. Code Numbering (Tenant-Configurable)
- **Vehicle Codes**: If tenant-specific vehicle identifiers are auto-generated, they should support tenant-configurable numbering schemas under **Settings → Numbering** via `numbering.vehicle_code_prefix` (default `VEH-`), falling back gracefully to standard defaults. Zero-padded sequence integers should grow sequentially and are stored in the database.

