# Frontend Architectural Structure: Nuxt 3 + Tailwind 4 + PrimeVue

> Authoritative directory map for `frontend/`. This describes the **actual** layout — not an aspirational one. Pages are organized by URL path, not by module sub-tree.

## Overview

Nuxt 3 SPA (SSR disabled). File-based routing maps `frontend/pages/**` → URLs. There is **no `src/modules/` folder** — module pages live under `pages/{module-slug}/`. Composables, stores, and components are flat singletons shared across modules.

## 1. Top-level layout

```text
frontend/
├── app.vue                           # Root component. Calls authStore.initializeAuth() + tenantStore.applyBrandToDocument()
├── nuxt.config.ts                    # ssr:false, modules:['@pinia/nuxt'], css:['~/assets/css/main.css'], vite tailwindcss()
├── package.json                      # nuxt^3, pinia, primevue, tailwindcss@next, vue-i18n (no Tailwind config file — uses @theme in main.css)
├── assets/
│   └── css/
│       └── main.css                  # Design tokens (CSS vars), .glass-card, .nav-skeleton, dash-shimmer, dark-mode overrides
├── components/                       # Flat. Reusable UI pieces (CustomizerOffcanvas, ConfirmDialog, Badge, ...)
├── composables/                      # Flat. Named auto-imports (useApi, useModules, useDashboard, useSettings, useDateFormat, useInventory, ...)
├── layouts/
│   ├── default.vue                   # 1200-line layout: rail sidebar + flyout + mobile drawer + topbar + breadcrumb + nav data
│   └── auth.vue                      # Login/auth-screen layout
├── middleware/                       # Route guards (auth, admin-only, ...)
├── pages/
│   ├── index.vue                     # Placeholder redirect to /dashboard
│   ├── login.vue
│   ├── dashboard.vue                 # Single file; admin vs customer branches via v-if="authStore.isAdmin"
│   ├── crm/                          # Module pages — folder per URL prefix
│   ├── sales/
│   ├── inventory/
│   ├── hrm/
│   ├── fms/
│   ├── eapprovals/
│   └── settings/
│       ├── index.vue                 # Redirects to /settings/configuration/branding
│       ├── users.vue                 # User directory (uses /users API directly)
│       ├── roles.vue                 # Roles matrix
│       ├── apps/hrm/leave-types.vue  # Nested app-management pages
│       └── configuration/
│           ├── branding.vue
│           ├── locale.vue
│           ├── notifications.vue
│           ├── security.vue
│           ├── numbering.vue
│           ├── modules.vue           # adminOnly via middleware → navigateTo('/dashboard') if !isAdmin
│           └── platform.vue
├── plugins/                          # Nuxt plugins (toast, i18n, ...)
├── public/                           # Static files served at /
├── stores/                           # Pinia stores
│   ├── auth.ts                       # user, accessToken, refreshToken, permissions, isAdmin, login/logout/refresh/initializeAuth
│   └── tenant.ts                     # currentTenant, activeHandle, applyBrandToDocument(), syncBranding()
└── types/                            # TS types per domain
    ├── sales.ts
    ├── inventory.ts
    ├── crm.ts
    └── finance.ts
```

## 2. Page conventions

- **`<script setup lang="ts">` always.** No Options API.
- **TypeScript strict.** All props, emits, and refs typed.
- **4-space indent** for `.vue`, `.ts`, `.css`, `.json` — matches backend.
- **`definePageMeta`** for breadcrumb label, middleware, and layout overrides. Example:

```ts
definePageMeta({
    breadcrumb: 'Categories',
    middleware: [function () {
        const auth = useAuthStore()
        if (!auth.isAdmin) return navigateTo('/dashboard')
    }],
})
```

- **Permission gating in the page** is done via `computed` flags off `authStore.hasPermission(...)` — used to hide buttons / disable inputs, not to redirect.

## 3. Composables (the API layer)

Pages **never** call `$fetch` directly. Every API call goes through `useApi()`, which:

1. Injects `X-Tenant-Handle` from `tenantStore.activeHandle`
2. Injects `Authorization: Bearer <accessToken>` when authenticated
3. On 401, awaits `authStore.rotateToken()` (concurrent-safe, single-flight) and retries once
4. Exposes `get / post / put / patch / delete` methods returning typed JSON

Module composables wrap `useApi()` and expose named CRUD methods, e.g.:

```ts
// composables/useInventory.ts
export const useInventory = () => {
    const api = useApi()
    return {
        categories: {
            tree: () => api.get<{ data: Category[] }>('/categories?tree=1'),
            create: (payload) => api.post('/categories', payload),
            update: (id, payload) => api.put(`/categories/${id}`, payload),
            destroy: (id) => api.delete(`/categories/${id}`),
        },
        products: { /* ... */ },
        // ...
    }
}
```

