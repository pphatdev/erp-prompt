# eCommerce Module Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `ecommerce`
- **Actions**: `read`, `write`, `delete`, `approve`, `cancel`

### Feature Matrix:
| Feature | Read | Write | Delete | Special Action |
|---------|------|-------|--------|----------------|
| `orders` | `ecommerce.orders.read` | `ecommerce.orders.write` | - | `ecommerce.orders.cancel` |
| `refunds` | `ecommerce.refunds.read` | `ecommerce.refunds.write` | - | `ecommerce.refunds.approve` |
| `products` | `ecommerce.products.read` | `ecommerce.products.write` | - | - |
| `customers` | `ecommerce.customers.read` | `ecommerce.customers.write` | - | - |
| `settings` | `ecommerce.settings.read` | `ecommerce.settings.write` | - | - |
| `storefront` | `ecommerce.storefront.read` | - | - | Granted to `shopper` role |

---

## 2. Database Models & Schema (Tenant DB)

All models reside under `App\Models\Tenant` and MUST use `BelongsToTenant`, `Auditable`, and `SoftDeletes` traits.

| Concept | Model | Table | Key Attributes | Status |
|---|---|---|---|---|
| Shopper | `EcomCustomer` | `ecom_customers` | `id` (UUID), `email` (unique), `password` (hashed), `phone` (encrypted), `first_name`, `last_name` | Planned |
| Shopper Address | `EcomAddress` | `ecom_addresses` | `id` (UUID), `ecom_customer_id` (FK), `address_type` (`shipping`\|`billing`), `address_line1`, `is_default` | Planned |
| Active Cart | `EcomCart` | `ecom_carts` | `id` (UUID), `ecom_customer_id` (FK nullable), `session_token` (nullable), `expires_at` | Planned |
| Cart Line | `EcomCartItem` | `ecom_cart_items` | `id` (UUID), `ecom_cart_id` (FK), `product_id` (FK), `variant_id` (FK), `quantity` | Planned |
| Order | `EcomOrder` | `ecom_orders` | `id` (UUID), `order_number` (ECOO prefix), `ecom_customer_id` (FK), `status` (FSM), `total_amount` | Planned |
| Order Item | `EcomOrderItem` | `ecom_order_items` | `id` (UUID), `ecom_order_id` (FK), `product_id` (FK), `variant_id` (FK), `unit_price`, `quantity` | Planned |
| Payment Log | `EcomPayment` | `ecom_payments` | `id` (UUID), `ecom_order_id` (FK), `provider` (Stripe), `client_uuid` (unique), `amount` | Planned |
| Refund | `EcomRefund` | `ecom_refunds` | `id` (UUID), `refund_number` (ECOR prefix), `ecom_order_id` (FK), `status` (FSM), `refund_amount` | Planned |
| Refund Item | `EcomRefundItem` | `ecom_refund_items` | `id` (UUID), `ecom_refund_id` (FK), `ecom_order_item_id` (FK), `quantity`, `amount` | Planned |

### Migration Blueprints Outline

```php
// Carts Schema
Schema::create('ecom_carts', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('ecom_customer_id')->nullable();
    $table->string('session_token')->nullable()->index();
    $table->timestamp('expires_at')->nullable();
    $table->string('tenant_id');
    $table->timestamps();
    $table->softDeletes();

    $table->foreign('ecom_customer_id')->references('id')->on('ecom_customers')->nullOnDelete();
    $table->index('tenant_id');
});

// Orders Schema
Schema::create('ecom_orders', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->string('order_number')->unique();
    $table->uuid('ecom_customer_id')->nullable();
    $table->string('status')->default('pending_payment'); // pending_payment | paid | fulfilling | shipped | delivered | cancelled | refunded
    $table->decimal('total_amount', 15, 2);
    $table->string('tenant_id');
    $table->timestamps();
    $table->softDeletes();

    $table->index('tenant_id');
});

// Payments Schema
Schema::create('ecom_payments', function (Blueprint $table) {
    $table->uuid('id')->primary();
    $table->uuid('ecom_order_id');
    $table->string('provider'); // stripe, aba, wing
    $table->string('provider_transaction_id')->nullable();
    $table->string('client_uuid')->unique(); // Idempotency key
    $table->decimal('amount', 15, 2);
    $table->string('status');
    $table->jsonb('raw_payload')->nullable();
    $table->string('tenant_id');
    $table->timestamps();

    $table->foreign('ecom_order_id')->references('id')->on('ecom_orders')->cascadeOnDelete();
    $table->index('tenant_id');
});
```

