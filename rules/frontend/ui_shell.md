# UI Shell Reproduction Guide

> The "shell" is the always-on chrome that wraps every page: the sidebar, topbar, breadcrumb, settings hub, dashboard, and tenant-branded theming. This document captures it precisely so a future Claude session can recreate the same look-and-feel.

All shell behavior is implemented in five files:

| File | Role |
|---|---|
| `frontend/layouts/default.vue` | Sidebar (rail + flyout + mobile drawer), topbar, breadcrumb, nav-group definitions |
| `frontend/app.vue` | Boot-time: `authStore.initializeAuth()`, `tenantStore.applyBrandToDocument()` |
| `frontend/assets/css/main.css` | CSS-variable tokens, `.glass-card`, dark-mode overrides, shimmer keyframes |
| `frontend/stores/tenant.ts` | Branding: `applyBrandToDocument()`, `syncBranding()` |
| `frontend/components/CustomizerOffcanvas.vue` | User-side theme picker (accent + light/dark/system) |

---

## 1. Layout

```
┌─────────────────────────────────────────────────────────────────┐
│ ┌──────┐  ┌─────────────────────────────────────────────────┐  │
│ │ Rail │  │ Topbar: menu·search·notif·tenant·profile        │  │
│ │ 260px│  ├─────────────────────────────────────────────────┤  │
│ │  ↕   │  │ Breadcrumb                                       │  │
│ │collap│  ├─────────────────────────────────────────────────┤  │
│ │se to │  │                                                  │  │
│ │ 70px │  │            <NuxtPage />                          │  │
│ │      │  │                                                  │  │
│ └──────┘  └─────────────────────────────────────────────────┘  │
└─────────────────────────────────────────────────────────────────┘
```

### Sidebar states

| State | Trigger | Width | Behavior |
|---|---|---|---|
| Expanded | Default on desktop | 260 px | Labels visible, full nav tree |
| Compact | Click collapse button (`top-5 -right-3`) | 70 px | Icons only |
| Flyout | Hover when compact | 260 px (overlay, `z-40 shadow-lg`) | Compact rail stays; flyout panel appears on top of content — does NOT push content |
| Mobile drawer | `< md` viewport, `sidebarOpen=true` | 260 px (slide-in) | Backdrop-blur overlay; closes on route change |

State refs in `layouts/default.vue`:

```ts
const sidebarOpen = ref(false)          // mobile drawer
const sidebarCollapsed = ref(false)     // desktop collapse to rail
const sidebarHovered = ref(false)       // hover-expand when collapsed
const isCompact = computed(() => sidebarCollapsed.value && !sidebarHovered.value)
const isFlyout = computed(() => sidebarCollapsed.value && sidebarHovered.value)
```

### Topbar

Left → right:
1. Mobile menu toggle (`md:hidden`)
2. Search input (`hidden md:flex`, ~80 px width, `⌘K` placeholder, `ti-search` icon)
3. Right cluster:
   - Notifications popover (currently demo data — 7 hardcoded items, `ti-bell`)
   - Tenant switcher (visible when user has access to multiple tenants; calls `tenantStore.setTenantByHandle()`)
   - Profile menu (avatar with initials, popover with Settings + Sign out)
   - Customizer button (`ti-palette`) → opens `CustomizerOffcanvas`

### Breadcrumb

- Path-based: splits the current route, maps each segment via `SLUG_LABELS` (`'iam' → 'IAM'`, `'crm' → 'CRM'`, etc.) and falls back to titleizing.
- Page-level override: a page can call `useBreadcrumbOverride().set('Edit Customer ACME Corp')`. Cleared on route change.
- UUID detection: segments that look like UUIDs are replaced with entity name set via `setEntityName(uuid, name)` from the detail page.

---

## 2. Nav data shape

The nav tree is declared inline in `layouts/default.vue` (constant `navGroups`). The item type:

```ts
interface NavItem {
    label: string
    icon: string                                          // Tabler class, e.g. 'ti-users'
    route?: string
    operational?: boolean                                 // false → renders as "Soon" stub
    badge?: string
    badgeVariant?: 'primary' | 'success' | 'warning' | 'danger' | 'info' | 'secondary'
    permission?: string | string[]                        // OR semantics for arrays
    moduleSlug?: string                                   // hidden if useModules().hasModule(slug) === false
    children?: NavItem[]
}

interface NavGroup {
    id: string
    label: string
    items: NavItem[]
}
```

