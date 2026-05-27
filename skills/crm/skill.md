---
name: crm
description: Manage leads, the Sales Pipeline (opportunities + B2B/B2C product schedules + stages), appointment scheduling, the interaction timeline, B2B contacts, and the qualified-lead handoff to Sales.
---
# Customer Relationship Management (CRM)

Use this skill when developing or modifying features related to:
- raw **Leads**
- the **Sales Pipeline**: Opportunities, B2B/B2C Product Schedules, pipeline stages (Contacted / Won / Lost)
- **Schedules** (appointment calendar / timeline view — demos, follow-ups, meetings)
- the **Interaction Timeline** (polymorphic activities)
- B2B Contacts
- the qualified-lead handoff into Sales

## Module surface (sidebar)

```
CRM
├── Leads                          — raw prospects
├── Sales Pipeline                 — single Kanban page; all stages (new/contacted/qualified/proposal/negotiation/won/lost) shown as columns; in-page stage filter via the column header strip
├── Schedules                      — appointment calendar (CrmAppointment)
├── Interaction Timeline           — CrmActivity
└── B2B Contacts
```

The **B2B/B2C Product Schedule** (`OpportunityProductSchedule`) is a per-Opportunity sub-resource — opened by clicking the package icon on a card in the Sales Pipeline Kanban. It's not a separate nav entry. The standalone "Schedules" group is the **appointment calendar** (`crm_appointments` entity).

## Read First
- `skills/crm/overview.md` — Conceptual overview, sub-modules, and scope definitions.
- `skills/crm/rules.md` — Database schemas, permission grid, services architecture, FSM constraints, B2B Product Schedule entity, planned handoff event.
- `skills/crm/flow.md` — Mermaid flows for Lead → Opportunity → Product Schedule → Qualified → Sales handoff. Includes both Shipped and Planned diagrams.
- `skills/crm/testing.md` — QA matrices and test templates.
- `rules/hybrid_sales_business_flow.md` — End-to-end CRM→Sales→Tenant lifecycle (source of truth for cross-module status rules).

---

## Core Developer Workflows

1. **Lead Sourcing & Capturing:** Catch prospects, register their acquisition channel (`source`), track projected value, and store as `App\Models\Tenant\Lead`.
2. **Lead-to-Opportunity Conversion (Qualification):**
   * Execute `LeadService::qualifyToOpportunity(Lead $lead, array $opportunityData)`.
   * Keep this process inside a strict database transaction (`DB::transaction`).
   * **Shipped:** If a related B2B Customer doesn't exist, create it together with a linked `CrmContact`. **Planned:** stop creating Customer here — it moves to Quotation `won` in Sales.
   * Automatically transition the Lead status to `qualified`.
3. **B2B/B2C Product Schedule:**
   * On the Opportunity detail page, build a line-item table of products-of-interest: product + variant + quantity + estimated price + cadence (`one_time` / `monthly` / `annual`).
   * The same `opportunity_product_schedules` entity covers B2B (Account-tied) and B2C (Contact-tied) sales — the parent Opportunity's Customer determines the audience.
   * Persist via `OpportunityProductScheduleService`. Lines lock once the parent Opportunity reaches a terminal stage.
   * On `won`, the schedule is snapshotted as default lines for the Sales-side Quotation builder.
4. **Appointments / Schedules (Planned):**
   * Calendar + timeline view of CRM appointments (demos, follow-ups, technical reviews).
   * New `crm_appointments` entity — fields: `subject`, `starts_at`, `ends_at`, `location`, `attendees[]`, `opportunity_id?`, `lead_id?`, `status` (`scheduled`/`completed`/`cancelled`/`no_show`).
   * Surfaces under `pages/crm/schedules.vue` with month/week/agenda toggle.
5. **Pipeline Progress (Kanban):**
   * Map opportunities across visual columns (`new`, `contacted`, `qualified`, `proposal`, `negotiation`, `won`, `lost`).
   * Maintain strict state progression using `OpportunityService::updateStage(Opportunity $opp, string $stage, ?string $lossReason = null)`.
   * Enforce supplying a non-empty `loss_reason` if stage changes to `lost`.
   * Terminate transition attempts out of final stages `won` and `lost`.
6. **Interaction Timeline (Polymorphic Activities):**
   * Keep track of calls, emails, meetings, notes, and tasks using a single `CrmActivity` model.
   * Relate activities via the `trackable` morph relation.
   * Ensure the trackable target belongs to the active tenant.
7. **Qualified Handoff to Sales (Planned):**
   * `OpportunityService::updateStage` dispatches `LeadQualified` on `won`. Sales surfaces a "Create Quotation from Lead" task — the rep explicitly creates the Quotation (no auto-creation).

---

## Critical Rules

### 1. Multi-Tenant Query Scoping (P0)
Always utilize the `BelongsToTenant` trait on all CRM-related models: `Lead`, `Opportunity`, `OpportunityProductSchedule` *(planned)*, `CrmContact`, `CrmActivity`. Bypassing or leaking cross-tenant records is a P0 security failure.

### 2. Transaction Boundaries (P0)
Multi-table inserts (qualifying a Lead, snapshotting a schedule to a Quotation, completing an activity that affects parent state) must reside inside a single `DB::transaction`. A crash at any point must roll back all tables.

### 3. Encrypted PII Fields (P0)
Database-level encryption for `CrmContact.email` and `CrmContact.phone`. Use Eloquent `'encrypted'` casts.

### 4. Controller Resource Serialization (P0)
Return the `JsonResource` directly from action methods. Never wrap a resource array inside `response()->json([...])` — strips `whenLoaded()` `MissingValue` sentinels and produces `[object Object]` in JSON.

### 5. Cross-module handoff via event, not direct dependency (Planned)
CRM never instantiates Sales classes. The `LeadQualified` event is the only contract; Sales owns the listener (`HandleLeadQualified`) and is free to evolve its handoff behaviour without touching CRM code.

---

## Troubleshooting

- **`Foreign polymorphic injection error`**: Activity creation attempted with a `trackable_id` of a Lead/Opportunity/Customer that belongs to another tenant. `ActivityService::logActivity` validates the trackable exists in the active tenant and blocks the insert.
- **`Opportunity stage transition locked`**: Attempted to move a deal out of `won` or `lost`. Terminal stages are locked. Create a new opportunity.
- **`Uncaught database truncation on lead conversion`**: `LeadService::qualifyToOpportunity` transaction failed to roll back. Verify the transaction wrapper is present and re-throws.
- **Quotation didn't appear after Opportunity Won** *(Planned behaviour)*: This is intentional — auto-creation is removed. Look for the "Create Quotation from Lead" task in the rep's Sales inbox.
- **Product Schedule lines disappeared after `won`** *(Planned)*: Lines are snapshotted to the Quotation, then the source schedule becomes read-only. View the snapshot on the resulting Quotation detail page.
