# Sales Workflow Flow (O2C — Hybrid Sales)

## Tenant provisioning — fast path (customer create)

```mermaid
graph TD
    A[POST /customers customer_type=tenant] --> B[Customer::create]
    B --> C{isTenantCustomer?}
    C -- no --> Z[Return CustomerResource]
    C -- yes --> D[TenantProvisioningService::provisionForCustomer]
    D --> E[CentralTenant::create handle=PK]
    E --> F[domains create subdomain]
    F --> G[DB::transaction: customer.provisioned_tenant_id + handle]
    G --> H[centralTenant->run: migrate + seed + create admin user]
    H --> I[customer.refresh]
    I --> Z
```

## Canonical O2C funnel

```mermaid
graph TD
    Start((Start)) --> A[Create Customer]
    A --> B[Create Quotation]

    B --> B1[Add line: product + variant + qty + price + due_date]
    B1 --> C{Quote status}

    C -- new --cancel--> End1((Closed Lost))
    C -- new --confirm--> CC[Quote confirmed]
    CC -- convert --> D[Sales Order from Quote]

    D --> D1{Order status}
    D1 -- new --cancel--> End2((Cancelled))
    D1 -- new --confirm--> Split{{Fulfillment orchestrator — one DB txn}}

    Split -- always --> E[Invoice 1:1]
    Split -- if any software line --> F[Subscription 1:1]
    Split -- if any hardware line --> G[StockMovement out per line]

    E --> E1[invoice.confirm posts AR journal:<br/>DR AR  CR Revenue + CR Tax]
    E1 --> E2[auto-confirms linked Subscription if new]
    E2 --> F1
    F --> F1[subscription.confirm dispatches<br/>SubscriptionConfirmed event]
    F1 --> F2[ProvisionSubscriptionTenant→TenantProvisioningService:<br/>idempotent — skips if already provisioned on customer create]
    F2 --> F3[Customer can access domain]
    G --> G1[Ship hardware — external process]

    E1 --> Final((Payment & Complete))
    F3 --> Final
    G1 --> Final
```

## Backend call graph

```mermaid
sequenceDiagram
    participant FE as Frontend (Nuxt)
    participant CC as CustomerController
    participant TPS as TenantProvisioningService
    participant QC as QuotationController
    participant QS as QuotationService
    participant OC as OrderController
    participant OS as OrderService
    participant OFS as OrderFulfillmentService
    participant IS as InvoiceService
    participant SS as SubscriptionService
    participant StockS as StockService
    participant AS as AccountingService
    participant PST as ProvisionSubscriptionTenant
    participant DB as Tenant DB
    participant CDB as Central DB

    Note over FE,CDB: ── Fast path: tenant provisioned on customer create ──
    FE->>CC: POST /customers {customer_type: tenant, tenant_handle, email, ...}
    CC->>DB: INSERT customers
    CC->>TPS: provisionForCustomer(customer)
    TPS->>CDB: INSERT tenants (handle=PK) + domains
    TPS->>DB: UPDATE customer.provisioned_tenant_id = handle
    TPS->>DB: migrate + seed + create admin user (customer email)
    CC-->>FE: 201 CustomerResource (provisionedSubdomain filled)

    Note over FE,CDB: ── O2C path (subscription billing) ──
    FE->>QC: POST /quotations {customer_id, items[]}
    QC->>QS: create()
    QS->>DB: INSERT quotations + quotation_items
    QC-->>FE: 201 QuotationResource

    FE->>QC: POST /quotations/{id}/confirm
    QC->>QS: confirm()
    QS->>DB: UPDATE status=confirmed
    QC-->>FE: 200 QuotationResource

    FE->>OC: POST /quotations/{id}/convert-to-order
    OC->>OS: createFromQuotation()
    OS->>DB: INSERT orders + order_items (snapshotted)
    OC-->>FE: 201 OrderResource (status=new)

    FE->>OC: POST /orders/{id}/confirm
    OC->>OS: confirmOrder() [BEGIN TXN]
    OS->>OFS: fulfill()
    OFS->>IS: createFromOrder()
    IS->>DB: INSERT invoices + invoice_items
    OFS->>SS: createFromOrder() (if software lines)
    SS->>DB: INSERT subscriptions + subscription_items
    OFS->>StockS: recordMovement(type=out) per hardware line
    StockS->>DB: INSERT stock_movements
    OS->>DB: UPDATE orders SET status=confirmed [COMMIT]
    OC-->>FE: 200 OrderResource

    FE->>IS: POST /invoices/{id}/confirm (via InvoiceController)
    Note over IS: BEGIN TXN
    IS->>AS: postEntry({DR AR, CR Revenue, CR Tax})
    AS->>DB: INSERT journal_entries + ledger_entries
    IS->>DB: UPDATE invoices SET status=confirmed
    Note over IS: COMMIT
    IS->>SS: confirm(subscription) [auto-activate]
    Note over SS: BEGIN TXN
    SS->>DB: UPDATE subscriptions SET status=confirmed
    Note over SS: COMMIT
    SS->>PST: dispatch SubscriptionConfirmed (outside txn)
    PST->>TPS: provisionForCustomer(customer, sub)
    Note over TPS: idempotent — returns immediately if already provisioned
    TPS->>CDB: INSERT tenants + domains (if not provisioned yet)
    TPS->>DB: UPDATE customer.provisioned_tenant_id, subscription.status=active
    IS-->>FE: 200 InvoiceResource
```

## Cancellation guards

- **Quotation**: cancellable at `new` or `confirmed` if no Sales Order exists. Once an Order exists, cancel the Order instead.
- **Order**: cancellable only while `new`. A confirmed Order has downstream artifacts — reverse individually.
- **Invoice**: cancellable only while `new`. Confirmed → posted to GL; reversal requires a credit note via FMS.
- **Subscription**: cancellable at any status; deprovisioning the customer tenant is a future concern.

## Atomicity boundaries

| Boundary | Scope |
|---|---|
| `CustomerController::store` (tenant type) | No wrapping transaction — customer saved first, provisioning runs after. Provisioning failure is logged; customer record is kept. |
| `OrderService::confirmOrder` | Single `DB::transaction` wrapping Invoice create + Subscription create + StockMovement create |
| `InvoiceService::confirm` | Inner `DB::transaction` for journal + invoice status update; subscription confirm runs after commit |
| `SubscriptionService::confirm` | Own `DB::transaction` for status update; `SubscriptionConfirmed` dispatched after commit |
| `TenantProvisioningService::provision` | Seller-DB `DB::transaction` for `customer` + `subscription` updates; `$centralTenant->run()` is separate |

## Central migrations (required before provisioning)

Three migrations in `database/migrations/central/` (auto-loaded by `CentralServiceProvider`):

| File | Purpose |
|---|---|
| `2024_01_01_000001_create_tenants_table.php` | Creates `tenants` table with `handle` as PK (no UUID `id`) |
| `2024_01_01_000002_create_domains_table.php` | Creates `domains` table, FK → `tenants.handle` |
| `2024_01_01_000003_use_handle_as_tenant_pk.php` | Transitions existing DBs with UUID `id` to handle-PK. No-op on fresh installs. `down()` is intentionally empty. |

Run `php artisan migrate` to apply. If the `domains` table is missing:
```
SQLSTATE[42P01]: Undefined table: relation "domains" does not exist
```
Run `php artisan migrate --path=database/migrations/central --force` to recover.
