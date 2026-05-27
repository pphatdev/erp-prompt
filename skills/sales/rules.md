# Sales Module Workflow Rules

> Sections marked **(Planned)** describe the target state per [`rules/hybrid_sales_business_flow.md`](../../rules/hybrid_sales_business_flow.md). Sections marked **(Shipped)** match current code today.

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `sales`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `quotations` | `sales.quotations.read` | `sales.quotations.write` | `sales.quotations.delete` | `sales.quotations.export` |
| `orders` | `sales.orders.read` | `sales.orders.write` | `sales.orders.delete` | `sales.orders.export` |
| `invoices` | `sales.invoices.read` | `sales.invoices.write` | `sales.invoices.delete` | `sales.invoices.export` |
| `subscriptions` | `sales.subscriptions.read` | `sales.subscriptions.write` | `sales.subscriptions.delete` | `sales.subscriptions.export` |
| `customers` | `sales.customers.read` | `sales.customers.write` | `sales.customers.delete` | `sales.customers.export` |

**Shipped today:** the matrix above is partially implemented. Quotation/Invoice/Subscription Form Requests currently delegate to `sales.crm.write` / `sales.orders.write`. The **planned** split lands with the status refactor — separation of duties (finance vs ops) becomes enforceable.

---

## 2. Hybrid Sales — TARGET status flow (Planned)

```
Quotation:     draft  --win-->     won   (terminal — converts Lead → Customer, auto-creates draft Sale Order)
               draft  --lose-->    lost  (terminal — loss_reason required)

Sale Order:    draft  --confirm--> confirm  → triggers Fulfillment + Tenant Provisioning
               draft  --cancel-->  cancel   (terminal)
               confirm --cancel--> cancel   (reverse downstream individually)

Subscription:  active --renew-->   active   (extends end_date + new Invoice)
               active --upgrade--> active   (variant swap + delta Invoice)
               active --downgrade--> active (variant swap + credit on next Invoice)
               active --cancel-->  cancelled (terminal)
               active --expire-->  expired  (auto, when end_date < today)
               expired --renew-->  active   (creates a new subscription record)
```

### Planned status enum constants

```php
// Quotation
public const STATUS_DRAFT = 'draft';
public const STATUS_WON   = 'won';
public const STATUS_LOST  = 'lost';

// Order
public const STATUS_DRAFT   = 'draft';
public const STATUS_CONFIRM = 'confirm';
public const STATUS_CANCEL  = 'cancel';

// Subscription
public const STATUS_ACTIVE    = 'active';
public const STATUS_EXPIRED   = 'expired';
public const STATUS_CANCELLED = 'cancelled';
```

### Planned migration: status remap (map-in-place + drop legacy)

```php
// 2024_01_01_0000XX_remap_sales_status_columns.php — per-tenant migration

DB::transaction(function () {
    // Quotation
    DB::table('quotations')->where('status', 'new')->update(['status' => 'draft']);
    DB::table('quotations')->where('status', 'confirmed')->update(['status' => 'won']);
    DB::table('quotations')
        ->where('status', 'cancelled')
        ->update(['status' => 'lost', 'cancel_reason' => DB::raw("COALESCE(cancel_reason, 'Legacy cancellation')")]);

    // Order
    DB::table('orders')->where('status', 'new')->update(['status' => 'draft']);
    DB::table('orders')->where('status', 'confirmed')->update(['status' => 'confirm']);
    DB::table('orders')->where('status', 'cancelled')->update(['status' => 'cancel']);

    // Subscription
    DB::table('subscriptions')->whereIn('status', ['new', 'confirmed'])->update(['status' => 'active']);
    // 'active' / 'expired' / 'cancelled' rows unchanged
});
```

Run via `php artisan tenants:migrate`. After deploy, drop the legacy `STATUS_NEW` / `STATUS_CONFIRMED` / `STATUS_CANCELLED` constants on `Quotation`, `Order`, `Invoice` (where applicable) and remove the legacy code paths.

---

## 3. Models & tables (Tenant DB)

Schema is **mostly unchanged**; the migration above only rewrites enum string values and the planned column additions below.

