# CRM Module Workflow Rules

> Sections marked **(Planned)** describe the target state per [`rules/hybrid_sales_business_flow.md`](../../rules/hybrid_sales_business_flow.md). Sections marked **(Shipped)** match current code.

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
| `product_schedule` *(planned)* | `crm.opportunities.read` | `crm.opportunities.write` | `crm.opportunities.write` | `crm.opportunities.export` |
| `contacts` | `crm.contacts.read` | `crm.contacts.write` | `crm.contacts.delete` | `crm.contacts.export` |
| `activities` | `crm.activities.read` | `crm.activities.write` | `crm.activities.delete` | `crm.activities.export` |

`product_schedule` re-uses `crm.opportunities.*` since the entity is a child of `Opportunity`.

---

## 2. Database Models & Schema (Tenant DB)

All models reside under `App\Models\Tenant` and MUST use `BelongsToTenant`, `Auditable`, and `SoftDeletes` traits.

| Concept | Model | Table | Key Attributes | Status |
|---|---|---|---|---|
| Lead | `App\Models\Tenant\Lead` | `leads` | `id` (UUID), `customer_id` (nullable), `title`, `estimated_value`, `status` (`new`\|`contacted`\|`qualified`\|`unqualified`), `source`, `tenant_id` | Shipped |
| Opportunity | `App\Models\Tenant\Opportunity` | `opportunities` | `id` (UUID), `lead_id` (nullable), `customer_id` (FK), `title`, `estimated_value`, `probability` (0-100), `stage` (`new`\|`contacted`\|`qualified`\|`proposal`\|`negotiation`\|`won`\|`lost`), `close_date`, `loss_reason` | Shipped |
| **B2B/B2C Product Schedule** | `App\Models\Tenant\OpportunityProductSchedule` | `opportunity_product_schedules` | `id` (UUID), `opportunity_id` (FK), `product_id` (FK), `variant_id` (FK nullable), `quantity`, `estimated_unit_price`, `cadence` (`one_time`\|`monthly`\|`annual`), `notes`, `tenant_id` | Shipped |
| **Appointment** *(calendar / timeline)* | `App\Models\Tenant\CrmAppointment` | `crm_appointments` | `id` (UUID), `subject`, `starts_at`, `ends_at`, `location`, `attendees` (jsonb), `opportunity_id` (FK nullable), `lead_id` (FK nullable), `status` (`scheduled`\|`completed`\|`cancelled`\|`no_show`), `tenant_id` | **Planned** |
| Account Contact | `App\Models\Tenant\CrmContact` | `crm_contacts` | `id` (UUID), `customer_id` (FK), `first_name`, `last_name`, `email`, `phone`, `job_title` | Shipped |
| Activity | `App\Models\Tenant\CrmActivity` | `crm_activities` | `id` (UUID), `activity_type` (`call`\|`email`\|`meeting`\|`note`\|`task`), `subject`, `description`, `due_date`, `status` (`pending`\|`completed`\|`cancelled`), `actor_id`, `trackable_type`, `trackable_id` | Shipped |

### Planned migration: `2024_01_01_0000XX_create_opportunity_product_schedules_table.php`

```php
Schema::create('opportunity_product_schedules', function (Blueprint $t) {
    $t->uuid('id')->primary();
    $t->uuid('opportunity_id');
    $t->uuid('product_id');
    $t->uuid('variant_id')->nullable();
    $t->decimal('quantity', 12, 2)->default(1);
    $t->decimal('estimated_unit_price', 15, 2)->default(0);
    $t->string('cadence')->default('one_time'); // one_time | monthly | annual
    $t->text('notes')->nullable();
    $t->string('tenant_id');
    $t->timestamps();
    $t->softDeletes();

    $t->foreign('opportunity_id')->references('id')->on('opportunities')->onDelete('cascade');
    $t->foreign('product_id')->references('id')->on('products');
    $t->foreign('variant_id')->references('id')->on('product_variants')->nullOnDelete();
    $t->index('tenant_id');
    $t->index('opportunity_id');
});
```

---

## 3. Services Architecture
All core CRM logic MUST reside in the Service Layer inside `App\Tenants\Modules\Crm\Services\`:

### `LeadService` (Shipped, slated for change)
* `createLead(array $data)`: Saves a raw prospect.
* `qualifyToOpportunity(Lead $lead, array $opportunityData)`:
  * Atomic transition wrapped in `DB::transaction`.
  * Status set to `qualified`.
  * If `customer_id` is null on Lead, creates an Account (`Customer` model) using Lead details. **(Shipped — Planned change: stop creating Customer here. Customer is created on Quotation `won` in Sales.)**
  * Creates an `Opportunity` linked to the Customer (or to the Lead alone, planned).
  * Captures the transition in audit logs.

### `OpportunityService` (Shipped)
* `createOpportunity(array $data)`: Provisions a pipeline entry.
* `updateStage(Opportunity $opp, string $stage, ?string $lossReason = null)`:
  * Transitions pipeline stages.
  * Throws exceptions if moving from terminal states (`won`/`lost`).
  * If `$stage === 'lost'`, validates that `loss_reason` is supplied.
  * If `$stage === 'won'`, fires **`OpportunityWon`** today; **(Planned: rename to `LeadQualified` and stop auto-creating Quotation. Sales surfaces a UI prompt instead.)**

### `OpportunityProductScheduleService` (Planned)
* `addLine(Opportunity $opp, array $data)` — append a product-of-interest line.
* `updateLine(OpportunityProductSchedule $line, array $data)` — adjust qty / variant / price / cadence.
* `removeLine(OpportunityProductSchedule $line)` — soft-delete.
* `snapshotToQuotation(Opportunity $opp): array` — returns line items ready to be passed to `QuotationService::create()`. Called by the Sales-side "Create Quotation from Lead" handler.

### `ActivityService` (Shipped)
* `logActivity(array $data)`: Attaches a polymorphic interaction to a target (Lead, Opportunity, or Customer). Validates trackable existence inside the active tenant.
* `completeActivity(CrmActivity $activity)`: Sets status to `completed`.

---

## 4. State Machines (FSM Enforcements)

### Lead Status Flow (Shipped — semantics unchanged in Planned):
```
[new]  ──►  [contacted]  ──►  [qualified] (handoff to Sales)
  │              │
  └──────────────┴───────►  [unqualified] (terminal)
