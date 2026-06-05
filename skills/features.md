# ERP Application Features

> Status legend: ✅ Shipped (backend + frontend) · ◐ Partially shipped · ❌ Not started. See [`.task/task.md`](../.task/task.md) for the authoritative master checklist; each `skills/{module}/skill.md` carries the code map and links its `.task/{module}/{task,context}.md` pair.

## 1. Identity & Access Management (IAM) — ✅
- **Skill:** [`skills/iam/skill.md`](./iam/skill.md) · **Status:** [`.task/iam/task.md`](../.task/iam/task.md)
- Multi-tenant security & isolation (handle-based, per-tenant Passport client)
- RBAC via `module.feature.action` slugs; `.self` suffix for self-service variants
- `hasPermission()` on User (bypasses Gate); super-admin short-circuit
- Audit logging via `Auditable` trait

## 2. Sales (Order-to-Cash) — ✅
- **Skill:** [`skills/sales/skill.md`](./sales/skill.md) · **Status:** [`.task/sales/task.md`](../.task/sales/task.md)
- **CRM:** [`skills/crm/skill.md`](./crm/skill.md) — Leads, Opportunity Kanban, B2B/B2C Product Schedule, Appointments, polymorphic Activities
- Sales Operations: Customers → Quotations → Orders → Invoices → Subscriptions
- Tenant provisioning via `TenantProvisioningService`
- Target-flow refactor planned (see [`rules/hybrid_sales_business_flow.md`](../rules/hybrid_sales_business_flow.md))

## 3. Financial Management (FMS) — ◐
- **Skill:** [`skills/fms/skill.md`](./fms/skill.md) · **Status:** [`.task/fms/task.md`](../.task/fms/task.md)
- Customer Receipts (UI + service + balanced GL posting) ✅
- Estimates · AP/AR UI · Tax · Financial reports ❌ Planned

## 4. Accounting / General Ledger — ✅
- **Skill:** [`skills/accounting/skill.md`](./accounting/skill.md) · **Status:** [`.task/accounting/task.md`](../.task/accounting/task.md)
- Chart of Accounts (tree + balance summation), Journals + Ledger (immutable, reversible)
- Bank, Budgets, Exchange Rates (uppercase normalized)
- AR cycle (Receipts / Credit Note / Debit Note); AP cycle (Bills / Pay Bill / Reimbursement / Cash Advance / Settlement / Expense)
- Fiscal period locks ❌ Planned (P1 open)

## 5. Human Resource Management (HRM) — ✅
- **Skill:** [`skills/hrm/skill.md`](./hrm/skill.md) · **Status:** [`.task/hrm/task.md`](../.task/hrm/task.md)
- Recruitment ATS (Vacancies → Applications → Candidate Kanban → Interviewing → Offer → Onboarding)
- Workforce, Departments, Positions; encrypted PII on Employee
- Leave, Shifts, Attendance, Overtime
- Payroll periods + Payslips
- Performance Appraisals + 360-degree feedback
- Hierarchical HRM settings + Work Schedules
- Public careers portal (no-auth) + candidate quiz portal

## 6. eApprovals — ◐ (backend ✅, UI partial)
- **Skill:** [`skills/eapprovals/skill.md`](./eapprovals/skill.md) · **Status:** [`.task/eapprovals/task.md`](../.task/eapprovals/task.md)
- Workflows + levels + actions + notifications + escalation/delegation (shipped)
- Approval routing wired into Procurement (PO submit) and HRM
- Forms request / personal request UI pending

## 7. eDocuments (Explorer) — ✅
- **Skill:** [`skills/edocuments/skill.md`](./edocuments/skill.md) · **Status:** [`.task/edocuments/task.md`](../.task/edocuments/task.md)
- Folder tree + Document CRUD + versions + tags + share links (410/403/429) + acknowledgements
- Banned-ext/MIME guards; cycle-guarded folder move; public share viewer
- Pest isolation + share-link expiry tests pending

## 8. Documents (CMS) — ◐ (backend partial, UI ❌)
- **Skill:** [`skills/documents/skill.md`](./documents/skill.md) · **Status:** [`.task/documents/task.md`](../.task/documents/task.md)
- CMS folders + documents + check-in/out locking service
- Backend API alignment (camelCase resources, policies, permissions seeder) pending
- Explorer UI + concurrency modals + preview modals pending

## 9. Fleet Management — ✅
- **Skill:** [`skills/fleet/skill.md`](./fleet/skill.md) · **Status:** [`.task/fleet/task.md`](../.task/fleet/task.md)
- Vehicles + Fuel logs + Maintenance logs (camelCase, policies, monotonic mileage)
- Map overlay + tenant-scoped signed-URL receipts + `MaintenanceSchedulerJob` deferred
- Pest suite not yet started

