# Sales Workflow Flow (O2C — Hybrid Sales)

> Status legend: **Shipped** = matches current code today. **Planned** = target state per [`rules/hybrid_sales_business_flow.md`](../../rules/hybrid_sales_business_flow.md). Three planned shifts are documented here:
> 1. Quotation status renamed `new/confirmed/cancelled` → `draft/won/lost`. `won` performs Lead → Customer conversion.
> 2. Tenant provisioning trigger moves from `SubscriptionConfirmed` → `OrderConfirmed` (Sale Order `confirm`).
> 3. New Customer Account dashboard surface (access URL + subscription countdown + renew/upgrade/downgrade/cancel).

## Target end-to-end O2C funnel (Planned)

```mermaid
graph TD
    %% ── Inbound from CRM ──
    Hand[(CRM: LeadQualified event)] --> N[New Quotation<br/>status=draft<br/>seeded from B2B Product Schedule]

    N --> N1[Edit lines: products, variants, qty, price, due]
    N1 --> NS{Quotation status}

    %% Quotation transitions
    NS -- draft → lost --> QL[status=lost<br/>loss_reason required]
    QL --> End1((Closed Lost))

    NS -- draft → won --> QW[status=won<br/>1. Convert Lead → Customer if new<br/>2. Auto-create draft Sale Order]

    %% Sales Order transitions
    QW --> SO[Sale Order<br/>status=draft]
    SO --> SOS{Order status}
    SOS -- draft → cancel --> End2((Cancelled))
    SOS -- draft → confirm --> SOC[status=confirm<br/>Generate Invoice]

    %% Fulfillment orchestrator
    SOC --> FAN{Fulfillment orchestrator<br/>single DB transaction}
    FAN -- always --> INV[Invoice — AR posted to GL]
    FAN -- any software line --> SUB[Subscription — status=active<br/>start_date / end_date set]
    FAN -- any hardware line --> STK[StockMovement out<br/>+ ship hardware]

    %% Tenant provisioning hook
    SOC --> PR{Customer new<br/>AND order has software?}
    PR -- yes --> TP[TenantProvisioningService::<br/>provisionForCustomer]
    PR -- no --> Skip[Skip provisioning]
    TP --> ACC
    Skip --> ACC

    %% Customer Account dashboard
    ACC[Customer Account dashboard]
    ACC --> ACC1[Access URL:<br/>customer-handle.example.com]
    ACC --> ACC2[Tenant Product Schedule:<br/>one card per active Subscription]
    ACC2 --> ACC3[Countdown to end_date]
    ACC2 --> ACC4[Renew]
    ACC2 --> ACC5[Upgrade]
    ACC2 --> ACC6[Downgrade]
    ACC2 --> ACC7[Cancel]

    INV --> Final((Payment & Complete))
    SUB --> Final
    STK --> Final
```

## Target status rules

### Quotation (Planned)

| Status | Editable | Transitions out | Side effects |
|---|---|---|---|
| `draft` | Yes | → `won`, → `lost` | Editable line items, prices, discounts, validity dates. |
| `won` | No | (terminal) | Single `DB::transaction`: convert Lead → Customer if new; create primary `CrmContact` if needed; auto-create `draft` Sale Order from snapshot. |
| `lost` | No | (terminal) | Requires non-empty `loss_reason`. Closes the originating Lead as `unqualified` (if linked). |

### Sale Order (Planned)

| Status | Editable | Allowed actions | Side effects |
|---|---|---|---|
| `draft` | Yes | confirm, cancel | Editable header/lines. |
| `confirm` | No | cancel | `OrderFulfillmentService::fulfill()` runs in the same transaction: Invoice (always), Subscription (software lines), StockMovement out (hardware lines). **Provisions tenant** if customer is new and order has software. |
| `cancel` | No | (terminal) | Reversal of a confirmed order requires credit-note (FMS) and restock (Inventory). |

### Subscription (Planned)

| Status | Editable | Allowed actions | Side effects |
|---|---|---|---|
| `active` | Header only | Renew, Upgrade, Downgrade, Cancel | Countdown to `end_date`. Renew extends `end_date` and bills a new Invoice. Upgrade/Downgrade swaps variant + bills a delta. |
| `expired` | No | Renew (creates a new subscription) | Tenant kept; module visibility reduces per policy. Reached automatically by a daily job when `end_date < today` and not renewed. |
| `cancelled` | No | (terminal) | Tenant kept; deprovisioning is a separate policy decision. |

## Backend call graph — target (Planned)

