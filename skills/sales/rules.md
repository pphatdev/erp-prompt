# Sales Module Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `sales`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `crm` | `sales.crm.read` | `sales.crm.write` | `sales.crm.delete` | `sales.crm.export` |
| `leads` | `sales.leads.read` | `sales.leads.write` | `sales.leads.delete` | `sales.leads.export` |
| `quotations` | `sales.crm.read` | `sales.crm.write` | `sales.crm.delete` | `sales.crm.export` |
| `orders` | `sales.orders.read` | `sales.orders.write` | `sales.orders.delete` | `sales.orders.export` |
| `invoices` | `sales.orders.read` | `sales.orders.write` | `sales.orders.delete` | `sales.orders.export` |
| `subscriptions` | `sales.orders.read` | `sales.orders.write` | `sales.orders.delete` | `sales.orders.export` |

> Note: Quotation/Invoice/Subscription Form Requests currently delegate to `sales.crm.write` / `sales.orders.write`. Split into dedicated permission slugs (`sales.quotations.write`, `sales.invoices.confirm`, etc.) if separation of duties is needed.

---

## 2. Hybrid Sales — Implementation (Shipped)

### Models & tables (tenant DB)

| Concept | Model | Table | Notes |
|---|---|---|---|
| Catalogue product | `App\Models\Tenant\Product` | `products` | Adds `product_type` (`hardware`\|`software`), `is_active`, `description_long`. |
| Variant axes | `App\Models\Tenant\ProductVariant` | `product_variants` | `attributes` jsonb holds `{color, size, plan_tier, term, seat_count, …}` — no schema churn per new axis. |
| Quote | `App\Models\Tenant\Quotation` + `QuotationItem` | `quotations`, `quotation_items` | Status: `new` → `confirmed` → (Order); `new` → `cancelled` (terminal). |
| Sales Order | `App\Models\Tenant\Order` + `OrderItem` | `orders`, `order_items` | 1:1 with Quotation via `orders.quotation_id` (unique). Items snapshot `product_type`. |
| Invoice (AR) | `App\Models\Tenant\Invoice` + `InvoiceItem` | `invoices`, `invoice_items` | 1:1 with Order. `journal_entry_id` links to GL. |
| Subscription | `App\Models\Tenant\Subscription` + `SubscriptionItem` | `subscriptions`, `subscription_items` | 1:1 with Order. Wraps only software-typed lines. `provisioned_tenant_id` set by provisioning listener. |
| Customer | `App\Models\Tenant\Customer` | `customers` | `customer_type` ∈ `individual`\|`business`\|`tenant`. `tenant_handle` must be unique (DB constraint + `Rule::unique` in validation). |

### Services

All under `App\Tenants\Modules\Sales\Services\`:
- `QuotationService` — create, addItem, confirm, cancel. Locks edits once status leaves `new`.
- `OrderService` — `createOrder` (ad-hoc), `createFromQuotation`, `confirmOrder` (triggers fulfillment), `cancelOrder`.
- `InvoiceService` — `createFromOrder` (auto by orchestrator), `confirm` (posts AR journal **then auto-confirms linked subscription**), `cancel`.
- `SubscriptionService` — `createFromOrder` (only when software lines present), `confirm` (commits row in own transaction, then dispatches `SubscriptionConfirmed`), `cancel`.
- `Fulfillment\OrderFulfillmentService` — orchestrator called by `OrderService::confirmOrder`. Always creates Invoice; software lines → Subscription; hardware lines → `out` StockMovement, all in the same DB transaction.

### Status flow (enforced in services)

```
Quotation:     new  --confirm-->  confirmed  --convert-->  Sales Order
               new  --cancel-->   cancelled  (terminal)
               confirmed  --cancel-->  cancelled  (only if no Order yet)

Sales Order:   new  --confirm-->  confirmed  → triggers Fulfillment orchestrator
               new  --cancel-->   cancelled
               confirmed  --cancel-->  ❌ rejected — must reverse downstream first

