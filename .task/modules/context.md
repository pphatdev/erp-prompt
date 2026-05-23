# Modules System — Context

## Goal
DB-backed module system where the ERP seller controls which modules each customer tenant can access, via subscription product → module entitlements.

## Files
| File | Role |
|---|---|
| `backend/database/migrations/tenant/2024_01_01_000041_create_modules_table.php` | Schema (FK split for Postgres) |
| `backend/database/seeders/ModuleSeeder.php` | Seeds menu items to modules table |
| `backend/app/Tenants/Modules/Settings/Controllers/ModuleController.php` | CRUD + toggle |
| `backend/app/Tenants/Modules/Inventory/Controllers/ProductController.php` | `module_ids[]` sync |
| `backend/app/Tenants/Modules/Inventory/Resources/ProductResource.php` | `modules` field |
| `backend/app/Tenants/Modules/Sales/Services/TenantProvisioningService.php` | `expandEntitledSlugs()` |
| `frontend/composables/useModules.ts` | Singleton composable |
| `frontend/pages/settings.vue` | Modules tab (adminOnly) |
| `frontend/pages/products.vue` | Module picker in create/edit |
| `frontend/layouts/default.vue` | `moduleSlug` gating on nav items |

## Module slug conventions
Parent slugs (e.g. `hrm`) map to child slugs (e.g. `hrm-employees`, `hrm-leaves`, `hrm-payroll`).
When a product entitles `hrm`, provisioning must also activate all children.
`is_core = true` modules (e.g. `iam`, `dashboard`) are always active and cannot be toggled.