---

## 3. Services Architecture

All core business logic must reside within `App\Tenants\Modules\Ecommerce\Services\`:

### `CartService`
- `addItem(EcomCart $cart, ProductVariant $variant, float $quantity)`
  - Calls `InventoryService::reserve($variant->id, $quantity)` to enforce stock reservation lock.
  - Updates or creates `EcomCartItem` line.
- `mergeGuestCart(string $sessionToken, EcomCustomer $customer)`
  - Moves all items from the guest `session_token` cart to the registered customer's database cart within a single database transaction.

### `CheckoutService`
- `initiate(EcomCart $cart, string $clientUuid)`
  - Transactionally creates a `pending_payment` order with `ECOO` prefix.
  - Generates idempotency token; returning existing order if `clientUuid` matches a completed or pending payment order.
- `confirm(EcomOrder $order, string $transactionId, array $gatewayPayload)`
  - Wrapped inside `DB::transaction()`:
    1. Validates order status is `pending_payment`.
    2. Writes payment row to `ecom_payments`.
    3. Commits the inventory reservation by invoking `StockMovement::recordMovement` (type=`ecom_sale`).
    4. Invokes standard O2C generation by triggering `InvoiceService::createFromOrder` which provisions the downstream Invoice and balanced AR journal records.
    5. Sets order status to `paid`.

### `RefundService`
- `requestRefund(EcomOrder $order, array $lines, string $reason)`
  - Validates order status is `paid` or `delivered` and items returned are within original limits.
  - Creates `EcomRefund` row with status `requested` with sequential `ECOR` prefix.
- `approveRefund(EcomRefund $refund)`
  - Wrapped inside `DB::transaction()`:
    1. Triggers Payment Gateway API refund call.
    2. Logs a restock stock entry via `StockMovement::recordMovement` (type=`ecom_restock`).
    3. Posts a balanced reversing entry to General Ledger: debit AR/Revenue Accounts and credit Cash/Bank Accounts via `AccountingService::postEntry()`.
    4. Sets status to `completed`.

---

## 4. State Machines (FSM Enforcements)

### B2C Order Lifecycle Status Flow:
```
               [pending_payment]
                       │
                       ├────────────────────────► [cancelled] (terminal)
                       ▼
                    [paid]
                       │
                       ▼
                 [fulfilling]
                       │
                       ▼
                   [shipped]
                       │
                       ▼
                  [delivered]
                       │
                       ▼
                   [refunded] (terminal)
```

**Transition Rules:**
- Cancellations are only permitted when the order status is `pending_payment`.
- State updates to final states `cancelled` or `refunded` lock the entity against future modifications.

### Refund Status Flow:
```
[requested] ──► [processing] ──► [completed] (terminal)
    │
    └──────────────────────────► [rejected] (terminal)
```

---

## 5. Accounting & General Ledger Hooks (FMS)

All financial journals originating from B2C actions must map dynamically to tenant-configured Chart of Account mappings:
- **Default Cash/Bank Code**: read from `settings('ecommerce.cash_account_code')`, defaults to `1100` (Cash-on-Hand).
- **Default Gateway Fee Code**: read from `settings('ecommerce.gateway_fee_account_code')`, defaults to `6900` (Bank Charges).
- **Default Revenue Code**: mapped to standard B2B Product category revenue codes.

When an `EcomRefund` reaches `completed`, the system creates a reversing journal entry:
```
Debit: Revenue / Return Account (e.g. 4100) — $Subtotal
Debit: Tax Payable (e.g. 2200)               — $TaxAmount
Credit: Cash / Bank Account (e.g. 1100)      — $TotalRefundAmount
```
If credit notes are enabled (`settings('fms.credit_notes_enabled') === true`), the system issues an official `CreditNote` model instead of raw journal transactions.