```mermaid
sequenceDiagram
    participant FE as Frontend (Nuxt)
    participant EV as Event Bus
    participant SL as HandleLeadQualified
    participant QC as QuotationController
    participant QS as QuotationService
    participant OC as OrderController
    participant OS as OrderService
    participant OFS as OrderFulfillmentService
    participant IS as InvoiceService
    participant SS as SubscriptionService
    participant StockS as StockService
    participant AS as AccountingService
    participant TPS as TenantProvisioningService
    participant DB as Tenant DB
    participant CDB as Central DB

    Note over EV,SL: ── CRM handoff ──
    EV->>SL: LeadQualified(lead, opportunity)
    SL->>DB: insert sales_task "Create Quotation from {lead.title}"

    Note over FE,DB: ── Quotation lifecycle ──
    FE->>QC: POST /quotations {from_opportunity_id, items[]}
    QC->>QS: create()  [snapshot schedule lines]
    QS->>DB: INSERT quotations (status=draft) + quotation_items
    QC-->>FE: 201 QuotationResource

    FE->>QC: POST /quotations/{id}/win
    QC->>QS: win()  [BEGIN TXN]
    alt lead has no Customer
        QS->>DB: INSERT customers
        QS->>DB: INSERT crm_contacts (primary)
        QS->>DB: UPDATE leads SET customer_id = ...
    end
    QS->>DB: UPDATE quotations SET status='won'
    QS->>OS: createFromQuotation()
    OS->>DB: INSERT orders (status=draft) + order_items
    Note over QS: COMMIT
    QC-->>FE: 200 QuotationResource (with order loaded)

    Note over FE,DB: ── Sale Order confirm = fulfillment + provisioning ──
    FE->>OC: POST /orders/{id}/confirm
    OC->>OS: confirmOrder()  [BEGIN TXN]
    OS->>OFS: fulfill()
    OFS->>IS: createFromOrder()
    IS->>DB: INSERT invoices + invoice_items
    IS->>AS: postEntry({DR AR, CR Revenue, CR Tax})
    AS->>DB: INSERT journal_entries + ledger_entries
    OFS->>SS: createFromOrder() (if software lines)
    SS->>DB: INSERT subscriptions (status=active) + subscription_items
    OFS->>StockS: recordMovement(out) per hardware line
    StockS->>DB: INSERT stock_movements
    OS->>DB: UPDATE orders SET status='confirm'
    Note over OS: COMMIT
    OS->>TPS: provisionForCustomer(customer, subscription)  [outside txn]
    TPS->>CDB: INSERT tenants + domains (if customer.is_new)
    TPS->>DB: customer.provisioned_tenant_id, subscription.provisioned_tenant_id
    TPS->>CDB: $centralTenant->run(migrate + seed + admin user + module entitlement)
    OC-->>FE: 200 OrderResource (with invoice + subscription + provisioned subdomain)
```

## Customer Account dashboard (Planned)

After provisioning, Sales surfaces a customer account page at `pages/sales/customers/[id]/account.vue`. Sections:

| Block | Data source | Actions |
|---|---|---|
| Access URL | `customer.tenant_handle` + `platform.system_domain` | Copy-to-clipboard, "Open in new tab" |
| Tenant Product Schedule | Customer's active `subscriptions` with eager-loaded `items` | One card per Subscription |
| Subscription countdown | `subscription.end_date - now()` | Live countdown badge (days remaining); colors: green > 30d, amber 7–30d, red < 7d |
| Renew | `POST /subscriptions/{id}/renew { cycle? }` | Extends `end_date`; bills a new Invoice |
| Upgrade | `POST /subscriptions/{id}/change-plan { product_id, variant_id, action:'upgrade' }` | Swaps variant; bills delta on next cycle or immediate |
| Downgrade | `POST /subscriptions/{id}/change-plan { product_id, variant_id, action:'downgrade' }` | Swaps variant; applies credit on next invoice |
| Cancel | `POST /subscriptions/{id}/cancel { reason }` | Sets status=`cancelled`; tenant kept per retention policy |

Buttons are disabled when the subscription is `expired` or `cancelled` (`Renew` remains enabled on `expired`).

## Atomicity boundaries (Planned)

| Boundary | Scope |
|---|---|
| `QuotationService::win` | Single `DB::transaction` for Customer-create-if-needed + CrmContact-create + Lead.customer_id update + Quotation status update + draft Order creation |
| `OrderService::confirmOrder` | Single `DB::transaction` wrapping Invoice create + Subscription create + StockMovement create + AR journal posting + order.status update |
| `TenantProvisioningService::provisionForCustomer` | Runs **after** `confirmOrder` commits. Catches exceptions → logs → surfaces on UI as "Provisioning pending — retry". Order stays `confirm`; idempotent retry available. |
| `SubscriptionService::renew` / `changePlan` / `cancel` | Each wraps its own `DB::transaction`. Renew + changePlan emit a new `Invoice` inside the same transaction. |

## Cancellation guards (Planned)

- **Quotation `draft`**: cancel becomes `lost` (loss_reason required). No `cancelled` state any more.
- **Sale Order `confirm`**: cancel sets status=`cancel`. Downstream Invoice/Subscription must be reversed individually (credit note for invoice, cancel for subscription). Hardware stock movement is not auto-reversed — operator issues a return.
- **Subscription `active`**: cancel allowed at any time. Renew/Upgrade/Downgrade pause if a cancellation is in flight.

## Central migrations (still required — unchanged)

The three central migrations for tenant provisioning remain unchanged:

| File | Purpose |
|---|---|
| `2024_01_01_000001_create_tenants_table.php` | `tenants` with `handle` as PK |
| `2024_01_01_000002_create_domains_table.php` | `domains` FK → `tenants.handle` |
| `2024_01_01_000003_use_handle_as_tenant_pk.php` | Transitions UUID-PK installs to handle-PK |

## Current shipped flow (for reference)

The implementation as of this writing differs from the target above. See [`skills/sales/rules.md` § "Shipped — current behaviour"](./rules.md) for the still-active call graph: Customer-create-on-`POST /customers`, auto-Quotation-on-`OpportunityWon`, provisioning on `SubscriptionConfirmed`.