| Concept | Model | Table | Notes |
|---|---|---|---|
| Catalogue product | `App\Models\Tenant\Product` | `products` | `product_type` (`hardware`\|`software`), `is_active`, `description_long`. |
| Variant axes | `App\Models\Tenant\ProductVariant` | `product_variants` | `attributes` jsonb. |
| Quote | `App\Models\Tenant\Quotation` + `QuotationItem` | `quotations`, `quotation_items` | **Planned status:** `draft`/`won`/`lost`. **Planned new column:** `from_opportunity_id` UUID nullable, FK → `opportunities.id`. |
| Sale Order | `App\Models\Tenant\Order` + `OrderItem` | `orders`, `order_items` | **Planned status:** `draft`/`confirm`/`cancel`. 1:1 with Quotation via `orders.quotation_id`. |
| Invoice (AR) | `App\Models\Tenant\Invoice` + `InvoiceItem` | `invoices`, `invoice_items` | 1:1 with Order. `journal_entry_id` links to GL. Status unchanged (`new/confirmed/cancelled/paid` for now — separate finance concern). |
| Subscription | `App\Models\Tenant\Subscription` + `SubscriptionItem` | `subscriptions`, `subscription_items` | **Planned status:** `active`/`expired`/`cancelled` only. **Planned new columns:** none — `start_date`/`end_date` already exist. |
| Customer | `App\Models\Tenant\Customer` | `customers` | `customer_type` ∈ `individual`\|`business`\|`tenant`. `tenant_handle` unique. |

### Central tenant model (unchanged)

`App\Models\Central\Tenant` uses `handle` as PK. See current section below for full rules.

---

## 4. Services architecture (Planned target)

