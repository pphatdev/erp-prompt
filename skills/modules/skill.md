# Modules System Skill

> Cross-cutting module-enablement layer (controls which sidebar entries and routes a tenant can use). Status: [`.task/modules/task.md`](../../.task/modules/task.md)

## Scope
`modules` table with self-referential FK (parent → children), `is_active`, `is_core` (cannot be disabled), `parent_id`, `sort_order`, `group`. `product_modules` pivot links software products to system modules so subscriptions can grant entitlement cascades.

## Code map
- **Backend module:** `backend/app/Tenants/Modules/Settings/` (ModuleController lives here)
- **Model:** `app/Models/Tenant/Module.php`
- **Controller endpoints:** `index`, `allForManagement`, `toggle`, `slugs`, `syncProduct`
- **Seeder:** `database/seeders/ModuleSeeder.php` — static menu items seeded to DB
- **Frontend composable:** `composables/useModules.ts` — singleton, fail-open, `hasModule(slug)`
- **Sidebar gating:** `layouts/default.vue` reads `moduleSlug` on nav items
- **Provisioning helper:** `expandEntitledSlugs()` propagates parent-grant to children at tenant provisioning

## Routes
- `// Modules` (~line 197–204)

## Permissions
Module management is admin-only (tab is gated by super-admin check, not a permission slug). Read access is open inside the tenant.

## Critical patterns
- Sidebar nav items declare `moduleSlug` — `useModules.hasModule()` decides whether to render
- `is_core: true` modules cannot be disabled in the UI (IAM, Settings, Dashboard)
- The PostgreSQL self-referential FK migration must split `Schema::create` + `Schema::table` (covered in `rules/tenancy/skill.md`)