### Gating function (must mirror this exactly)

```ts
const canSeeItem = (item: NavItem): boolean => {
    if (item.moduleSlug && !hasModule(item.moduleSlug)) return false   // fail-open until modules load
    if (item.children) return item.children.some(canSeeItem)           // parent visible iff any child visible
    if (!item.permission) return true                                  // unrestricted
    const slugs = Array.isArray(item.permission) ? item.permission : [item.permission]
    return slugs.some(slug => authStore.hasPermission(slug))           // OR semantics
}
```

### Standard nav groups

| Group id | Label | Notes |
|---|---|---|
| `main` | Main | Dashboard |
| `self-service` | My Workspace | Employee self-service items (Profile, Leaves, Payslips, Appraisals). Hidden when `authStore.isAdmin` |
| `apps` | Apps | Ecommerce, CRM, Sales, Finance, Inventory, HRM, eApprovals, Fleets (soon), Projects (soon), eDocuments (soon), Reports (soon) |
| `configurations` | Configurations | Apps Management, User Directory, Roles Matrix, Configuration (tabs) |

---

## 3. Settings hub

`/settings/index.vue` → redirect to `/settings/configuration/branding`.

| Tab | Route | Notes |
|---|---|---|
| Branding | `/settings/configuration/branding` | Color swatches + theme mode (light/dark/system) + logo URL |
| Locale | `/settings/configuration/locale` | Timezone, date format, currency |
| Notifications | `/settings/configuration/notifications` | Email/SMS toggles |
| Security | `/settings/configuration/security` | Session timeout, MFA |
| Numbering | `/settings/configuration/numbering` | 7 prefix inputs — see [`skills/configuration/numbering.md`](../../skills/configuration/numbering.md) |
| Modules | `/settings/configuration/modules` | **adminOnly** — middleware redirects non-admins to `/dashboard`. Drag/drop tree + toggle switches; `is_core` rows are locked. |
| Platform | `/settings/configuration/platform` | adminOnly — platform-wide flags |

Standalone (not under `configuration/`):
- `/settings/users` — user directory
- `/settings/roles` — roles matrix
- `/settings/apps/hrm/leave-types` — app-management example

### adminOnly tab gating

```ts
definePageMeta({
    middleware: [function () {
        const auth = useAuthStore()
        if (!auth.isAdmin) return navigateTo('/dashboard')
    }],
})
```

---

## 4. Dashboard

One file: `pages/dashboard.vue`. Two branches selected by `v-if="authStore.isAdmin"`.

### Admin dashboard (full grid)

KPI cards (in this order):

| Card | Source | Icon | Badge variant |
|---|---|---|---|
| Active Employees | `summary?.kpis?.employees?.active` | `ti-users` | `primary` |
| Revenue MTD | `summary?.kpis?.sales?.revenue_mtd` | `ti-currency-dollar` | `success` |
| Orders MTD | `summary?.kpis?.sales?.orders_mtd` | `ti-shopping-cart` | `info` |
| Active Customers | `summary?.kpis?.sales?.active_customers` | `ti-address-book` | `warning` |
| Pending Leaves | `summary?.kpis?.leave?.pending` | `ti-calendar-event` | `danger` |
| Total Products | `summary?.kpis?.inventory?.total_products` | `ti-package` | `secondary` |

Charts:
- **7-day revenue trend** — manual CSS bar chart, `useDashboard().revenueBars` provides `{ label, amount, height }[]`. Gradient `from-[--color-primary] to-[--color-info]`.
- **Headcount by department** — horizontal bars, `useDashboard().headcountBars` provides `{ label, count, width }[]`.

Recent tables: last 5 orders, last 5 pending leaves.

### Customer dashboard (module-gated)

Conditionally renders blocks based on `useModules().hasModule(slug)`:

```vue
<template v-if="hasModule('hrm')"> ... My Team + Leave Requests ... </template>
<template v-if="hasModule('sales')"> ... Revenue + Customers ... </template>
<template v-if="!hasModule('hrm') && !hasModule('sales')"> ... "No active modules yet" placeholder ... </template>
```

### Loading + error states

