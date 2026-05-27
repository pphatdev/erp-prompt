# Task Context: CRM (Customer Relationship Management)

## Objective
Implement a fully decoupled, multi-tenant CRM module scoping raw Leads, Opportunity pipeline stages (Kanban board), B2B Contacts linked to corporate Accounts (Customers), and a polymorphic Interaction Timeline (Activities).

---

## Architectural Boundaries
1. **Module Segregation:** CRM is strictly pre-sale. The Opportunity's `B2B Product Schedule` is the artefact handed off to Sales. The Quotation/Order/Invoice/Subscription lifecycle lives entirely in Sales.
2. **Tenancy Scoping:** All tables (`leads`, `opportunities`, `opportunity_product_schedules` [planned], `crm_contacts`, `crm_activities`) reside within tenant-isolated databases and inherit the `BelongsToTenant` trait.
3. **Audit Trail (P1):** Use the `Auditable` trait on all CRM models.
4. **Handoff Contract (Planned):** Stage transition to `won` dispatches `LeadQualified(lead, opportunity)`. Sales' `HandleLeadQualified` listener creates a "Create Quotation from Lead" rep task — Sales **does not** auto-create the Quotation. Customer creation is deferred to the Sales-side Quotation `won` transition.

---

## Decoupling Strategy
* **Legacy Context:** Currently, `Lead` model, `CrmService`, and `LeadController` are grouped within the `Sales` namespace (`App\Tenants\Modules\Sales`).
* **Target Refactor:** Move these classes to `App\Tenants\Modules\Crm` to establish a distinct `Crm` domain model, separating sales billing logic from CRM pre-sale pipeline operations.
* **Frontend Modules:** Move Nuxt CRM pages from `pages/sales/` (such as `leads/`) to a dedicated `pages/crm/` route namespace.

---

## Environment & Tech Stack
* **Backend:** Laravel 11, PostgreSQL (Tenant DB), Laravel Passport (Authentication).
* **Frontend:** Nuxt 3, Tailwind CSS 4+, PrimeVue (Kanban Cards, Timeline, Icons).
* **Testing:** Pest PHP (Backend tests under `tests/Feature/Tenant/Crm/`).

---

## Customer Module Integration
* **Account-Contact Architecture:** In this ERP system, B2B Accounts are represented by the `Customer` model inside the Sales module. Contacts (`CrmContact`) represent specific people inside those accounts.
* **Lead Conversion:** The `LeadService` conversion must bridge to the `Customer` model. Leads without a `customer_id` must trigger the creation of a new `Customer` record before setting up Opportunities and Contacts.
* **Timeline Aggregation:** Ensure that the Customer profile view fetches and embeds related polymorphic activities (`crm_activities`) and opportunities to maintain a unified CRM view.
