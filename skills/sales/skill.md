---
name: sales-and-crm
description: Develop features for lead management, customer relationships, quotations, orders, invoices, subscriptions, and tenant provisioning via the O2C workflow.
---
# Sales & CRM

Use this skill when developing features for lead management, customer relationships, order processing, invoicing, and tenant provisioning. The Sales module owns the full Order-to-Cash (O2C) workflow and is the entry point for Stancl tenant provisioning.

## Read first
- `skills/sales/rules.md` — implementation detail, service contracts, API surface, critical patterns
- `skills/sales/flow.md` — Mermaid diagrams for the O2C funnel and backend call graph
- `skills/sales/testing.md` — P0-P2 test cases

## Workflows
1. **Lead to Customer**: Convert prospects into verified customer profiles with billing and branding context. Tenant-type customers require a unique `tenant_handle`.
2. **Tenant Provisioning (immediate)**: Creating a customer with `customer_type = tenant` triggers `TenantProvisioningService` synchronously inside `CustomerController::store()`. The tenant subdomain is live and the customer admin user can log in before the response returns.
3. **Order to Cash (O2C)**: Quote → confirm → convert to Order → confirm (fulfills Invoice + Subscription + Stock) → Invoice confirm (posts AR + auto-activates Subscription). If the subscription customer is a tenant type and not yet provisioned, this path also provisions.
4. **User password management**: Admins can reset any user's password via `POST /users/{user}/reset-password` (requires `iam.users.write`).

## Critical rules

### 1. Controller response pattern (P0)
Never return `response()->json(['data' => $resource->toArray(...)])` from any action method. Always return the `JsonResource` instance directly so the pipeline filters `MissingValue` sentinels. Use `.response()->setStatusCode(201)` when a 201 is needed.

### 2. Route ordering (P0)
`GET /customers/check-handle` must be declared **before** `Route::apiResource('customers', ...)`. Laravel matches named resource routes greedily; registering after will treat `check-handle` as the `{customer}` show segment.

### 3. Central migrations (P0)
Three central migrations are required before any provisioning:
- `2024_01_01_000001_create_tenants_table.php` — `handle` is the primary key (string, no UUID)
- `2024_01_01_000002_create_domains_table.php` — FK references `tenants.handle`
- `2024_01_01_000003_use_handle_as_tenant_pk.php` — no-op on fresh installs; transitions existing DBs with a UUID `id` column

`CentralServiceProvider::boot()` auto-loads this path. Run `php artisan migrate` to apply.

### 4. Tenant primary key is `handle` (P0)
`App\Models\Central\Tenant` uses `handle` as its primary key (`$primaryKey = 'handle'`, `getTenantKeyName()` returns `'handle'`). The `id_generator` in `config/tenancy.php` is `null` — handle must always be set explicitly on create. Stancl derives the physical database name from the tenant key, so databases are named `tenant_{handle}` (e.g. `tenant_kean`).

Never use `$centralTenant->id` — there is no `id` column. Always use `$centralTenant->getKey()` or `$centralTenant->handle`.

### 5. TenantProvisioningService is the single source of truth (P0)
All provisioning logic lives in `App\Tenants\Modules\Sales\Services\TenantProvisioningService::provisionForCustomer(Customer $customer, ?object $sub = null)`. Both triggers (customer create and subscription confirm) delegate here. Never duplicate provisioning logic in listeners or controllers.

### 6. Invoice → Subscription activation chain
`InvoiceService::confirm` is the payment trigger. After the accounting transaction commits, it auto-confirms any linked `new` subscription, which dispatches `SubscriptionConfirmed` outside any open transaction. Provisioning failures are logged but do not roll back the committed invoice.

### 7. Event dispatch outside transactions
`SubscriptionService::confirm` wraps its status UPDATE in its own `DB::transaction` and dispatches `SubscriptionConfirmed` after it commits. Never dispatch this event inside a parent transaction — the provisioning service opens central DB connections and needs committed data.

