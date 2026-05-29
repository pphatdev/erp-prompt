# ERP Application Features

> Status legend: ✅ Shipped (backend + frontend) · ◐ Partially shipped · ❌ Not started. See `.task/task.md` for the authoritative master checklist.

## 1. Identity & Access Management (IAM) — ✅
- **[Detailed Specification: IAM](./iam/overview.md)** · **[Operational skill](./iam/skill.md)**
- Multi-Tenant Security & Isolation
- Role-Based Access Control (RBAC) — `module.feature.action` slugs
- `hasPermission()` on User; super-admin short-circuit
- Identity Management (Passport password grant); MFA + SSO planned
- Audit logging via `Auditable` trait

## 2. Sales (Order-to-Cash) — ✅
- **[Detailed Specification: Sales](./sales/overview.md)** · **[Operational skill](./sales/skill.md)**
- **[Customer Relationship Management (CRM)](./crm/overview.md)** — Lead intake, Opportunity pipeline (Kanban), B2B/B2C Product Schedule, Appointments, polymorphic Activities
- Sales Operations: Customers → Quotations → Orders → Invoices → Subscriptions
- Tenant provisioning (`TenantProvisioningService`)
- Target-flow refactor planned (status enums + Customer-creation moves to Quotation `won`)

## 3. Financial Management (FMS) — ◐
- **[Detailed Specification: FMS](./fms/overview.md)**
- General Ledger (GL) & Chart of Accounts ✅
- Journal Entries + Ledger ✅
- Payments + Estimates ❌ Planned
- Accounts Payable (AP) & Accounts Receivable (AR) UI ❌
- Tax Compliance & Reporting ❌


## 4. Human Resource Management (HRM) — ✅
- **[Detailed Specification: HRM](./hrm/overview.md)**

- Recruitment (Talent Acquisition & ATS)
- Workforce Management (Employee Profiles)
- Time Off & Leave Management
- Payroll & Compensation Engine
- Employee Feedback & Suggestions
- Performance Appraisals & Reviews
- Employee Notes & Documentation


## 5. eApprovals — ◐ (backend ✅, UI ❌)
- **[Detailed Specification: eApprovals](./eapprovals/overview.md)**
- Forms Request: [Leave, Overtime, Expense, etc.]
- Personal Request
- Approval (For Approvers)
- Approval Status
- Notification & Alerts
- Audit & Compliance Tracking

## 6. eDocuments (Explorer) — ◐ (backend ✅, UI ❌)
- **[Detailed Specification: eDocuments](./edocuments/overview.md)**
- Central Policy & SOP Repository
- Multi-System File Explorer (Aggregated View)
- Advanced Full-Text Search & Metadata Filtering
- Document Categorization & Tags
- Role-Based Access to Policies
- Public Link Sharing (Secure & Expiring)
- Document Acknowledgement Tracking

## 7. Fleet Management — ❌
- **[Detailed Specification: Fleet Management](./fleet/overview.md)**
- Vehicle & Asset Tracking
- Maintenance Scheduling & Tracking
- Fuel & Expense Management
- Trip & Route Optimization
- Telematics Integration
- Fleet Analytics & Reporting

## 8. Fixed Asset Management — ❌
- **[Detailed Specification: Fixed Asset Management](./assets/overview.md)**
- Asset Tracking
- Depreciation Management
- Asset Disposal Management
- Asset Revaluation Management
- Asset Retirement Management

## 9. Inventory Management — ✅ (FIFO + eCommerce sync planned)
- **[Detailed Specification: Inventory Management](./inventory/overview.md)** · **[Operational skill](./inventory/skill.md)**
- Inventory & Warehouse Management
- Procurement & Procure-to-Pay (P2P)
- Logistics & Distribution Tracking
- Order & Fulfillment Management
- Returns & Warranty Management
- Vendor & Supplier Management
- Inventory Analytics & Optimization

## 10. Project Management — ◐ (backend ✅, UI ❌)
- **[Detailed Specification: Project Management](./projects/overview.md)**
- Project Planning & Scheduling
- Task Management & Tracking
- Resource Allocation & Management
- Budgeting & Cost Control
- Time Tracking & Expense Management
- Project Collaboration & Communication
- Project Analytics & Reporting

## 11. Document Management — ◐ (backend ✅, UI ❌)
- **[Detailed Specification: Document Management](./documents/overview.md)**
- Document Storage & Retrieval
- Document Version Control
- Document Workflow Management
- Document Security & Access Control
- Document Audit & Compliance Tracking

## 12. Reporting & Analytics — ◐
- **[Detailed Specification: Reporting & Analytics](./reporting/overview.md)**
- Dashboard infrastructure + `DashboardSummaryService` ✅
- Dashboard & Reporting
- Data Visualization & Analytics
- Report Export & Sharing
- Report Customization & Personalization
- Report Scheduling & Delivery
- Report Security & Access Control
- Report Audit & Compliance Tracking

## 13. Configuration & Tenant Settings — ✅
- **[Detailed Specification: Configuration](./configuration/overview.md)**
- Tabs: Branding · Locale · Notifications · Security · Numbering · Modules (admin) · Platform (admin)
- See [`./configuration/numbering.md`](./configuration/numbering.md) for the 7 document-numbering prefixes
- Company Profile & Legal Information
- Localization & Regional Settings (Currency, Timezone)
- Branding & UI Customization (Logos, Colors)
- Module Enablement & Feature Toggles
- Global Security Policies
