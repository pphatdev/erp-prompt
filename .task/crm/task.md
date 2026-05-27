# Task Checklist: CRM Module

## Checklist — Backend

### Phase 1 — Database & Decoupling
- [x] Migrate `Lead` model from `Sales` to the `Crm` namespace — LeadController/LeadService moved to `App\Tenants\Modules\Crm`.
- [x] Create `2024_01_01_000044_create_opportunities_table.php`
- [x] Create `2024_01_01_000045_create_crm_contacts_table.php`
- [x] Create `2024_01_01_000046_create_crm_activities_table.php`
- [x] Implement `Opportunity`, `CrmContact`, and `CrmActivity` tenant models with `BelongsToTenant` and `Auditable` traits.

### Phase 2 — Lead Conversion Engine
- [x] `App\Tenants\Modules\Crm\Services\LeadService` created.
- [x] `LeadService::qualifyToOpportunity(Lead $lead, array $data)` — transaction: Customer (if missing) + CrmContact + Opportunity + Lead status=qualified.

### Phase 3 — Opportunity Pipeline & Kanban
- [x] `App\Tenants\Modules\Crm\Services\OpportunityService` created.
- [x] `updateStage` guards terminal states, enforces loss_reason, dispatches `OpportunityWon`.
- [x] `CreateDraftQuotationOnOpportunityWon` listener in Sales registered in `TenantServiceProvider`.

### Phase 4 — Polymorphic Activities
- [x] `App\Tenants\Modules\Crm\Services\ActivityService` — `logActivity` and `completeActivity`.
- [x] Enforce cross-tenant polymorphic activity blocks (tenant scope on trackable queries).

### Phase 5 — API Surface & Controllers
- [x] Routes registered in `routes/tenant.php`: leads qualify, opportunities stage, crm-contacts CRUD, crm-activities CRUD + complete.
- [x] `LeadController`, `OpportunityController`, `CrmContactController`, `CrmActivityController` implemented.
- [x] `LeadResource`, `OpportunityResource`, `CrmContactResource`, `CrmActivityResource` implemented.
- [x] Wire permissions for the features: `crm.leads.*`, `crm.opportunities.*`, `crm.contacts.*`, and `crm.activities.*` (LeadPolicy/OpportunityPolicy/CrmContactPolicy/CrmActivityPolicy + Gate::authorize() in controllers; backfill via `CrmPermissionSeeder`).

### Phase 8 — Appointments / Schedules calendar
Spec for the standalone "Schedules" sidebar entry (separate from the B2B/B2C Product Schedule that is nested under Opportunity). See [`skills/crm/rules.md`](../../skills/crm/rules.md) § 2.

- [x] Migration `2024_01_01_000051_create_crm_appointments_table` — columns: `subject`, `starts_at`, `ends_at`, `location`, `attendees` (jsonb), `opportunity_id` (FK cascade nullable), `lead_id` (FK nullOnDelete nullable), `status` (`scheduled`/`completed`/`cancelled`/`no_show`), `actor_id`, `cancel_reason`, `completed_at`, `cancelled_at`, `tenant_id` (+ window/status/tenant indices).
- [x] `CrmAppointment` model (`BelongsToTenant`, `Auditable`, `SoftDeletes`) with status constants + `isScheduled` / `isTerminal` helpers.
- [x] `CrmAppointmentService` — `schedule`, `reschedule`, `complete`, `cancel`, `markNoShow`, `listInWindow(start, end)` + time-range + terminal-state guards.
- [x] REST routes: `apiResource('crm-appointments')` + `POST /{id}/complete|cancel|no-show`; `?from=&to=` switches index to window mode.
- [x] `crm.appointments.{read,write,delete}` permission triplet seeded in `TenantDatabaseSeeder` + `CrmPermissionSeeder` (backfill); `CrmAppointmentPolicy` registered in `TenantServiceProvider`.
- [x] `pages/crm/schedules.vue` — agenda + week + month views; status filter; create/edit modal with attendee rows, lead/opportunity linking; complete/cancel/no-show in the modal footer.
- [x] Sidebar nav flipped to operational (`crm.appointments.read|write`).
- [x] Pest: `CrmAppointmentLifecycleTest` — schedule, time-range guard, complete/cancel/no-show FSM, window-query correctness, reschedule.