```

### Opportunity Pipeline Stage Flow (Shipped):
```
[new] → [contacted] → [qualified] → [proposal] → [negotiation] → [won]   (handoff)
                                                                  ↘ [lost] (requires loss_reason)
```

### B2B Product Schedule lifecycle (Planned):
A Product Schedule line has no status of its own; it's a soft-deletable child of the parent `Opportunity`. Edits are allowed while the parent Opportunity is **not yet** in a terminal stage (`won`/`lost`). On `won`, lines are snapshotted to the resulting Quotation and the source schedule becomes read-only.

---

## 5. Cross-Module Handoff (Planned)

### `LeadQualified` event

Dispatched by `OpportunityService::updateStage` when stage becomes `won`. Payload: `Lead $lead, Opportunity $opportunity` (with `productSchedule` eager-loaded).

Sales listens via `App\Tenants\Modules\Sales\Listeners\HandleLeadQualified`:
- Creates a `tasks` / notification entry for the assigned rep: "Create Quotation from <Lead title>".
- Does **not** auto-create a Quotation. The rep opens the lead, reviews the schedule, and triggers `POST /quotations { from_opportunity_id: ..., items: [...] }`.

This replaces the shipped `OpportunityWon → CreateDraftQuotationOnOpportunityWon` listener which silently creates an empty draft Quotation today.

### `Customer` creation moves to Sales

Once the Quotation reaches `won`, Sales' `QuotationService::win()` (Planned) creates the Customer if none exists yet, links the Lead to it, then auto-creates the Sale Order. See [`skills/sales/rules.md`](../sales/rules.md).

---

## 6. Security & Isolation Controls (P0)
1. **Tenant Scope Guard:** Every query and insertion is automatically scoped using the `BelongsToTenant` trait. Tenant A MUST NEVER be able to query, update, or leak Tenant B's leads, opportunities, schedules, contacts, or activities.
2. **Polymorphic Target Validation:** When creating activities, the `trackable_type` and `trackable_id` MUST be validated against the active tenant context. The shipped `ActivityService::logActivity` enforces this.
3. **Sensitive Contact Encryption:** Encrypt `crm_contacts.phone` and `crm_contacts.email` at rest using Laravel's database-level encryption or Eloquent casts (`'encrypted'`) to comply with compliance policies.
4. **Product Schedule isolation (Planned):** `opportunity_product_schedules` references `products` and `product_variants` — the tenant scope on those parent tables already guarantees the FK cannot cross tenants.

---

## 7. Controller Response Pattern (P0)
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

## 8. Frontend Integration (Nuxt & PrimeVue)
* **Kanban Component:** PrimeVue `<Card>` + HTML5 drag-and-drop. Drag-to-`lost` opens a modal that requires `loss_reason`.
* **Pipeline Updates:** Debounce drag-and-drop updates. Trigger `PATCH /api/v1/opportunities/{id}/stage` on drop, toast on success.
* **Activity Timeline:** PrimeVue `<Timeline>` with icons mapped to activity types.
* **B2B Product Schedule UI (Planned):** Render an editable line-item table on the Opportunity detail page. Each row picks a Product, optional Variant, qty, estimated unit price, cadence. Lock the table once the parent stage is terminal.

---

## 9. Cross-Module Customer Integration

### Planned target
- **Lead → Customer conversion is deferred:** `LeadService::qualifyToOpportunity` no longer creates a `Customer`. The Customer is created at Quotation `won` in Sales (or pre-existing customers can be linked at any stage).
- **Opportunity may exist without a Customer:** add `customer_id` nullable on `opportunities` (already nullable on `leads`). UI shows "Prospect — no account yet" until Sales completes the win.

### Shipped (current behaviour)
1. **Account Entity Mapping:** The standard B2B `Customer` model acts as the primary B2B **Account**. Leads and Opportunities reference it via `customer_id`.
2. **Lead Conversion Bridge:** `LeadService::qualifyToOpportunity` checks if `customer_id` is provided; creates `Customer` + primary `CrmContact` if absent.
3. **Contact to Account Hierarchy:** `CrmContact` references `customers.id` via FK.
4. **Interactive Timeline Aggregation:** Customer detail page aggregates `crm_activities`, open `opportunities`, and Lead conversion history.
