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
2. **Order to Cash (O2C)**: Quote → confirm → convert to Order → confirm (fulfills Invoice + Subscription + Stock) → Invoice confirm (posts AR + auto-activates Subscription + provisions domain).
3. **Tenant Provisioning**: Confirming a subscription for a `tenant`-type customer triggers `ProvisionSubscriptionTenant` which creates the `Central\Tenant`, registers the subdomain (`{handle}.{APP_SYSTEM_DOMAIN}`), and migrates + seeds the new tenant database.

## Critical rules

### 1. Controller response pattern (P0)
Never return `response()->json(['data' => $resource->toArray(...)])` from any action method. Always return the `JsonResource` instance directly so the pipeline filters `MissingValue` sentinels. Use `.response()->setStatusCode(201)` when a 201 is needed.

### 2. Route ordering (P0)
`GET /customers/check-handle` must be declared **before** `Route::apiResource('customers', ...)`. Laravel matches named resource routes greedily; registering after will treat `check-handle` as the `{customer}` show segment.

### 3. Central migrations (P0)
The `domains` table (central DB) is required for provisioning. It is created by `database/migrations/central/2024_01_01_000002_create_domains_table.php`. `CentralServiceProvider` auto-loads this path. Run `php artisan migrate` to apply. Missing `domains` table → `relation "domains" does not exist` on first provisioning attempt.

### 4. Invoice → Subscription activation chain
`InvoiceService::confirm` is the payment trigger. After the accounting transaction commits, it auto-confirms any linked `new` subscription, which dispatches `SubscriptionConfirmed` outside any open transaction. Provisioning failures are logged but do not roll back the committed invoice.

### 5. Event dispatch outside transactions
`SubscriptionService::confirm` wraps its status UPDATE in its own `DB::transaction` and dispatches `SubscriptionConfirmed` after it commits. Never dispatch this event inside a parent transaction — the provisioning listener opens central DB connections and needs committed data.

### 6. CoA seed requirement
`InvoiceService::confirm` requires accounts `1200` (AR), `4000` (Revenue), `2150` (Tax Payable) to exist. `TenantDatabaseSeeder::seedChartOfAccounts()` seeds these idempotently. Run `php artisan tenants:seed --class=TenantDatabaseSeeder` on existing tenants if missing.

### 7. Handle uniqueness
`tenant_handle` has a DB unique index and a `Rule::unique(...)->whereNull('deleted_at')` validation rule. The frontend checks availability live via `GET /customers/check-handle`. The edit page passes `ignore_id` to exclude the current customer from the check.

## Troubleshooting
- **`[object Object]` in "Open Sales Order" URL**: Confirm/cancel action is using `->toArray()` — see rule #1 above.
- **`relation "domains" does not exist`**: Run `php artisan migrate --path=database/migrations/central --force` — see rule #3 above.
- **Subscription not provisioned after invoice confirm**: Verify `InvoiceService::activateLinkedSubscription()` exists and that `Invoice → order → subscription` chain is loaded.
- **`SubscriptionConfirmed` listener fails with "row not found"**: The event was dispatched inside a transaction that had not yet committed. See rule #5 above.
- **Invoice confirm fails with "missing AR account (code: 1200)"**: CoA not seeded. Run `php artisan tenants:seed --class=TenantDatabaseSeeder`.
- **Handle availability check route returns 404 model-not-found**: `check-handle` route registered after `apiResource`. Move it before — see rule #2.
