# Sales Workflow Flow (O2C — Hybrid Sales)

## Canonical funnel

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
    F1 --> F2[ProvisionSubscriptionTenant listener:<br/>Central\Tenant + domain + tenant DB migrate/seed]
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
    PST->>CDB: INSERT tenants + domains
    PST->>DB: UPDATE customer.provisioned_tenant_id, subscription.status=active
    PST->>DB: migrate + seed new tenant DB
    IS-->>FE: 200 InvoiceResource (subscription auto-confirmed, domain live)
```

## Cancellation guards

- **Quotation**: cancellable at `new` or `confirmed` if no Sales Order exists. Once an Order exists, cancel the Order instead.
- **Order**: cancellable only while `new`. A confirmed Order has downstream artifacts — reverse individually.
- **Invoice**: cancellable only while `new`. Confirmed → posted to GL; reversal requires a credit note via FMS.
- **Subscription**: cancellable at any status; deprovisioning the customer tenant is a future concern.

## Atomicity boundaries

| Boundary | Scope |
|---|---|
| `OrderService::confirmOrder` | Single `DB::transaction` wrapping Invoice create + Subscription create + StockMovement create |
| `InvoiceService::confirm` | Inner `DB::transaction` for journal + invoice status update; subscription confirm runs after commit |
| `SubscriptionService::confirm` | Own `DB::transaction` for status update; `SubscriptionConfirmed` dispatched after commit |
| `ProvisionSubscriptionTenant::provision` | Seller-DB `DB::transaction` for `customer` + `subscription` updates; `$centralTenant->run()` is separate |

## Central migrations (required before provisioning)

The `domains` table lives in the central (landlord) database. It is created by:
- `database/migrations/central/2024_01_01_000001_create_tenants_table.php`
- `database/migrations/central/2024_01_01_000002_create_domains_table.php`

`CentralServiceProvider::boot()` calls `$this->loadMigrationsFrom(database_path('migrations/central'))` so `php artisan migrate` picks these up automatically. If the `domains` table is missing you will see:
```
SQLSTATE[42P01]: Undefined table: relation "domains" does not exist
```
Run `php artisan migrate --path=database/migrations/central --force` to recover.