All under `App\Tenants\Modules\Sales\Services\`:

### `QuotationService`
- `create(array $data): Quotation` — `status=draft`. If `from_opportunity_id` set, snapshots `OpportunityProductSchedule` lines into `quotation_items`.
- `addItem(Quotation, array): QuotationItem` — only when `draft`.
- `removeItem` / `updateItem` — only when `draft`.
- **`win(Quotation): Quotation`** *(new)* — atomic: convert Lead → Customer if Lead has none; create primary `CrmContact` if needed; mark quotation `won`; auto-create draft Sale Order from snapshot. Returns quotation with `order` relation loaded.
- **`lose(Quotation, string $lossReason): Quotation`** *(new)* — requires non-empty reason; marks `lost`; closes linked Lead as `unqualified`.

### `OrderService`
- `createFromQuotation(Quotation): Order` — `status=draft`.
- `confirmOrder(Order): Order` — runs fulfillment orchestrator + **provisioning** in one logical flow (orchestrator inside transaction; provisioning after commit).
- `cancelOrder(Order, ?string $reason): Order` — `draft → cancel` always; `confirm → cancel` requires downstream artifacts reversed first.

### `InvoiceService`
- `createFromOrder` — auto by orchestrator. **Planned change:** Invoice is now created at Order `confirm` (not on a separate user click); the AR journal still posts via a subsequent `confirm()` to give finance separation-of-duties control.
- `confirm` — posts AR journal. **Planned:** no longer auto-confirms the subscription (subscription is `active` from creation already).
- `cancel` — only while `new`. Once posted to GL, requires credit note.

### `SubscriptionService` (re-shaped)
- `createFromOrder(Order): Subscription` — `status=active` from the start, `start_date=today`, `end_date=start_date + billing_cycle`.
- **`renew(Subscription, ?string $cycle = null): Subscription`** *(new)* — extends `end_date` by one cycle; issues a renewal Invoice via `InvoiceService::createForRenewal`. Same transaction.
- **`changePlan(Subscription, array $data, string $action): Subscription`** *(new)* — `action ∈ {upgrade, downgrade}`. Updates `subscription_items` (swap variant) and bills the delta on next cycle (downgrade → credit; upgrade → immediate delta Invoice). Same transaction.
- `cancel(Subscription, ?string $reason): Subscription` — sets `status=cancelled`.
- **Daily scheduled job** *(new)* — `expireSubscriptions` command: `Subscription::where('status', 'active')->where('end_date', '<', today())->update(['status' => 'expired'])`.

### `TenantProvisioningService` (trigger moved)
- Same `provisionForCustomer(Customer $customer, ?object $sub = null)` signature.
- **Planned trigger change:** invoked from `OrderService::confirmOrder` **after commit** when the customer is new AND the order has any software line. Replaces the shipped `SubscriptionConfirmed` listener.
- Idempotent (returns immediately if already provisioned).

### `Fulfillment\OrderFulfillmentService`
- Unchanged orchestration: always Invoice; software → Subscription (now `active`); hardware → StockMovement out.

---

## 5. Cross-module handoffs

### CRM → Sales (Planned)

Sales listens for `App\Tenants\Modules\Crm\Events\LeadQualified` via `App\Tenants\Modules\Sales\Listeners\HandleLeadQualified`:
- Creates a sales-rep task / notification: "Create Quotation from `{lead.title}`".
- Does **not** auto-create a Quotation. The rep clicks the task → opens the Quotation builder pre-filled with the `OpportunityProductSchedule` snapshot.

This replaces the shipped `OpportunityWon → CreateDraftQuotationOnOpportunityWon` listener (which silently creates an empty draft today). Delete that listener as part of the refactor.

### Sales → IAM / Tenant Provisioning (Planned)

`OrderService::confirmOrder` invokes `TenantProvisioningService::provisionForCustomer($customer, $primarySubscription)` once the order transaction commits and the customer has no `provisioned_tenant_id`. Same provisioning service, new caller. The legacy `SubscriptionConfirmed → ProvisionSubscriptionTenant` listener is removed.

### Sales → FMS

Unchanged: `InvoiceService::confirm` posts AR via `AccountingService::postEntry`. Account codes from `SettingService` (`fms.ar_account_code` etc.).

### Sales → Inventory

Unchanged: hardware lines → `StockMovement(type=out)` on the resolved default warehouse.

---

## 6. Customer Account dashboard (Planned)

New page at `pages/sales/customers/[id]/account.vue` (admin-side view of a customer-tenant). Mirrors what the tenant customer sees on their own login, but rendered in the seller's UI.

| Block | Source | Notes |
|---|---|---|
| Access URL chip | `provisionedSubdomain` (assembled by `CustomerResource`) | Copy + open-in-tab |
| Active subscriptions | `customer.subscriptions.where('status', 'active')` | One PrimeVue Card per subscription |
| Countdown badge | `subscription.end_date - now()` | Days remaining, color-coded |
| Action buttons | Per subscription card | Renew / Upgrade / Downgrade / Cancel |

API surface additions:

| Method | Path | Action |
|---|---|---|
| POST | `/subscriptions/{subscription}/renew` | Extend end_date + issue Invoice |
| POST | `/subscriptions/{subscription}/change-plan` | `{product_id, variant_id, action:'upgrade'\|'downgrade'}` |

`POST /subscriptions/{subscription}/cancel` already exists.

---

## 7. Atomicity boundaries (Planned)

| Boundary | Scope |
|---|---|
| `QuotationService::win` | Single `DB::transaction`: customer create-if-needed + CrmContact create-if-needed + lead.customer_id update + quotation.status=won + draft Order create |
| `OrderService::confirmOrder` | Single `DB::transaction`: Invoice create + Subscription create (active from start) + StockMovement out + AR journal post + order.status=confirm. Provisioning runs **after** commit. |
| `SubscriptionService::renew` / `changePlan` | Own `DB::transaction`: subscription update + Invoice create + (changePlan) item swap. |
| `TenantProvisioningService::provision` | Seller-DB `DB::transaction` for `customer` + `subscription` updates; `$centralTenant->run()` is separate (a different DB connection). |

---

## 8. Tenant provisioning — Planned single trigger

| Trigger | Code path | When |
|---|---|---|
| **Order `confirm`** | `OrderService::confirmOrder` → after commit → `TenantProvisioningService::provisionForCustomer($customer, $sub)` | Customer must be `customer_type=tenant` AND not yet provisioned AND order has at least one software line |

The shipped two-trigger setup (immediate on customer create + on `SubscriptionConfirmed`) collapses to this single trigger. Provisioning failures are caught and logged; the order stays `confirm` and a "Retry provisioning" action is exposed on the order detail page.

Removed (Planned):
- `CustomerController::store` no longer calls `TenantProvisioningService`.
- `SubscriptionConfirmed` event + `ProvisionSubscriptionTenant` listener — both deleted.

---

## 9. Shipped — current behaviour (still active until the refactor lands)

### Hybrid Sales — Implementation (Shipped)

#### Models & tables (tenant DB)

| Concept | Model | Table | Notes (Shipped) |
|---|---|---|---|
| Quote | `Quotation` + `QuotationItem` | `quotations`, `quotation_items` | Status: `new` → `confirmed` → (Order); `new` → `cancelled` (terminal). |
| Sales Order | `Order` + `OrderItem` | `orders`, `order_items` | 1:1 with Quotation via `orders.quotation_id` (unique). |
| Invoice (AR) | `Invoice` + `InvoiceItem` | `invoices`, `invoice_items` | 1:1 with Order. `journal_entry_id` links to GL. |
| Subscription | `Subscription` + `SubscriptionItem` | `subscriptions`, `subscription_items` | 1:1 with Order. Software-typed lines only. `provisioned_tenant_id` stores the tenant handle. |
| Customer | `Customer` | `customers` | `customer_type` ∈ `individual`\|`business`\|`tenant`. |

#### Central tenant model (Shipped)

`App\Models\Central\Tenant` uses `handle` as primary key:

```php
protected $primaryKey = 'handle';
public $incrementing  = false;
protected $keyType    = 'string';