- Shimmer class `.dash-skeleton` (200 % width gradient + `dash-shimmer` keyframe, 1.4 s infinite). Used on KPI numbers, chart bars, table rows while `loading === true`.
- Error: alert box with `ti-alert-triangle` + message + Retry button calling `refresh()`.
- Refresh button: top-right of dashboard, calls `useDashboard().load(true)`.

---

## 5. Branding / theme system

### Single applier

`tenantStore.applyBrandToDocument()` is the only function that writes to `document.documentElement.style`. Call order on each page mount: `app.vue` → `applyBrandToDocument()`.

```ts
applyBrandToDocument() {
    if (!import.meta.client) return
    const userAccent = localStorage.getItem('accent')
    document.documentElement.style.setProperty(
        '--color-primary-rgb',
        userAccent || this.currentTenant?.primaryColor || DEFAULT_PRIMARY
    )
}
```

**Precedence**: `localStorage.accent` (user override) > `currentTenant.primaryColor` (tenant branding from backend) > `DEFAULT_PRIMARY` (`'59 130 246'` — `#3b82f6`).

### localStorage keys

| Key | Purpose |
|---|---|
| `auth_token` / `auth_refresh_token` / `auth_expires_at` | Token persistence |
| `tenant_handle` | Active tenant handle |
| `tenant_primary:{handle}` | Per-tenant cached primary color (avoids flash on tenant switch) |
| `theme` | `'light' \| 'dark' \| 'system'` |
| `accent` | User-chosen RGB triple override (highest priority) |

### Dark mode

Selector: `[data-bs-theme="dark"]` on `<html>`. Toggled by `CustomizerOffcanvas.setMode()`. `app.vue` `onMounted` resolves: `localStorage.theme` → existing attr → `prefers-color-scheme` → `'light'`.

### CSS variable inventory (light defaults from `main.css`)

```css
--color-primary-rgb: 59 130 246;       /* #3b82f6 */
--color-success-rgb: 16 185 129;
--color-warning-rgb: 245 158 11;
--color-danger-rgb: 239 68 68;
--color-info-rgb: 14 165 233;

--bg-layout: #f8fafc;
--bg-card:   #ffffff;
--border-color: #e2e8f0;

--text-heading: #0f172a;
--text-body:    #475569;
--text-muted:   #94a3b8;

--shadow-sm: ...
--shadow-md: ...
--shadow-lg: ...
```

Derived utilities like `rgb(var(--color-primary-rgb))` and `rgb(var(--color-primary-rgb) / 0.1)` are used throughout — never inline a hex value when a token applies.

---

## 6. Skeleton + shimmer pattern

```css
.nav-skeleton {
    background: linear-gradient(90deg, var(--bg-card) 0%, #f1f5f9 50%, var(--bg-card) 100%);
    background-size: 200% 100%;
    animation: skeleton-shimmer 1.4s linear infinite;
}
@keyframes skeleton-shimmer { 0% { background-position: 200% 0; } 100% { background-position: -200% 0; } }
```

Two ready-made variants: `.nav-skeleton` (sidebar loading) and `.dash-skeleton` (dashboard cards). Reuse them — don't invent new shimmer classes per page.

---

## 7. Adding a new sidebar entry

Find the matching group in `navGroups` (`'apps'`, `'configurations'`, ...), then insert an item. Example: add a "Suggestions" page under HRM.

```ts
// inside the 'apps' group → 'Human Resource' children
{
    label: 'Suggestions',
    icon: 'ti-bulb',
    route: '/hrm/suggestions',
    operational: true,
    permission: 'hrm.suggestions.read',
    moduleSlug: 'hrm',
}
```

That's it — `canSeeItem()` handles permission + module gating; loading skeleton + flyout behavior are inherited from the layout.

---

## 8. Adding a new settings tab

1. Add a page under `pages/settings/configuration/{slug}.vue`.
2. Register it in the Settings sub-nav (inside `layouts/default.vue` → `configurations` group → `'Configuration'` parent → `children`).
3. If admin-only, add the middleware redirect (see § 3 adminOnly snippet).
4. Read/write settings via `useSettings()` → `PUT /settings` with `{ settings: [{ key, value }, ...] }`. Backend reads through `SettingService::bulkSet()` which wraps the loop in `DB::transaction`.