Invoice:       new  --confirm-->  confirmed  (AR posted to GL)
                                             → auto-confirms linked Subscription (if new)
               confirmed  --paid-->  paid
               new  --cancel-->   cancelled
               confirmed  --cancel-->  ❌ rejected — issue credit note via FMS first

Subscription:  new  --confirm-->  confirmed  (dispatches SubscriptionConfirmed)
               confirmed  --provisioned-->  active   (set by listener after tenant DB + domain ready)
               any  --cancel-->   cancelled
```

### Invoice → Subscription auto-activation (P1)

`InvoiceService::confirm()` is the payment trigger. After the accounting transaction commits it calls `activateLinkedSubscription()`, which:
1. Loads `invoice → order → subscription`.
2. If the subscription is still `new`, calls `SubscriptionService::confirm()`.
3. `confirm()` commits the status update in its own transaction, then dispatches `SubscriptionConfirmed` **outside** any open transaction so the listener sees committed data.
4. `ProvisionSubscriptionTenant` listener creates the `Central\Tenant`, registers the subdomain, and migrates/seeds the tenant DB.

Provisioning failures are caught, logged, and do **not** roll back the committed invoice. The subscription can be manually re-confirmed from the UI if provisioning fails.

### Atomicity (P0)

`OrderService::confirmOrder` runs inside `DB::transaction`. The orchestrator's downstream calls (`InvoiceService::createFromOrder`, `SubscriptionService::createFromOrder`, `StockService::recordMovement`) all run in the SAME transaction. Any failure — insufficient stock, missing CoA account, etc. — rolls the order back to `new`. Partial fulfillment is impossible.

`SubscriptionService::confirm` wraps its own `UPDATE` in a `DB::transaction` before dispatching the event so the provisioning listener always sees committed state.

### Tenant provisioning (ProvisionSubscriptionTenant listener)

Registered: `Event::listen(SubscriptionConfirmed::class, ProvisionSubscriptionTenant::class)` in `TenantServiceProvider`.

Flow when triggered:
1. Skip if `customer_type !== 'tenant'` or customer is already provisioned (idempotent).
2. Create `App\Models\Central\Tenant` (central DB connection) with `handle` + `name`.
3. Call `$centralTenant->domains()->create(['domain' => "{$handle}.{$systemDomain}"])`. Requires `domains` table to exist — see **Central migrations** below.
4. Commit seller's tenant DB: update `Customer.provisioned_tenant_id / provisioned_at` and `Subscription.provisioned_tenant_id / status=active`.
5. Call `$centralTenant->run(fn)` to migrate + seed the new tenant's own database.
6. Seed branding settings (`branding.primary_color`, `branding.logo_url`) from customer record.

System domain is read from `config('platform.system_domain')` → `.env` key `APP_SYSTEM_DOMAIN`.

### Customer → Tenant handle rules

- `tenant_handle` field is **required** when `customer_type = 'tenant'`.
- Format: `^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])?$` (lowercase, digits, hyphens, no leading/trailing hyphen, max 60 chars).
- Uniqueness enforced at both DB level (`customers_tenant_handle_unique` index) and validation level (`Rule::unique(...)->whereNull('deleted_at')`).
- The frontend debounce-checks availability via `GET /customers/check-handle?handle=&[ignore_id=]` (450ms debounce) and shows a live status indicator.

### Account resolution for AR journal

`InvoiceService::confirm` posts:
```
DR  Accounts Receivable    total_amount
  CR  Sales Revenue        subtotal
  CR  Sales Tax Payable    tax_amount   (only when > 0)
