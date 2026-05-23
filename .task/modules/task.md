# Modules System — Task Tracker

Status: COMPLETED (2026-05-23)

## Checklist
- [x] `modules` table migration — fixed PostgreSQL self-referential FK (separate Schema::table call)
- [x] `down()` drops FK before dropping table
- [x] `ModuleSeeder` — seeds all menu items to DB
- [x] `ModuleController::allForManagement()` — returns ALL modules with children + products
- [x] `ModuleController::toggle()` — flips `is_active`, 403 for core modules
- [x] Routes: `GET /modules/all`, `PATCH /modules/{module}/toggle` (static before parameterized)
- [x] Settings → Modules tab (adminOnly: true, filtered in computed `tabs`)
- [x] Product-module linking — `module_ids[]` accepted in ProductController store/update
- [x] `ProductResource` returns `modules` field when loaded
- [x] Product list shows module prefix badges for software products
- [x] Product create/edit modal: module picker (tree, tri-state checkboxes) for software type
- [x] `TenantProvisioningService::expandEntitledSlugs()` — parent slug → children cascade

## Key decisions
- Static routes (`/modules/all`) MUST come before parameterized (`/modules/{module}`) in tenant.php
- `is_core = true` modules cannot be deactivated (controller returns 403)
- Module management tab hidden from customers (`adminOnly: true` in ALL_TABS)
- Provisioning cascade: when product links to parent `hrm`, children `hrm-employees`, `hrm-leaves` etc. are also activated