### Phase 9 — B2C variant of Product Schedule (Planned)
The shipped `opportunity_product_schedules` covers both B2B and B2C since the parent Opportunity's Customer determines the audience. Frontend follow-up only:

- [ ] Schedule editor UI exposes a "Cadence" preset matched to audience: B2C tends toward `one_time`, B2B tends toward `monthly`/`annual`. Defaults adapt to the linked Customer's `customer_type`.

> The standalone "Schedules (B2B/B2C)" sub-nav listing was dropped — Sales Pipeline is now a single Kanban page. Product schedules are accessed via the per-card package icon on the Opportunity Kanban.

### Phase 7 — B2B Product Schedule + Sales handoff refactor
Spec: [`rules/hybrid_sales_business_flow.md`](../../rules/hybrid_sales_business_flow.md), [`skills/crm/rules.md`](../../skills/crm/rules.md), [`skills/crm/flow.md`](../../skills/crm/flow.md).

- [x] Migration `2024_01_01_000047_create_opportunity_product_schedules_table` — columns: `opportunity_id`, `product_id`, `variant_id`, `quantity`, `estimated_unit_price`, `cadence`, `notes`, `tenant_id` (+ FKs, soft deletes).
- [x] Migration `2024_01_01_000048_make_opportunity_customer_nullable` — allows opportunities without a Customer.
- [x] `OpportunityProductSchedule` model (`BelongsToTenant`, `Auditable`, `SoftDeletes`).
- [x] `OpportunityProductScheduleService` — `addLine`, `updateLine`, `removeLine`, `snapshotToQuotationItems`.
- [x] `OpportunityProductScheduleController` + Resource — REST CRUD nested under `/opportunities/{opportunity}/product-schedule`.
- [x] Lock schedule lines when parent Opportunity is `won` or `lost`.
- [x] Frontend Opportunity Kanban editor: `ProductScheduleEditor.vue` modal mounted from each card; stage list realigned to `new/contacted/qualified/proposal/negotiation/won/lost`; useCrm extensions for nested `/opportunities/{id}/product-schedule` CRUD.
- [x] **Drop Customer creation from `LeadService::qualifyToOpportunity`** — moved to `QuotationService::win` in Sales.
- [x] Replace `OpportunityWon` event with `LeadQualified` (payload: lead + opportunity with `productSchedule` eager-loaded).
- [x] Delete the `CreateDraftQuotationOnOpportunityWon` listener; Sales adds its own `HandleLeadQualified` (creates a CrmActivity type=task on the Opportunity).
- [x] Pest tests for the new schedule service + handoff event dispatch (`OpportunityProductScheduleTest`, updated `LeadQualificationTest` and `OpportunityPipelineTest`).

### Phase 6 — Testing & Verification
- [x] Implement tests under `tests/Feature/Tenant/Crm/`:
  - `OpportunityPipelineTest` checking stage transitions and loss reason requirements.
  - `LeadQualificationTest` checking transactional integrity and customer creation.
  - `CrmTenancyIsolationTest` asserting Tenant A cannot read Tenant B's contacts/activities.
  - `CrmPolymorphicActivityTest` verifying cross-tenant polymorphic activity blocks.

---

## Checklist — Frontend (Nuxt & PrimeVue)

### Phase 1 — Types & Composables
- [x] Create `frontend/types/crm.ts` detailing Lead, Opportunity, Contact, and Activity wire contracts.
- [x] Create `frontend/composables/useCrm.ts` housing API wrappers for CRM actions with proper tenant header injections.

### Phase 2 — CRM Layout & Pages
- [x] Create a dedicated `pages/crm/` route directory.
- [x] `pages/crm/leads.vue` — Custom Leads index with Create and qualification dialog pipelines.
- [x] `pages/crm/opportunities.vue` — Interactive Kanban pipeline board using PrimeVue Cards and drag-and-drop.
  - [x] Require input of `loss_reason` in a popup modal if deal is dragged to "Lost".
- [x] `pages/crm/contacts.vue` — Account contact index, create modal, and detail view.
- [x] `pages/crm/activities.vue` — Polymorphic interaction log rendering as a sleek PrimeVue vertical timeline with custom icons per type.

### Phase 3 — Navigation Integration
- [x] Modify `layouts/default.vue` to add a new top-level **CRM** navigation group gated by `crm.leads.read` / `crm.opportunities.read`.
- [x] Update Breadcrumb override labels to support CRM routes.