```

Account codes resolve via `SettingService`:
- `fms.ar_account_code`      — default `1200`
- `fms.revenue_account_code` — default `4000`
- `fms.tax_account_code`     — default `2150`

The CoA must contain a row matching each code or the confirm throws `DomainException`. **`TenantDatabaseSeeder::seedChartOfAccounts()`** seeds all three (plus 16 others) idempotently using `Account::updateOrCreate(['code' => ...])`.

### Warehouse resolution for hardware

`OrderFulfillmentService::resolveDefaultWarehouse()` reads:
1. `Setting('inventory.default_warehouse_code')`.
2. The sole Warehouse row when only one exists.
3. Otherwise throws — tenant must configure.

### API surface

All routes are tenant-scoped under `/api/v1`, gated by `auth:api`:

| Method | Path | Action |
|---|---|---|
| GET | `/customers/check-handle?handle=&[ignore_id=]` | Real-time handle availability check |
| GET/POST/PUT/DELETE | `/customers` | Full CRUD (apiResource) |
| POST | `/quotations` | Create new draft quote with line items |
| POST | `/quotations/{quotation}/items` | Append a line to a `new` quote |
| POST | `/quotations/{quotation}/confirm` | Lock quote, eligible for SO conversion |
| POST | `/quotations/{quotation}/cancel` | Terminal cancel |
| POST | `/quotations/{quotation}/convert-to-order` | Snapshot confirmed quote into a Sales Order |
| POST | `/orders/{order}/confirm` | Confirm + auto-fulfill (Invoice + Subscription + Stock) |
| POST | `/orders/{order}/cancel` | Cancel only while `new` |
| POST | `/invoices/{invoice}/confirm` | Post AR journal → auto-confirm subscription → provision domain |
| POST | `/invoices/{invoice}/cancel` | Allowed only while `new` |
| POST | `/subscriptions/{subscription}/confirm` | Fire `SubscriptionConfirmed` (manual override) |
| POST | `/subscriptions/{subscription}/cancel` | At any status |

> **Route ordering (P0):** `GET /customers/check-handle` must be registered **before** `Route::apiResource('customers', ...)`. If reversed, Laravel's router matches `check-handle` as the `show({id})` segment.

### Controller response pattern (P0)

**Never** use `response()->json(['data' => (new XxxResource(...))->toArray(...)])` in action methods. This bypasses Laravel's resource serialization pipeline — `whenLoaded()` sentinel objects (`MissingValue`) survive `toArray()` and become `{}` in JSON, which JavaScript stringifies as `[object Object]`.

**Always** `return new XxxResource(...)` directly (or `.response()->setStatusCode(201)` for 201 responses). The pipeline filters `MissingValue` automatically.

```php
// ❌ Wrong — MissingValue leaks as {} in JSON
return response()->json([
    'data' => (new OrderResource($order->load(['customer', 'items'])))->toArray(request()),
]);

// ✅ Correct — full serialization pipeline, MissingValue filtered
return new OrderResource($order->load(['customer', 'items', 'invoice', 'subscription']));

// ✅ Correct for 201
return (new OrderResource($order->load(['customer', 'items'])))->response()->setStatusCode(201);
```

This rule applies to all `confirm`, `cancel`, and `storeFromQuotation` actions in `QuotationController`, `OrderController`, `InvoiceController`, and `SubscriptionController`.

### Frontend integration (Nuxt — shipped)

Pages under `frontend/pages/sales/`:
- `customers/` — index (card grid), `new.vue`, `[id]/index.vue`, `[id]/edit.vue`
- `quotations/` — index, `new.vue`, `[id].vue`
- `orders/` — index, `[id].vue`
- `invoices/` — index, `[id].vue`
- `subscriptions/` — index, `[id].vue`

Key frontend patterns:
- Handle uniqueness: debounced `GET /customers/check-handle` (450ms), live status icon in input.
- Breadcrumb for nested UUID routes: `useBreadcrumbOverride().setEntityName(name)` in detail/edit pages; `clear()` in `onBeforeUnmount`.
- Logo display: check `brandLogoUrl` first (`<img>`), fall back to initial letter avatar.
- `provisionedSubdomain` field on `Customer` type: assembled by `CustomerResource` as `{handle}.{platform.system_domain}`.
