# Main Project Tasks

This file tracks the overarching progress across all ERP modules. Use the links below to navigate to the specific task checklists and contexts for each module.

## Core Modules
- [x] [IAM (Identity & Access Management)](./iam/task.md) — Both Backend API and Frontend screens (Login, Users, Roles) fully complete.
- [x] [Sales (O2C & CRM)](./sales/task.md) — Hybrid Sales full stack: backend (Quote → Order → Invoice + Subscription + Stock deduction, one transactional fulfillment) + frontend (card-grid funnel across Quotations / Sales Orders / Invoices / Subscriptions with inline-variant quote builder, atomic-confirm warnings, journal-entry surface). Provisioning listener + credit-note + tax engine still planned.
- [x] [FMS (Financial Management)](./fms/task.md) — Backend accounts & balanced journal entry engine complete with tests; Frontend pending.
- [x] [HRM (Workforce & Payroll)](./hrm/task.md) — Phases 1–4 complete on Backend & Frontend (Workforce, Leave, Payroll, Recruitment); Performance review complete.
- [x] [HRM Time Off & Attendance](./time_off_attendance/task.md) — Docs + backend implementation complete (5 slices): shifts/employee-shifts CRUD, attendance logs with Haversine geofence + IPv4 whitelist, overtime requests, daily reconciliation job + payroll deductions/OT earnings, half-day leave with pro-rata accrual. Pending: Pest tests, frontend UI, eApprovals integration for overtime.

## Specialized Modules
- [x] [eApprovals](./eapprovals/task.md) — Backend engine, workflow actions, and tests complete; Frontend pending.
- [x] [Inventory Management](./inventory/task.md) — Backend schema, stock movement transactions, and tests complete; Frontend Products UI complete.
- [x] [eDocuments (Policy Explorer)](./edocuments/task.md) — Backend document & folder services and tests complete; Frontend pending.
- [x] [Fixed Asset Management](./assets/task.md) — Backend depreciation engine & tests complete.
- [x] [Fleet Management](./fleet/task.md) — Backend maintenance, fuel logs, and tests complete.
- [x] [Project Management](./projects/task.md) — Backend tasks, timesheets, and tests complete; Frontend Tasks UI complete.
- [x] [Document Management (CMS)](./documents/task.md) — Backend CMS folders, files checkout/checkin, and tests complete.
- [x] [Reporting & Analytics](./reporting/task.md) — Backend dashboards, widgets, and tests complete; Frontend Dashboard UI complete.

## Global UI & Architecture
- [x] [Layout & Visual Specification](./design/task.md) — Design tokens (§2, §3, §9) synced with global frontend stylesheet and layout templates.
- [x] [File Upload Process Rules](./uploads/task.md) — Standardized file validation, chunking, and multi-tenant isolation rules.
- [x] [Configuration & Tenant Settings](./configuration/task.md) — Key/value `tenant_settings` store, Branding/Locale/Notifications/Security tabs, `/settings/public` for login-screen branding, customizer↔backend sync. Logo upload & module toggles still planned.



