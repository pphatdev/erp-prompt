# Dashboard — Task Tracker

Status: COMPLETED (2026-05-23)

## Checklist
- [x] Design API response shape (`period`, `active_module_slugs`, `kpis`, `charts`, `recent`)
- [x] `DashboardSummaryService` — real aggregation queries per module
  - [x] HRM: employees (total, active, on_leave, new_mtd)
  - [x] HRM: leave (pending, approved_mtd, rejected_mtd)
  - [x] HRM: attendance today (present_today using STATUS_ constants)
  - [x] Sales: revenue_mtd, revenue_prev_mtd, revenue_change_pct, orders_mtd, active_customers, open_leads
  - [x] Inventory: total_products, low_stock (raw SQL subquery vs stock_movements)
  - [x] Projects: active (planning/active/on_hold), open_tasks, completed_tasks_mtd
  - [x] Finance: total_accounts, unposted_journals (draft status)
  - [x] Charts: 7-day revenue trend + headcount by department (Employee groupBy dept)
  - [x] Recent: last 5 orders with customer name, last 5 pending leaves with employee+type
  - [x] `safely()` wrapper for graceful degradation on empty tables
  - [x] 5-min Cache keyed `tenant_{id}_dashboard_summary_{user_id}`
- [x] `DashboardSummaryController` — invokable, injects service via DI
- [x] Route: `GET /api/v1/dashboard/summary` (inside auth:api group)
- [x] `useDashboard.ts` — singleton composable, 5-min client staleness, `revenueBars`, `headcountBars`, `formatCurrency`
- [x] `pages/dashboard.vue` — complete rewrite
  - [x] Admin view: 6-card KPI grid, revenue chart, headcount bars, today's pulse, recent orders/leaves tables
  - [x] Customer view: module-gated (hrm / sales sections), fallback empty state
  - [x] Shimmer skeletons on every loading state
  - [x] Error banner with retry
  - [x] Refresh button

## Key decisions
- All queries run regardless of `is_active` module flag — `active_module_slugs` returned for frontend gating
- Admin sees everything; customer sees only sections where their module slug is active
- `AttendanceLog` uses `status` field (present/late/early_out/half_day), not type=clock_in
- `Employee` has no `leaves` HasMany — on_leave count queries `Leave` table directly with distinct employee_id
- Project "active" = planning | active | on_hold (no archived/cancelled statuses exist)
- Low-stock uses raw SQL subquery: `minimum_stock_level > SUM(in) - SUM(out)` on stock_movements
