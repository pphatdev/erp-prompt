# Feature Context: Ecommerce (B2C Storefront + Orders + Refunds)

## Scope
Customer-facing B2C storefront and the admin management surface for ecommerce orders and refunds. Reuses:
- **Inventory** for products, variants, stock reservations (`INV-RESERVE`) and storefront catalog (`INV-STOREFRONT`).
- **Sales** for the resulting Invoice (AR posting via `InvoiceService::createFromOrder`).
- **IAM** for role-gated admin pages and a new `shopper` role for storefront-only accounts.
- **FMS/Accounting** for refund journal postings.

Out of scope for this tracker:
- Reviews, coupons, loyalty programs, advanced shipping rules (multi-carrier, rates), wishlists, gift cards.
- Marketplace / multi-vendor patterns.
- Subscription billing on the storefront (covered by Sales subscriptions).

## Module Identity
- Sidebar parent slug: `ecommerce` (already seeded in `ModuleSeeder.php:51`).
- Sidebar children seeded: `ecom-orders` (ECOO prefix), `ecom-refunds` (ECOR prefix).
- Additional admin pages to add: `ecom-products` (catalog curation), `ecom-customers` (shopper accounts), `ecom-settings` (storefront branding, payment provider keys).
- Public storefront routes are unauthenticated and live under `frontend/pages/shop/`.

## Key Architectural Decisions (Phase 0)

### D1. Customer model
B2C shoppers are not the same entity as Sales `Customer` (which is a B2B partner with tenant provisioning). Decision: introduce `EcomCustomer` (separate table, separate auth via Passport `shopper` role). Keep `Customer.customer_type` reserved for B2B variants.

### D2. Order model
Decision: separate `EcomOrder` model with its own status FSM and `ECOO-####` numbering. On successful payment, `EcomOrderService::confirm` calls `InvoiceService::createFromOrder` with a minted Sales `Order` shim so AR posts through the existing pipeline. This keeps the Sales O2C engine as the single source of truth for AR.

### D3. Stock
Decision: use Inventory `INV-RESERVE` (15-min TTL reservation lock) at "add to cart" and `recordMovement(type='ecom_sale')` at order confirm. Refunds call `recordMovement(type='ecom_restock')`. Depends on `INV-RESERVE` being shipped (currently `[/]` in master tracker).

### D4. Payments
Decision: pluggable `PaymentGatewayInterface` with first concrete adapter for Stripe (test mode), behind a feature flag in `settings.ecommerce.payment.provider`. Webhook endpoint validates signature and finalizes the order via idempotent `client_uuid`.

### D5. Refunds
Decision: refunds are initiated from admin only in this phase. Full and partial refunds both create an `EcomRefund` row, optionally a Credit Note in Accounting (Phase 4), and a gateway-side refund call. Storefront customers see refund status on their order detail; they cannot self-request yet.

## Permissions Catalog (planned)
| Resource | Read | Write | Delete | Special |
|---|---|---|---|---|
| Orders (admin) | `ecommerce.orders.read` | `ecommerce.orders.write` | - | `ecommerce.orders.cancel` |
| Refunds (admin) | `ecommerce.refunds.read` | `ecommerce.refunds.write` | - | `ecommerce.refunds.approve` |
| Products (curation) | `ecommerce.products.read` | `ecommerce.products.write` | - | - |
| Customers (shoppers) | `ecommerce.customers.read` | `ecommerce.customers.write` | - | - |
| Settings | `ecommerce.settings.read` | `ecommerce.settings.write` | - | - |
| Storefront (consumer) | `ecommerce.storefront.read` | - | - | - (granted to `shopper` role) |

## Dependencies / Cross-Module
- **Inventory**: `INV-RESERVE` (in progress), `INV-STOREFRONT` (planned). Storefront cannot ship without `INV-RESERVE`.
- **Sales**: `OrderService`, `InvoiceService::createFromOrder` (shipped).
- **FMS**: `AccountingService::postEntry` for refund journals (shipped).
- **Configuration**: numbering prefixes ECOO + ECOR already in `SettingService::defaults()` (verify).
- **Uploads**: product gallery + invoice PDF storage via tenant-scoped filesystem.

## Open Questions
- Payment provider: Stripe-only initially, or also ABA PayWay / Wing (relevant for the user's KH market)?
- Guest checkout allowed, or account required?
- Tax engine: defer to Sales-side tax (currently always 0) or introduce a tax-by-region table now?
- Multi-currency on the storefront: tenant base currency only for v1?

## Success Metrics
- A shopper can browse, add to cart, check out, and pay end-to-end on a fresh tenant.
- Admin can view, fulfill, and refund an order; the Sales Invoice posts to AR; the refund posts a reversing journal.
- Stock decrements at confirm and restocks at refund; reservations expire at 15 min.
- P0 isolation tests pass: tenant A cannot see tenant B's shoppers, orders, or refunds.