public function getTenantKeyName(): string { return 'handle'; }
```

`config/tenancy.php` sets `'id_generator' => null` — the handle must always be provided explicitly on `CentralTenant::create()`. The physical tenant database is named `tenant_{handle}`.

**Never use `$tenant->id`** (no such column). Use `$tenant->getKey()` or `$tenant->handle`.

#### Services (Shipped)

All under `App\Tenants\Modules\Sales\Services\`:
- `TenantProvisioningService` — **single source of truth** for tenant provisioning. Called by `CustomerController::store()` and `ProvisionSubscriptionTenant` listener. Idempotent.
- `QuotationService` — create, addItem, confirm, cancel. Locks edits once status leaves `new`.
- `OrderService` — `createFromQuotation`, `confirmOrder` (triggers fulfillment), `cancelOrder`.
- `InvoiceService` — `createFromOrder`, `confirm` (posts AR journal **then auto-confirms linked subscription**), `cancel`.
- `SubscriptionService` — `createFromOrder`, `confirm` (commits in own transaction, then dispatches `SubscriptionConfirmed`), `cancel`.
- `Fulfillment\OrderFulfillmentService` — orchestrator called by `OrderService::confirmOrder`.

#### Status flow — Shipped

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
               confirmed  --provisioned-->  active   (set by TenantProvisioningService)
               any  --cancel-->   cancelled
```

#### Invoice → Subscription auto-activation (Shipped, P1)

`InvoiceService::confirm()` is the payment trigger. After the accounting transaction commits it calls `activateLinkedSubscription()`, which:
1. Loads `invoice → order → subscription`.
2. If the subscription is still `new`, calls `SubscriptionService::confirm()`.
3. `confirm()` commits the status update in its own transaction, then dispatches `SubscriptionConfirmed` **outside** any open transaction so the listener sees committed data.
4. `ProvisionSubscriptionTenant` listener delegates to `TenantProvisioningService`.

Provisioning failures are caught, logged, and do **not** roll back the committed invoice.

#### Tenant provisioning triggers (Shipped)

| Trigger | Code path | When |
|---|---|---|
| Customer created with `customer_type = tenant` | `CustomerController::store()` → `TenantProvisioningService::provisionForCustomer($customer)` | Immediately on `POST /customers` |
| Software subscription confirmed | `SubscriptionConfirmed` → `ProvisionSubscriptionTenant` → `TenantProvisioningService::provisionForCustomer($customer, $sub)` | After invoice → subscription chain |

