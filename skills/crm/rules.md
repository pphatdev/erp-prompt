# CRM Module Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `crm`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `leads` | `crm.leads.read` | `crm.leads.write` | `crm.leads.delete` | `crm.leads.export` |
| `opportunities` | `crm.opportunities.read` | `crm.opportunities.write` | `crm.opportunities.delete` | `crm.opportunities.export` |
| `contacts` | `crm.contacts.read` | `crm.contacts.write` | `crm.contacts.delete` | `crm.contacts.export` |
| `activities` | `crm.activities.read` | `crm.activities.write` | `crm.activities.delete` | `crm.activities.export` |

---

## 2. Database Models & Schema (Tenant DB)

All models reside under `App\Models\Tenant` and MUST use `BelongsToTenant`, `Auditable`, and `SoftDeletes` traits.

| Concept | Model | Table | Key Attributes | Notes |
|---|---|---|---|---|
| Lead | `App\Models\Tenant\Lead` | `leads` | `id` (UUID), `customer_id` (nullable), `title`, `estimated_value`, `status` (`new`\|`contacted`\|`qualified`\|`unqualified`), `source`, `tenant_id` | Initial raw contact before conversion. |
| Opportunity | `App\Models\Tenant\Opportunity` | `opportunities` | `id` (UUID), `lead_id` (nullable), `customer_id` (FK), `title`, `estimated_value`, `probability` (0-100), `stage` (`discovery`\|`proposal`\|`negotiation`\|`won`\|`lost`), `projected_close_date`, `loss_reason` | Qualified sales cycle pipeline tracker. |
| Account Contact | `App\Models\Tenant\CrmContact` | `crm_contacts` | `id` (UUID), `customer_id` (FK), `first_name`, `last_name`, `email`, `phone`, `job_title` | People linked to corporate customers. |
| Activity | `App\Models\Tenant\CrmActivity` | `crm_activities` | `id` (UUID), `activity_type` (`call`\|`email`\|`meeting`\|`task`), `subject`, `description`, `due_date`, `status` (`pending`\|`completed`), `actor_id` (FK to users), `trackable_type`, `trackable_id` | Polymorphic relation logs interaction timeline. |

---

## 3. Services Architecture
All core CRM logic MUST reside in the Service Layer inside `App\Tenants\Modules\Crm\Services\`:

### `LeadService`
* `createLead(array $data)`: Saves a raw prospect.
* `qualifyToOpportunity(Lead $lead, array $opportunityData)`:
  * Atomic transition wrapped in `DB::transaction`.
  * Status set to `qualified`.
  * If `customer_id` is null on Lead, creates an Account (`Customer` model) using Lead details.
  * Creates an `Opportunity` linked to the Customer.
  * Captures the transition in audit logs.

### `OpportunityService`
* `createOpportunity(array $data)`: Provisions a pipeline entry.
* `updateStage(Opportunity $opp, string $stage, ?string $lossReason = null)`:
  * Transitions pipeline stages.
  * Throws exceptions if moving from terminal states (`won`/`lost`).
  * If `$stage === 'lost'`, validates that `loss_reason` is supplied.
  * If `$stage === 'won'`, fires `OpportunityWon` event (allowingSales to auto-generate a draft Quotation).

### `ActivityService`
* `logActivity(array $data)`: Attaches a polymorphic interaction to a target (Lead, Opportunity, or Customer).
* `completeActivity(CrmActivity $activity)`: Sets status to `completed`, records completed timestamp.

---

## 4. State Machines (FSM Enforcements)

### Lead Status Flow:
```
[new]  ──►  [contacted]  ──►  [qualified] (converts to Opportunity)
  │              │
  └──────────────┴───────►  [unqualified] (terminal)
```

### Opportunity Pipeline Stage Flow:
```
[discovery] ──► [proposal] ──► [negotiation] ──► [won] (converts to Quotation)
     │               │               │
     └───────────────┴───────────────┴─────────► [lost] (requires loss_reason)
```

---

## 5. Security & Isolation Controls (P0)
1. **Tenant Scope Guard:** Every query and insertion is automatically scoped using the `BelongsToTenant` trait. Tenant A MUST NEVER be able to query, update, or leak Tenant B's leads, opportunities, or contacts.
2. **Polymorphic Target Validation:** When creating activities, the `trackable_type` and `trackable_id` MUST be validated against the active tenant context. Bypassing tenant scoping via polymorphic injection is blocked by verifying that the target model belongs to the tenant.
3. **Sensitive Contact Encryption:** Encrypt `crm_contacts.phone` and `crm_contacts.email` at rest using Laravel's database-level encryption or Eloquent casts (`'encrypted'`) to comply with compliance policies.

---

## 6. Controller Response Pattern (P0)
Always return the `JsonResource` direct instance from Controller action methods. Bypassing the Laravel resource serialization pipeline results in leaked `MissingValue` wrappers.

```php
// ❌ Incorrect
return response()->json([
    'data' => (new LeadResource($lead->load('customer')))->toArray(request())
]);

// ✅ Correct
return new LeadResource($lead->load('customer'));
```

---

## 7. Frontend Integration (Nuxt & PrimeVue)
* **Kanban Component:** Use PrimeVue's `<Card>` combined with HTML5 drag-and-drop or Tailwind transition groups.
* **Pipeline Updates:** Debounce drag-and-drop updates. Trigger `PATCH /api/v1/opportunities/{id}/stage` upon drop, showing a toast notification.
* **Activity Timeline:** Render using PrimeVue `<Timeline>` with dynamic icons mapped to activity types (phone for calls, envelope for emails, calendar for meetings, check-square for tasks).

---

## 8. Cross-Module Customer Integration
The CRM module explicitly references and integrates with the primary standard **Customer** module:
1. **Account Entity Mapping:** The standard B2B `Customer` model (`App\Models\Tenant\Customer` from the Sales/Customer module) acts as the primary B2B **Account** in CRM. Leads and Opportunities explicitly reference this model via the `customer_id` column.
2. **Lead Conversion Bridge:** When a raw `Lead` is qualified via `LeadService::qualifyToOpportunity`, the conversion engine checks if the `customer_id` is provided.
   - If present, it binds the new `Opportunity` directly to that existing `Customer` account.
   - If absent, the engine first creates a new `Customer` profile (saving B2B account properties like company name, address, tax info) and then binds the new `Opportunity` and `CrmContact` to it.
3. **Contact to Account Hierarchy:** `CrmContact` models represent B2B individuals (e.g., procurement managers) and must be linked to a parent B2B `Customer` (Account) record via a strict foreign key constraint (`customer_id` references `customers.id`).
4. **Interactive Timeline Aggregation:** The Customer detail page in the frontend/backend aggregates and displays all related CRM polymorphic Activities (`crm_activities`), open Opportunities (`opportunities`), and conversion history from raw Leads.
