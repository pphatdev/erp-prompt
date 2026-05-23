# Dashboard — Context

## Goal
Single endpoint returning all KPIs needed for both the Administrator dashboard and the Customer (tenant) dashboard, with module-aware gating.

## Files
| File | Role |
|---|---|
| `backend/app/Tenants/Modules/Reporting/Services/DashboardSummaryService.php` | All aggregation queries |
| `backend/app/Tenants/Modules/Reporting/Controllers/DashboardSummaryController.php` | Invokable controller |
| `backend/routes/tenant.php` | `GET /api/v1/dashboard/summary` |
| `frontend/composables/useDashboard.ts` | Singleton data composable |
| `frontend/pages/dashboard.vue` | Admin + Customer views |

## API Response Shape
```json
{
  "period": { "start": "2026-05-01", "end": "2026-05-23" },
  "active_module_slugs": ["hrm", "sales", "inventory"],
  "kpis": {
    "employees": { "total": 45, "active": 43, "on_leave": 2, "new_mtd": 1 },
    "leave": { "pending": 7, "approved_mtd": 15, "rejected_mtd": 2 },
    "attendance": { "present_today": 38 },
    "sales": { "revenue_mtd": 84500.00, "revenue_prev_mtd": 71200.00, "revenue_change_pct": 18.7, "orders_mtd": 42, "active_customers": 156, "open_leads": 18 },
    "inventory": { "total_products": 94, "low_stock": 4 },
    "projects": { "active": 8, "open_tasks": 34, "completed_tasks_mtd": 89 },
    "finance": { "total_accounts": 28, "unposted_journals": 1 }
  },
  "charts": {
    "revenue_trend": [{ "label": "May 17", "amount": 4200.00 }, ...],
    "headcount_by_dept": [{ "label": "Engineering", "count": 15 }, ...]
  },
  "recent": {
    "orders": [{ "id": "...", "number": "ORD-001", "customer_name": "...", "total": 1200.00, "status": "confirmed", "date": "..." }],
    "leaves": [{ "id": "...", "employee_name": "...", "type": "Annual", "days": 3, "status": "pending", "start_date": "..." }]
  }
}
```

## Gotchas resolved
- `AttendanceLog` has no `type=clock_in` — uses `status` field with constants `STATUS_PRESENT`, `STATUS_LATE`, `STATUS_EARLY_OUT`, `STATUS_HALF_DAY`
- `Employee` has no `leaves` HasMany relationship — query `Leave` table directly
- Project statuses: `planning | active | on_hold | completed` (no archived/cancelled)
- Invoice statuses: `new | confirmed | cancelled | paid` — use `paid` for revenue sums