**Planned:** collapse to a single trigger on Order `confirm`. See § 8.

#### TenantProvisioningService — provision flow (Shipped)

`provisionForCustomer(Customer $customer, ?object $sub = null)`:

1. Skip if `customer_type !== 'tenant'`.
2. If already provisioned: mirror `provisioned_tenant_id` onto `$sub` (if given); seed subscription products inside existing tenant; return.
3. Derive handle: `customer.tenant_handle` or call `deriveHandle()` (slug + 4-char suffix).
4. Create `App\Models\Central\Tenant` with `handle` as PK.
5. Register subdomain.
6. Pre-load `$sub->loadMissing('items')->items` in the **seller's** DB context.

---

## Code Numbering (Tenant-Configurable)

Document numbers (`quote_number`, `order_number`, `invoice_number`, `subscription_number`) read their prefix from per-tenant settings. Admins edit them under **Settings → Numbering**. Stored values include any separator (e.g. `INV-`), so the generator concatenates: `{prefix}YYYYMMDD-XXXXXX`. If the per-tenant setting is missing, null, or empty, the generator MUST fall back to its conventional default to guarantee business continuity. Changes only affect new records.

| Entity | Setting key | Default | Format | Generator |
|---|---|---|---|---|
| Quotation | `numbering.quotation_prefix` | `QT-` | `{prefix}YYYYMMDD-{6×random}` | `QuotationService::generateQuoteNumber` |
| Sales Order | `numbering.order_prefix` | `SO-` | `{prefix}YYYYMMDD-{6×random}` | `OrderService::generateOrderNumber` |
| Invoice | `numbering.invoice_prefix` | `INV-` | `{prefix}YYYYMMDD-{6×random}` | `InvoiceService::generateInvoiceNumber` (also used by `SubscriptionService` renewals) |
| Subscription | `numbering.subscription_prefix` | `SUB-` | `{prefix}YYYYMMDD-{6×random}` | `SubscriptionService::generateSubscriptionNumber` |

The random suffix (6 uppercase alphanumeric) is collision-safe enough at expected scale; uniqueness is also enforced at the DB level via per-tenant composite indexes.

7. `DB::transaction`: update `customer.provisioned_tenant_id`, `customer.provisioned_at`, optionally update subscription status → `active`.
8. `$centralTenant->run()`: migrate, seed (`TenantDatabaseSeeder`), create customer admin user (`customer.email`, password `'password'`, admin role), seed branding, call `seedSubscriptionProducts($subItems)`, restrict module visibility to entitled modules + core.

#### Subscription product seeding (Shipped)

`seedSubscriptionProducts(Collection $items)` runs **inside** `$centralTenant->run()`:

- Iterates each `SubscriptionItem`; uses `variant_sku` as SKU, falls back to `Str::slug($product_name)`.
- `Product::updateOrCreate(['sku' => $sku], ['name' => ..., 'product_type' => 'software', 'unit_price' => ..., 'is_active' => true])`.

System domain is read from `config('platform.system_domain')` → `.env` `APP_SYSTEM_DOMAIN`.

#### Default tenant credentials (Shipped)

Every provisioned tenant gets one admin user automatically:

| Field | Value |
|---|---|
| Email | Customer's `email` field |
| Password | `password` (plaintext passed; `hashed` cast hashes once) |
| Role | `admin` |

Additional seeded users (from `TenantDatabaseSeeder`):

| Email | Password | Role |
|---|---|---|
| `admin@example.com` | `password` | admin |
| `role.base@tanent.com` | `password` | employee |

#### Repairing credentials on existing tenants

```bash
php artisan tenants:repair-credentials --tenant={handle}
# Omit --tenant to repair all tenants
```

#### Customer → Tenant handle rules (Shipped)

- `tenant_handle` required when `customer_type = 'tenant'`.
- Format: `^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])?$`.
- Uniqueness enforced at DB and validation level.
- Frontend debounce-checks via `GET /customers/check-handle` (450ms).

#### Account resolution for AR journal (Shipped)

`InvoiceService::confirm` posts:
```
DR  Accounts Receivable    total_amount
  CR  Sales Revenue        subtotal
  CR  Sales Tax Payable    tax_amount   (only when > 0)
```

