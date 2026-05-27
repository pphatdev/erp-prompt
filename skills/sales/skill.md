---
name: sales-and-crm
description: Develop features for quotations, sale orders, customer accounts, and tenant provisioning along the O2C workflow.
---
# Sales (Order-to-Cash + Tenant Provisioning)

Use this skill when developing features for customer accounts, the quote → order → invoice → subscription funnel, and tenant provisioning. The Sales module owns the full Order-to-Cash (O2C) flow and is the entry point for Stancl tenant provisioning.

## Module surface (sidebar)

```
Sales
├── Customers
├── Quotations          — draft → won/lost
└── Sales Orders        — draft → confirm/cancel (triggers fulfillment + tenant provisioning)
```

**Invoices** and **Subscriptions** are now surfaced under the [`Finance`](../fms/skill.md) sidebar group even though the code still lives under `App\Tenants\Modules\Sales\*` (routes unchanged: `/sales/invoices`, `/sales/subscriptions`). See `rules/hybrid_sales_business_flow.md` § Module layout.

CRM-side prospect work (Leads, Opportunities, B2B/B2C Product Schedule, Appointments, polymorphic activities) lives in the [`crm`](../crm/skill.md) skill.

## Read first
- `skills/sales/rules.md` — implementation detail, target status enums, planned vs shipped behaviour, service contracts, API surface
- `skills/sales/flow.md` — Mermaid diagrams for the target O2C funnel + the current shipped call graph
- `skills/sales/testing.md` — P0-P2 test cases
- `rules/hybrid_sales_business_flow.md` — End-to-end CRM→Sales→Tenant lifecycle (source of truth for cross-module status rules)

## Workflows

### Target (Planned)
1. **CRM handoff** — Sales listens for `LeadQualified` (CRM event). The rep gets a "Create Quotation from Lead" task; the Quotation builder pre-fills lines from the `OpportunityProductSchedule` snapshot.
2. **Quotation lifecycle** — `draft` → `won` (terminal, converts Lead → Customer if new, auto-creates draft Sale Order) or `lost` (terminal, requires `loss_reason`).
3. **Sale Order lifecycle** — `draft` → `confirm` (generates Invoice + Subscription + StockMovement, **provisions tenant** if applicable) or `cancel` (terminal).
4. **Subscription lifecycle** — starts `active`. Renew / Upgrade / Downgrade / Cancel from the Customer Account dashboard. A daily job flips `active` → `expired` when `end_date < today`.
5. **Customer Account dashboard** — surfaces the tenant access URL, per-subscription countdown to `end_date`, and Renew / Upgrade / Downgrade / Cancel actions.

### Shipped (current behaviour)
1. **Lead to Customer** — `LeadService::qualifyToOpportunity` creates the Customer at qualification time.
2. **Tenant provisioning (immediate)** — `customer_type = tenant` triggers `TenantProvisioningService` synchronously from `CustomerController::store()`.
3. **Order to Cash (O2C)** — Quote (`new` → `confirmed`) → Order (`new` → `confirmed`, fulfills Invoice + Subscription + Stock) → Invoice `confirm` (posts AR + auto-activates Subscription, which fires `SubscriptionConfirmed` and provisions if not already done).

## Critical rules

### 1. Controller response pattern (P0)
Never return `response()->json(['data' => $resource->toArray(...)])` from any action method. Always return the `JsonResource` instance directly so the pipeline filters `MissingValue` sentinels. Use `.response()->setStatusCode(201)` when a 201 is needed.

### 2. Route ordering (P0)
`GET /customers/check-handle` must be declared **before** `Route::apiResource('customers', ...)`. Laravel matches resource routes greedily.

### 3. Central migrations (P0)
Three central migrations are required before any provisioning:
- `2024_01_01_000001_create_tenants_table.php` — `handle` is the primary key (string, no UUID)
- `2024_01_01_000002_create_domains_table.php` — FK references `tenants.handle`
- `2024_01_01_000003_use_handle_as_tenant_pk.php` — no-op on fresh installs

`CentralServiceProvider::boot()` auto-loads this path. Run `php artisan migrate` to apply.

### 4. Tenant primary key is `handle` (P0)
`App\Models\Central\Tenant` uses `handle` as PK. Never use `$centralTenant->id`. Use `$centralTenant->getKey()` or `$centralTenant->handle`. Physical DBs are named `tenant_{handle}`.

