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
- [ ] Wire permissions for the features: `crm.leads.*`, `crm.opportunities.*`, `crm.contacts.*`, and `crm.activities.*`.

### Phase 6 — Testing & Verification
- [ ] Implement Pest tests under `tests/Feature/Tenant/Crm/`:
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