Account codes via `SettingService`:
- `fms.ar_account_code`      — default `1200`
- `fms.revenue_account_code` — default `4000`
- `fms.tax_account_code`     — default `2150`

#### Warehouse resolution for hardware (Shipped)

`OrderFulfillmentService::resolveDefaultWarehouse()` reads:
1. `Setting('inventory.default_warehouse_code')`.
2. The sole Warehouse row when only one exists.
3. Otherwise throws — tenant must configure.

#### API surface (Shipped — to evolve per § 6 + § 4)

| Method | Path | Action |
|---|---|---|
| GET | `/customers/check-handle?handle=&[ignore_id=]` | Real-time handle availability check |
| GET/POST/PUT/DELETE | `/customers` | Full CRUD |
| POST | `/quotations` | Create new draft quote with line items |
| POST | `/quotations/{quotation}/items` | Append a line to a `new` quote |
| POST | `/quotations/{quotation}/confirm` | Lock quote *(planned rename: `/win`)* |
| POST | `/quotations/{quotation}/cancel` | Terminal cancel *(planned rename: `/lose` + require loss_reason)* |
| POST | `/quotations/{quotation}/convert-to-order` | Snapshot confirmed quote into a Sales Order *(planned: merged into `/win`)* |
| POST | `/orders/{order}/confirm` | Confirm + auto-fulfill *(Planned: also triggers provisioning)* |
| POST | `/orders/{order}/cancel` | Cancel only while `new` |
| POST | `/invoices/{invoice}/confirm` | Post AR journal |
| POST | `/invoices/{invoice}/cancel` | Allowed only while `new` |
| POST | `/subscriptions/{subscription}/confirm` | Fire `SubscriptionConfirmed` *(planned removal — subscriptions start `active`)* |
| POST | `/subscriptions/{subscription}/cancel` | At any status |
| POST | `/subscriptions/{subscription}/renew` *(planned)* | Extend end_date + Invoice |
| POST | `/subscriptions/{subscription}/change-plan` *(planned)* | Upgrade / downgrade |

> **Route ordering (P0):** `GET /customers/check-handle` must be registered **before** `Route::apiResource('customers', ...)`.

### Controller response pattern (P0)

**Never** use `response()->json(['data' => (new XxxResource(...))->toArray(...)])` in action methods. Always `return new XxxResource(...)` directly (or `.response()->setStatusCode(201)`).

```php
// ❌ Wrong — MissingValue leaks as {} in JSON
return response()->json([
    'data' => (new OrderResource($order->load(['customer', 'items'])))->toArray(request()),
]);

// ✅ Correct
return new OrderResource($order->load(['customer', 'items', 'invoice', 'subscription']));

// ✅ Correct for 201
return (new OrderResource($order->load(['customer', 'items'])))->response()->setStatusCode(201);
```

### Frontend integration (Nuxt — shipped)

Pages under `frontend/pages/sales/`:
- `customers/` — index, `new.vue`, `[id]/index.vue`, `[id]/edit.vue`. **Planned addition:** `[id]/account.vue` (Customer Account dashboard).
- `quotations/` — index, `new.vue`, `[id].vue`. **Planned:** Quotation detail surfaces `Mark Won` / `Mark Lost` buttons instead of `Confirm` / `Cancel`.
- `orders/` — index, `[id].vue`. **Planned:** Order confirm modal warns about Tenant provisioning side effect.
- `invoices/` — index, `[id].vue`.
- `subscriptions/` — index, `[id].vue`. **Planned:** detail page exposes Renew / Upgrade / Downgrade buttons.

Key frontend patterns (Shipped):
- Handle uniqueness: debounced `GET /customers/check-handle` (450ms), live status icon.
- Breadcrumb override: `useBreadcrumbOverride().setEntityName(name)`; `clear()` in `onBeforeUnmount`.
- `provisionedSubdomain` field on `Customer`: assembled by `CustomerResource` as `{handle}.{platform.system_domain}`.
- User password reset modal: `POST /users/{id}/reset-password`.
