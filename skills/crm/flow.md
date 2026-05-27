# CRM Workflow Flows

> Status legend: **Shipped** matches current code. **Planned** describes the target state once the refactor in [`rules/hybrid_sales_business_flow.md`](../../rules/hybrid_sales_business_flow.md) lands.
>
> Key planned changes documented here: (1) `OpportunityProductSchedule` (B2B Product Schedule) — new entity attached to an Opportunity; (2) Customer creation is **deferred** out of `LeadService::qualifyToOpportunity` and moved to the Sales-side Quotation `won` transition.

## Full prospect-to-handoff pipeline (Planned)

```mermaid
graph TD
    Lead[New Lead<br/>status=new] --> Touch[Outreach activities<br/>calls / emails / meetings]
    Touch --> Opp[Lead Opportunity<br/>stage=discovery]

    Opp --> Sched[B2B Product Schedule<br/>prospect's products-of-interest:<br/>software + hardware lines]
    Sched --> Stage[Stage progression:<br/>discovery → proposal → negotiation]

    Stage -- terminal: lost --> Lost((stage=lost<br/>loss_reason required))
    Stage -- terminal: won --> Qual[Lead Qualified<br/>lead.status=qualified<br/>opportunity.stage=won]

    Qual -. handoff .-> Sales[(Sales Module<br/>Create Quotation<br/>pre-filled from Product Schedule)]
```

## Lead lifecycle (Planned)

```mermaid
graph TD
    A[POST /leads — capture raw lead] --> B[status=new]
    B --> B1[Log activities: call/email/meeting]
    B1 --> C[POST /opportunities — promote to Opportunity]
    C --> D[Build B2B Product Schedule<br/>POST /opportunities/{id}/product-schedule]
    D --> E[PATCH /opportunities/{id}/stage<br/>discovery → proposal → negotiation]
    E -- won --> F[Lead Qualified<br/>handoff event LeadQualified dispatched]
    E -- lost --> G[Capture loss_reason<br/>close lead as unqualified]
    F -. handoff .-> H[(Sales: Create Quotation)]
```

Notes:
- `LeadService::qualifyToOpportunity` (Shipped) currently creates a Customer + CrmContact + Opportunity in one transaction. Planned change: **drop the Customer creation** from this step. Customer creation moves to Quotation `won` (Sales).
- `LeadQualified` event is the cross-module handoff signal. Sales listens for it to surface "Create Quotation from Lead" in the rep's task list.

## B2B Product Schedule (Planned)

```mermaid
graph LR
    Opp[Opportunity] -- has many --> Sched[OpportunityProductSchedule line]
    Sched -- references --> Product[Product]
    Sched -- optional --> Variant[ProductVariant]
```

Schema sketch (`opportunity_product_schedules` table):

| Column | Type | Notes |
|---|---|---|
| `id` | UUID | PK |
| `opportunity_id` | UUID | FK → opportunities.id, indexed |
| `product_id` | UUID | FK → products.id |
| `variant_id` | UUID nullable | FK → product_variants.id |
| `quantity` | decimal(12,2) | Estimated headcount / units |
| `estimated_unit_price` | decimal(15,2) | Pre-negotiation price |
| `cadence` | string | `one_time` \| `monthly` \| `annual` |
| `notes` | text nullable | Why this product is on the schedule |
| `tenant_id` | string | BelongsToTenant scope |
| timestamps + soft deletes | | |

API surface (planned):

| Method | Path | Action |
|---|---|---|
| GET | `/opportunities/{opportunity}/product-schedule` | List the schedule |
| POST | `/opportunities/{opportunity}/product-schedule` | Append a line |
| PATCH | `/opportunities/{opportunity}/product-schedule/{line}` | Update qty / variant / price / cadence |
| DELETE | `/opportunities/{opportunity}/product-schedule/{line}` | Remove a line |

On `Opportunity → won`, the schedule lines are snapshotted as default Quotation items on the Sales side.

## Backend call graph — qualification handoff (Planned)

```mermaid
sequenceDiagram
    participant FE as Frontend (Nuxt CRM)
    participant OC as OpportunityController
    participant OS as OpportunityService
    participant DB as Tenant DB
    participant EV as Event Bus
    participant SL as Sales Listener

    Note over FE,DB: ── Opportunity Won (qualified) ──
    FE->>OC: PATCH /opportunities/{id}/stage {stage:'won'}
    OC->>OS: updateStage() [BEGIN TXN]
    OS->>DB: UPDATE opportunities SET stage='won'
    OS->>DB: UPDATE leads SET status='qualified' (linked lead)
    OS->>DB: INSERT audit_logs
    Note over OS: COMMIT
    OS->>EV: dispatch LeadQualified(lead, opportunity)
    EV->>SL: Sales: surface "Create Quotation from Lead" task
    OC-->>FE: 200 OpportunityResource (with schedule loaded)
```

The planned `LeadQualified` event **replaces** today's `OpportunityWon → CreateDraftQuotationOnOpportunityWon` auto-creation. Reps explicitly create the Quotation so they can edit the product schedule snapshot before sending.

## Lead lifecycle — current (Shipped)

```mermaid
graph TD
    A[POST /leads qualification] --> B[LeadService::qualifyToOpportunity]
    B --> C{Has Customer Associated?}
    C -- no --> D[Create Customer Account]
    D --> E[Create Contact person linked to Customer]
    E --> F[Create Opportunity linked to Customer]
    C -- yes --> F
    F --> G[Update Lead status = qualified]
    G --> H[Log Auditable Action]
    H --> I[Return LeadResource with Opportunity details]
```

This shipped flow creates the Customer at qualification. The planned flow defers it to Quotation `won`.

## Opportunity pipeline — current (Shipped)

```mermaid
graph TD
    Start((Start Opportunity)) --> Disc[Stage: discovery]
    Disc --> Prop[Stage: proposal]
    Prop --> Nego[Stage: negotiation]

    Disc -- lose --> L1((Lost))
    Prop -- lose --> L2((Lost))
    Nego -- lose --> L3((Lost))

    Nego -- win --> W1[Stage: won]
    W1 --> Q[Generate Draft Sales Quotation]
    Q --> Finish((Sales Order Pipeline))

    L1 -. requires .-> R[Capture loss_reason]
    L2 -. requires .-> R
    L3 -. requires .-> R
```

Planned change: drop the auto Quotation generation (the box labeled `Generate Draft Sales Quotation`). Replace with `LeadQualified` event consumed in Sales as a UI prompt.