## 10. Fixed Asset Management — ✅
- **Skill:** [`skills/assets/skill.md`](./assets/skill.md) · **Status:** [`.task/assets/task.md`](../.task/assets/task.md)
- Tracking + QR + custodian/location (settings-driven `asset_code_prefix`)
- Depreciation engines (SL / DDB / SYD) with NBV ≥ salvage invariant
- Revaluation (surplus/loss), Disposal (sale/scrap), Audit campaigns + Verification logs
- Balanced GL journals via `FmsIntegrationService`; HRM custodian via `Employee::assets()`
- P0 + P1 Pest shipped

## 11. Inventory Management — ✅ (FIFO + storefront planned)
- **Skill:** [`skills/inventory/skill.md`](./inventory/skill.md) · **Status:** [`.task/inventory/task.md`](../.task/inventory/task.md)
- Products (with variants + module link), hierarchical Categories
- Warehouses + Suppliers (with Vendor/AP extension: payable/expense FKs, bank details)
- Stock movements, reservations + transfers, Low-stock alerts
- Purchase Orders (full FSM, eApprovals integration, WAC on receive)
- FIFO + omnichannel pricing SSOT + storefront sync pending

## 12. Projects & Time Tracking — ✅
- **Skill:** [`skills/projects/skill.md`](./projects/skill.md) · **Status:** [`.task/projects/task.md`](../.task/projects/task.md)
- Projects + Tasks (list + Kanban board) + Timesheets (16h/day cap, leave block, closed-period lock)
- WBS / Gantt / dependency cycles / task drawer / approvals workflow deferred (schema gap)

## 13. Reporting & Analytics — ◐
- **Skill:** [`skills/reporting/skill.md`](./reporting/skill.md) · **Status:** [`.task/reporting/task.md`](../.task/reporting/task.md)
- Dashboard / Widget CRUD + `DashboardSummaryService` real aggregation queries
- Configurable widget builder UI · Scheduled reports · PDF/Excel export pending

## 14. Configuration / Settings — ✅
- **Skill:** [`skills/configuration/skill.md`](./configuration/skill.md) · **Status:** [`.task/configuration/task.md`](../.task/configuration/task.md)
- Tabs: Branding · Locale · Notifications · Security · Numbering · Modules (admin) · Platform (admin)
- 7 document-numbering prefixes (employee, candidate, quotation, order, invoice, subscription, PO) with `GenerationRetry` on 23505
- `UpdateSettingsRequest` per-key + numbering regex validation
- HRM Settings sub-pages: recruitment, leave, attendance, payroll, performance

## 15. Point of Sale (POS) — ✅
- **Skill:** [`skills/pos/skill.md`](./pos/skill.md) · **Status:** [`.task/pos/task.md`](../.task/pos/task.md)
- Terminals + Shifts (cashier+terminal mutex, expected_cash variance + supervisor reconcile)
- Orders: idempotent `client_uuid` checkout (atomic stock-out + balanced GL) + void (compensating movements + reverse journal)
- Touch register, barcode scanner focus, thermal-receipt CSS printout
- Offline IndexedDB resiliency deferred

## 16. Ecommerce (B2C) — ✅
- **Skill:** [`skills/ecommerce/skill.md`](./ecommerce/skill.md) · **Status:** [`.task/ecommerce/task.md`](../.task/ecommerce/task.md)
- Storefront (catalog → cart → checkout → account → order receipt) under `/shop/**`
- Separate `shop` Passport guard (shopper role); guest checkout supported
- Admin orders/refunds/customers with FSM actions
- FMS cash-receipt journal on confirm; Credit Note path on refund approve
- Webhook signing + replay protection
- Blocked on `INV-RESERVE` (15-min TTL) + `INV-STOREFRONT` (cached public products)

## 17. Unified Calendar & Holiday Management — ✅
- **Skill:** [`skills/calendar/skill.md`](./calendar/skill.md) · **Status:** [`.task/calendar/task.md`](../.task/calendar/task.md)
- Holidays (`overtime_multiplier` + compensatory Sat/Sun → Monday)
- Custom Calendar Events table; unified-events query unions holidays/leaves/shifts/CRM appointments/custom (90-day guard)
- Privacy masking (sick-leave titles hidden without `hrm.leave.read`)
- Frontend: `/calendar/events` with 42-cell month grid + 5 source layer chips
- Attendance reconciler + payroll workday subtraction deferred