### 8. CoA seed requirement
`InvoiceService::confirm` requires accounts `1200` (AR), `4000` (Revenue), `2150` (Tax Payable) to exist. `TenantDatabaseSeeder::seedChartOfAccounts()` seeds these idempotently. Run `php artisan tenants:seed --class=TenantDatabaseSeeder` on existing tenants if missing.

### 9. Handle uniqueness
`tenant_handle` has a DB unique index and a `Rule::unique(...)->whereNull('deleted_at')` validation rule. The frontend checks availability live via `GET /customers/check-handle`. The edit page passes `ignore_id` to exclude the current customer from the check.

### 10. Password hashing contract (P0)
`User` model declares `'password' => 'hashed'` in `casts()`. Always pass **plaintext** to any User create/update — the cast hashes exactly once. Never call `Hash::make()` before assignment. `TenantDatabaseSeeder` and `TenantProvisioningService` follow this contract. Double-hashing causes silent "Invalid credentials" on login.

### 11. Subscription product visibility
When a tenant customer logs in, `GET /api/v1/products` returns only the software products that were seeded into their tenant DB during provisioning. No frontend filtering is required — data isolation handles it naturally. `TenantProvisioningService::seedSubscriptionProducts()` runs inside `$centralTenant->run()` and calls `Product::updateOrCreate(['sku' => ...], [..., 'product_type' => 'software', 'is_active' => true])` for each `SubscriptionItem` snapshot. The idempotent path (already-provisioned customer receiving a new subscription) also seeds products by running the same method inside the existing tenant.

## Troubleshooting
- **`[object Object]` in "Open Sales Order" URL**: Confirm/cancel action is using `->toArray()` — see rule #1.
- **`relation "domains" does not exist`**: Run `php artisan migrate --path=database/migrations/central --force` — see rule #3.
- **Subscription not provisioned after invoice confirm**: Verify `InvoiceService::activateLinkedSubscription()` exists and that `Invoice → order → subscription` chain is loaded.
- **`SubscriptionConfirmed` listener fails with "row not found"**: Event dispatched inside a transaction. See rule #7.
- **Invoice confirm fails with "missing AR account (code: 1200)"**: CoA not seeded. Run `php artisan tenants:seed --class=TenantDatabaseSeeder`.
- **Handle availability check returns 404 model-not-found**: `check-handle` route registered after `apiResource`. Move it before — see rule #2.
- **`$centralTenant->id` is null after provisioning**: There is no `id` column. Use `$centralTenant->getKey()` — see rule #4.
- **Tenant not found / wrong database name after handle-PK migration**: Existing tenant databases were named `tenant_{uuid}`. After migrating to handle-PK, Stancl looks for `tenant_{handle}`. Re-provision the customer or rename the physical database.
- **Login fails "Invalid credentials" on provisioned tenant**: Either the user doesn't exist or has a double-hashed password from a pre-fix seeder run. Run `php artisan tenants:repair-credentials --tenant={handle}` to fix both.
- **Customer tenant's products page is empty**: The subscription had no items when provisioning ran, or the tenant was provisioned before rule #11 was applied (no software subscription existed at that time). Re-trigger product seeding by calling `TenantProvisioningService::provisionForCustomer($customer, $sub)` again — the idempotent path detects the tenant already exists and only runs `seedSubscriptionProducts()`. Alternatively confirm a software subscription via the invoice confirm flow to trigger the O2C path.
- **Customer still shows "Not provisioned" after subscription confirm**: The `SubscriptionConfirmed` event fired but `TenantProvisioningService::provision()` threw a unique-constraint error because a `CentralTenant` row for that handle already existed from a previous partial provisioning attempt. Fixed: `provision()` now uses `CentralTenant::firstOrCreate()` and an existence check on the domain row. Additionally, `SubscriptionService::confirm()` re-dispatches `SubscriptionConfirmed` when the subscription is already `confirmed` but `provisioned_tenant_id` is still null — so clicking "Confirm & provision" again is safe. `InvoiceService::activateLinkedSubscription()` does the same for the invoice confirm path.