Singleton composables (`useModules`, `useDashboard`) use **module-level refs** outside the factory so state persists across components and uses fail-open semantics (don't hide UI on a backend error).

## 4. State (Pinia)

Two cross-cutting stores live in `stores/`:

- `auth.ts` — `user`, `accessToken`, `refreshToken`, `expiresAt`, `permissions`, `isAdmin`, `hasPermission(slug)`. Hydrates from `localStorage` (`auth_token`, `auth_refresh_token`, `auth_expires_at`) on app mount.
- `tenant.ts` — `currentTenant`, `activeHandle`, `applyBrandToDocument()`, `syncBranding()`. Branding lives here because it cuts across all modules.

Module-specific state stays inside the page/composable. There is no `stores/{module}.ts` pattern — domain state is reactive refs inside the composable that returns them.

## 5. Layout & UI shell

A single `layouts/default.vue` (~1200 lines) owns:

- 260-px rail sidebar that collapses to 70 px (`sidebarCollapsed` ref); hover-expands when collapsed (`isFlyout`)
- Mobile drawer with backdrop (`sidebarOpen` ref, closes on route change)
- Sticky topbar: search, notifications popover (demo data), tenant switcher, profile menu, customizer button
- Breadcrumb with slug-to-label map + entity-name override (`useBreadcrumbOverride().set(label)`)
- Nav-group definitions (Main / My Workspace / Apps / Configurations) and the permission/module gating function

See `rules/frontend/ui_shell.md` for the full reproduction guide (nav schema, settings tabs, dashboard layout, branding system).

## 6. Styling

- **Tailwind 4 alpha** via `@tailwindcss/vite`. There is **no `tailwind.config.ts`** — design tokens are declared with `@theme { ... }` blocks in `assets/css/main.css`.
- **CSS variables** are the single source of truth for tenant branding:
  - `--color-primary-rgb` (RGB triple, set by `tenantStore.applyBrandToDocument()`)
  - `--color-success-rgb`, `--color-warning-rgb`, `--color-danger-rgb`, `--color-info-rgb`
  - `--bg-layout`, `--bg-card`, `--border-color`, `--text-heading`, `--text-body`, `--text-muted`
  - `--shadow-sm`, `--shadow-md`, `--shadow-lg`
- **Dark mode** via `[data-bs-theme="dark"]` selector on `<html>`; toggled by `CustomizerOffcanvas` and reflected in `localStorage.theme`.
- **`.glass-card`** is the canonical card class — used widely instead of PrimeVue's default Card chrome.
- **Icons** come from Tabler Icons CDN (loaded in `nuxt.config.ts` `app.head`); class names look like `ti ti-users`, `ti-shopping-cart`.

## 7. PrimeVue usage

PrimeVue is a dependency (`primevue@^3.52`) but is **not** the default chrome. Most pages are hand-rolled Tailwind components (cards, tables, modals) using `glass-card` + custom modals (`fixed inset-0 bg-black/50 backdrop-blur-sm`). PrimeVue is reserved for richer widgets (Kanban drag/drop, complex DataTables, Calendar) and uses Pass-Through (`pt:`) for theming when applied. When introducing a new page, prefer the existing custom patterns unless you genuinely need a PrimeVue widget.

## 8. Multi-tenancy

- Every API request carries `X-Tenant-Handle: {handle}` — injected by `useApi()` from `tenantStore.activeHandle`.
- `localStorage.tenant_handle` persists the selected tenant across reloads.
- `tenantStore.setTenantByHandle(handle)` short-circuits when the handle is unchanged so navigating between pages does not trigger a branding flip.

## 9. Forms, validation, modals

- **Forms**: reactive `form` object + `showErrors` flag; show validation errors only after first submit attempt. VeeValidate is available for complex forms but not the default.
- **Confirm dialogs**: never use `window.confirm()`. Use `useToast().confirm({ title, message, color: 'danger' })` which returns a `Promise<boolean>`. Backed by `components/ConfirmDialog.vue`.
- **Toast**: `useToast().success(...)`, `.error(...)`, `.info(...)`.
- **Row actions**: ≥2 actions per row → single 30×30 kebab trigger (`ti-dots-vertical`) with a `position: fixed` dropdown. Per-row visibility via `v-if`, not gray-out. See `rules/frontend/standards.md` §7.

## 10. Date formatting

Single source: `~/composables/useDateFormat.ts`. Import named functions, never call `Date#toLocaleString` in pages.

- `formatDateTime(input)` → `"21 May 2026 03:45 PM"`
- `formatDate(input)` → `"21 May 2026"`
- Invalid/empty input → em-dash (`—`).