### 5. TenantProvisioningService is the single source of truth (P0)
All provisioning logic lives in `TenantProvisioningService::provisionForCustomer(Customer $customer, ?object $sub = null)`. Both the **Planned** trigger (Order `confirm`) and the **Shipped** triggers (customer create + subscription confirm) delegate here. Never duplicate provisioning logic in listeners or controllers.

### 6. Event dispatch outside transactions (P0)
Any event whose listener opens a different DB connection (central DB, customer DB) must be dispatched **after** the originating transaction commits. The shipped `SubscriptionService::confirm` follows this contract; the planned `QuotationService::win` and `OrderService::confirmOrder → TenantProvisioningService` calls must follow the same pattern.

### 7. CoA seed requirement
`InvoiceService::confirm` requires accounts `1200` (AR), `4000` (Revenue), `2150` (Tax Payable) to exist. `TenantDatabaseSeeder::seedChartOfAccounts()` seeds these idempotently.

### 8. Handle uniqueness
`tenant_handle` has a DB unique index + `Rule::unique(...)->whereNull('deleted_at')` validation. Frontend checks availability live via `GET /customers/check-handle` (450ms debounce). Edit page passes `ignore_id`.

### 9. Password hashing contract (P0)
`User` model declares `'password' => 'hashed'` in `casts()`. Always pass **plaintext** — the cast hashes exactly once. Double-hashing causes silent "Invalid credentials" on login.

### 10. Subscription product visibility
When a tenant customer logs in, `GET /api/v1/products` returns only the software products seeded into their tenant DB during provisioning. `TenantProvisioningService::seedSubscriptionProducts()` runs inside `$centralTenant->run()` and calls `Product::updateOrCreate(['sku' => ...], ['name' => ..., 'product_type' => 'software', 'is_active' => true])` per `SubscriptionItem` snapshot.

### 11. Status migration plan (Planned)
The Planned status enum changes (Quotation `draft`/`won`/`lost`, Order `draft`/`confirm`/`cancel`, Subscription `active`/`expired`/`cancelled`) are applied via a single per-tenant migration that remaps legacy values in-place. See `skills/sales/rules.md` § 2 for the migration body. Legacy `STATUS_NEW`/`STATUS_CONFIRMED` constants are removed in the same release.

## Troubleshooting
- **`[object Object]` in "Open Sales Order" URL**: Confirm/cancel action is using `->toArray()` — see rule #1.
- **`relation "domains" does not exist`**: Run `php artisan migrate --path=database/migrations/central --force` — see rule #3.
- **Subscription not provisioned after invoice confirm**: Verify `InvoiceService::activateLinkedSubscription()` exists and the `Invoice → order → subscription` chain is loaded. *(Shipped only — Planned flow has no Subscription confirm.)*
- **`SubscriptionConfirmed` listener fails with "row not found"**: Event dispatched inside a transaction. See rule #6.
- **Invoice confirm fails with "missing AR account (code: 1200)"**: CoA not seeded. Run `php artisan tenants:seed --class=TenantDatabaseSeeder`.
- **Handle availability check returns 404 model-not-found**: `check-handle` route registered after `apiResource`. Move it before — see rule #2.
- **`$centralTenant->id` is null after provisioning**: There is no `id` column. Use `$centralTenant->getKey()` — see rule #4.
- **Tenant not found / wrong database name after handle-PK migration**: Existing tenant databases were named `tenant_{uuid}`. After migrating to handle-PK, Stancl looks for `tenant_{handle}`. Re-provision the customer or rename the physical database.
- **Login fails "Invalid credentials" on provisioned tenant**: Either the user doesn't exist or has a double-hashed password from a pre-fix seeder run. Run `php artisan tenants:repair-credentials --tenant={handle}`.
- **Customer tenant's products page is empty**: The subscription had no items when provisioning ran. Re-trigger product seeding by calling `TenantProvisioningService::provisionForCustomer($customer, $sub)` again — idempotent path detects the existing tenant and only runs `seedSubscriptionProducts()`.
- **Customer still shows "Not provisioned" after subscription confirm** *(Shipped)*: `SubscriptionConfirmed` fired but `provision()` threw a unique-constraint error. Fixed via `firstOrCreate` + existence check on the domain row. `SubscriptionService::confirm()` re-dispatches when `provisioned_tenant_id` is still null — clicking "Confirm & provision" again is safe.
