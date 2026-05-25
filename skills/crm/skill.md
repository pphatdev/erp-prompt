---
name: crm
description: Manage prospects, contacts, B2B customer relationships, interaction timelines, Kanban pipelines, and sales forecasting analytics.
---
# Customer Relationship Management (CRM)

Use this skill when developing or modifying features related to pre-sale raw leads, opportunities/deals pipeline (Kanban), B2B customer contacts, polymorphic activity tracking, or sales forecasting analytics.

## Read First
- `skills/crm/overview.md` — Conceptual overview, sub-modules, and scope definitions.
- `skills/crm/rules.md` — Database design schemas, permission grids, services architecture, FSM constraints, and response practices.
- `skills/crm/flow.md` — Visual flows (Mermaid) for Lead qualification, Opportunity stages, and backend call graphs.
- `skills/crm/testing.md` — QA matrices, test guidelines, and Pest PHP test templates.

---

## Core Developer Workflows

1. **Lead Sourcing & Capturing:** Catch prospects, register their acquisition channel (`source`), track projected value, and store as `App\Models\Tenant\Lead`.
2. **Lead-to-Opportunity Conversion (Qualification):**
   * Execute `LeadService::qualifyToOpportunity(Lead $lead, array $opportunityData)`.
   * Keep this process inside a strict database transaction (`DB::transaction`) to prevent partial creation errors.
   * If a related B2B corporate customer account (`Customer`) doesn't exist, create it together with an linked Contact (`CrmContact`).
   * Automatically transition the Lead status to `qualified`.
3. **Pipeline Progress tracking (Kanban):**
   * Map opportunities across visual columns (`discovery`, `proposal`, `negotiation`, `won`, `lost`).
   * Maintain strict state progression using `OpportunityService::updateStage(Opportunity $opp, string $stage, ?string $lossReason = null)`.
   * Enforce supplying a non-empty `loss_reason` string if stage changes to `lost`.
   * Terminate transition attempts out of final stages `won` and `lost`.
4. **Interaction Timeline (Polymorphic Activities):**
   * Keep track of calls, emails, meetings, and tasks using a single `CrmActivity` model.
   * Relate activities morphologically using the `trackable` relation.
   * Ensure that the trackable target belongs to the active tenant's context.

---

## Critical Rules

### 1. Multi-Tenant Query Scoping (P0)
Always utilize the `BelongsToTenant` trait on all CRM-related models: `Lead`, `Opportunity`, `CrmContact`, and `CrmActivity`. Bypassing or leaking cross-tenant records is a P0 security failure.

### 2. Transaction Boundaries (P0)
Multi-table inserts (e.g., qualifying a Lead into a Customer, Contact, and Opportunity) must reside inside a single transactional block (`DB::transaction`). A crash at any point must roll back all tables.

### 3. Encrypted PII Fields (P0)
Maintain database-level encryption for contact details (email and phone fields on the `CrmContact` model) to comply with data privacy policies.

### 4. Controller Resource Serialization (P0)
Return the `JsonResource` directly from the action methods. Never wrap a resource array inside standard JSON arrays (`response()->json(...)`), which strips out Eloquent's lazy-loading protection wrappers (`MissingValue`).

---

## Troubleshooting

- **`Foreign polymorphic injection error (403)`**: An activity creation was attempted with a `trackable_id` of a Lead or Opportunity that belongs to another tenant. The `ActivityService` blocks polymorphic references that do not resolve to models owned by the active tenant.
- **`Opportunity stage transition locked`**: An attempt was made to move a deal out of `won` or `lost`. Terminal stages are locked. To change, create a new opportunity or trigger a system-override supervisor service if permitted.
- **`Uncaught database truncation on lead conversion`**: The database transaction failed to roll back during a crash. Ensure `LeadService::qualifyToOpportunity` implements the transaction wrapper cleanly and re-throws errors so validation layers can report issues.
