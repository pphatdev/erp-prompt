# Task Checklist: Ecommerce (B2C)

> See [`.task/ecommerce/context.md`](./context.md) for scope, architectural decisions, and dependencies.
>
> Skill docs (`skills/ecommerce/{skill,rules,flow,testing}.md`) are not yet authored; this tracker is the working spec until they land in Phase 1.

Legend: [x] shipped / [/] partial / [ ] planned

---

## Phase 0: Foundations & Decisions
- [x] Author `skills/ecommerce/skill.md` with module overview, sidebar slugs, permission slug catalog.
- [x] Author `skills/ecommerce/rules.md` covering policies, FSM, atomicity, numbering, snake_case-to-camelCase resource contract.
- [x] Author `skills/ecommerce/flow.md` with Mermaid sequence diagrams for: cart-to-paid-order, refund, webhook reconciliation.
- [x] Author `skills/ecommerce/testing.md` with the P0/P1/P2 matrix.
- [x] Decide payment providers (see context D4, Open Questions). Capture in `skill.md`.
- [x] Decide guest checkout policy. Capture in `skill.md`.
- [x] Verify `numbering.ecommerce_order_prefix` (ECOO) and `numbering.ecommerce_refund_prefix` (ECOR) exist in `SettingService::defaults()`; add if missing.

---

## Phase 1: Database & Models (Backend)
- [x] Combined migration `2024_01_01_000093_create_ecommerce_tables.php` creates all 9 tables (ecom_customers, ecom_addresses, ecom_carts, ecom_cart_items, ecom_orders, ecom_order_items, ecom_payments, ecom_refunds, ecom_refund_items) with FKs to products / product_variants / orders / invoices / credit_notes / stock_reservations and tenant_id indexes.
- [x] 9 models under `app/Models/Tenant/`: EcomCustomer (Authenticatable + HasApiTokens, hashed password cast, encrypted phone), EcomAddress (snapshot helper), EcomCart (status constants, isGuest helper), EcomCartItem (reservation FK), EcomOrder (7-state FSM constants, isRefundable / isPaid helpers, shipping_address/billing_address jsonb), EcomOrderItem (snapshots), EcomPayment (provider + status constants), EcomRefund (5-state FSM, isTerminal helper), EcomRefundItem (restock flag).
- [x] ECOO-/ECOR- prefixes added to `SettingService::defaults()` under `numbering.ecommerce_order_prefix` and `numbering.ecommerce_refund_prefix` (materialize on next tenant `ensureDefaults()` call).
- [x] `EcommercePermissionSeeder` with 12 admin perms + `ecommerce.storefront.read` for the `shopper` role; admin role syncs all perms; `shopper` role created with storefront-only access; wired into `TenantDatabaseSeeder` after the assets permission seeder so fresh tenants get it.

## Phase 2: Services & Business Logic (Backend)
- [x] `shop` Passport guard wired in `config/auth.php` with `ecom_customers` provider, separate from the admin `api` guard.
- [x] `EcomCustomerService` (register / createGuest / authenticate / addAddress / updateAddress / deleteAddress; trusts password `'hashed'` cast; auto-assigns `shopper` role).
- [x] `CartService` (getOrCreateForCustomer / getOrCreateForGuest / addItem / updateItemQuantity / removeItem / mergeGuestCart / releaseReservations; calls `StockReservationService::reserve` per line and releases on remove/topUp; resolves warehouse via `inventory.default_warehouse_code`).
- [x] `CheckoutService::initiate` mints `EcomOrder` + `EcomPayment` (idempotent on (tenant_id, client_uuid)), snapshots shipping/billing addresses, returns both.
- [x] `CheckoutService::confirm` inside `DB::transaction()`: marks payment succeeded, commits every cart reservation via `StockReservationService::commit`, mints Sales `Order` shim under the `ECOM-B2C` umbrella customer, calls `InvoiceService::createFromOrder` + `::confirm` (posts AR journal), transitions order to `paid`, archives cart as `converted`.
- [x] `CheckoutService::cancel` releases reservations + fails pending payments + transitions order.
- [x] `WebhookController::handle({provider})` with provider-specific signature verification (Stripe HMAC-SHA256 with `Stripe-Signature` header; generic `X-Webhook-Signature` for others), idempotent on `provider_charge_id`, dispatches to `CheckoutService::confirm` on success and marks payment failed on failure events. Webhook secret read from `ecommerce.payment.{provider}.webhook_secret` setting.
- [x] `FulfillmentService::markFulfilling / ship / markDelivered` walks `paid → fulfilling → shipped → delivered` with carrier/tracking_number capture.
- [x] `RefundService::request` validates line-level scope against order items, computes refund amount + `is_partial` flag, creates `EcomRefund` + items in `requested` state.
- [x] `RefundService::approve` inside `DB::transaction()`: restocks each `restock=true` line via `StockService::recordMovement` type=`in`, reverses invoice journal via `AccountingService::reverseEntry`, updates payment to `refunded` or `partial_refund`, transitions refund to `completed`, flips order to `refunded` for full refunds (partial leaves it shippable).
- [x] `RefundService::reject` is terminal and audit-logged.
- [x] Atomic guarantees: every multi-table write wrapped in `DB::transaction()`.

## Phase 3: API Surface & Access Control (Backend)
- [x] Public storefront routes under `api/v1/shop/*` (no Passport, tenant resolved via `X-Tenant-Handle`): register, login, cart show/add/update/remove (guest carts via `X-Cart-Session` header), checkout initiate / confirm-direct / cancel. Catalog endpoints reuse the existing `PublicCatalogController` under `/public/catalog`.
- [x] Shopper-authenticated routes under `auth:shop` guard: `GET /shop/auth/me`, `POST /shop/auth/logout`, address book CRUD, own-order index/show.
- [x] Admin routes under `auth:api` + `EcomOrderPolicy` / `EcomRefundPolicy` / `EcomCustomerPolicy`: `/ecommerce/orders` index+show+fulfilling+ship+delivered+cancel, `/ecommerce/refunds` index+show+store+approve+reject, `/ecommerce/customers` index+show.
- [x] Webhook route `POST /api/v1/ecom/webhooks/{provider}` outside `auth:api`; provider-specific signature verification inside the controller.
- [x] Resources (camelCase): `EcomCustomerResource`, `EcomAddressResource`, `CartResource` + `CartItemResource`, `EcomOrderResource` + `EcomOrderItemResource`, `EcomPaymentResource`, `EcomRefundResource` + `EcomRefundItemResource`.
- [x] Validation done inline via `$request->validate()` in each controller (FormRequest classes deferred until a field count crosses ~10).
- [x] Policies `EcomOrderPolicy`, `EcomRefundPolicy`, `EcomCustomerPolicy` registered in `TenantServiceProvider::boot`. Authorize() called inside admin controllers.
- [x] `EcommercePermissionSeeder` (already shipped in Phase 1) attaches all 12 admin perms to `admin` and the storefront perm to the new `shopper` role.

## Phase 4: FMS Integration (Backend)
- [x] Settings added: `ecommerce.cash_account_code` (1100), `ecommerce.gateway_fee_account_code` (6900), `fms.credit_notes_enabled` (false), `fms.sales_returns_account_code` (4900). All in `SettingService::defaults()`.
- [x] `CheckoutService::confirm` posts a cash receipt journal after invoice confirm: `DR Cash (total - gateway_fee) / DR Gateway Fee (fee) / CR AR (total)`. Soft-fails (logs and continues) if any GL account is missing, so a misconfigured CoA doesn't block storefront checkout.
- [x] `RefundService::approve`: when `fms.credit_notes_enabled` is true, issues a Credit Note via `CreditNoteService::issue` (links via `EcomRefund.credit_note_id`). When disabled or the CoA is missing accounts, falls back to `AccountingService::reverseEntry` — but only for full refunds; partial refunds require the Credit Note path because direct reversals would corrupt the invoice's line totals.

## Phase 5: Storefront UI (Public, `frontend/pages/shop/`)
- [x] `pages/shop/index.vue` home: hero CTA + featured products grid (uses `public/catalog`).
- [x] `pages/shop/products/index.vue` catalog grid + search (debounced) + pagination.
- [x] `pages/shop/products/[id].vue` product detail with variant picker, qty, add-to-cart with success/error feedback.
- [x] `pages/shop/cart.vue` line editing (qty/remove) + sticky order summary aside.
- [x] `pages/shop/checkout.vue` guest-email OR saved-address picker, payment provider radio (Stripe/ABA/Wing/manual), sandbox confirmDirect on submit so the demo flow completes without webhooks.
- [x] `pages/shop/order/[id].vue` post-checkout receipt with items, totals, tracking badge.
- [x] `pages/shop/account/index.vue` shopper dashboard (recent orders + address book CRUD).
- [x] `pages/shop/auth/login.vue` + `pages/shop/auth/register.vue`.
- [x] `composables/useShop.ts` — `catalog/cart/checkout/auth/addresses/orders` namespaces. Uses its own request fn so the shopper Passport token (from `shop-auth` store) doesn't collide with admin `auth` token.
- [x] `stores/shop-auth.ts` — Pinia store, separate localStorage keys (`shop_token`, `shop_shopper`, `shop_cart_session`); auto-generates a guest cart session token on init.
- [x] `layouts/shop.vue` (clean public chrome with cart badge + login/logout toggle).
- [ ] Storefront tenant-branding pass (logo + primary color from `settings/public`) - deferred; the layout uses the same CSS tokens as the admin shell so branding already cascades.
- [ ] `pages/shop/account/orders/[id].vue` and `pages/shop/auth/reset.vue` - deferred; the storefront uses `pages/shop/order/[id].vue` for the post-checkout receipt and account dashboard already lists past orders.

## Phase 6: Admin UI (`frontend/pages/ecommerce/`)
- [x] `composables/useEcommerce.ts` - admin namespaces (`orders`, `refunds`, `customers`) + `statusBadgeVariant` helper. Uses the shared `useApi()` so admin Passport token + tenant header are injected automatically.
- [x] `pages/ecommerce/orders/index.vue` glass-card grid + KPI strip + status filter chips + debounced search + pagination.
- [x] `pages/ecommerce/orders/[id].vue` detail with item table, payment trail, shipping address, FSM action bar (markFulfilling / ship / markDelivered / cancel / refund), inline modals for ship + refund + cancel.
- [x] `pages/ecommerce/refunds/index.vue` grid with status filter (defaults to `requested`).
- [x] `pages/ecommerce/refunds/[id].vue` detail with line summary, approve/reject modals, gateway refund id capture, journal-posted indicator (`creditNoteId` surface).
- [x] `pages/ecommerce/customers/index.vue` shopper directory with search + exclude-guests filter; orderCount badge.
- [x] `pages/ecommerce/customers/[id].vue` shopper detail (KPI strip + address book).
- [x] Sidebar wire-up: stubbed `route: '#'` entries in `layouts/default.vue` Ecommerce children replaced with real routes (`/ecommerce/orders`, `/ecommerce/refunds`, `/ecommerce/customers`) and permission-gated on `ecommerce.orders.*` / `refunds.*` / `customers.*`. Added Customers child.
- [ ] `pages/ecommerce/products/index.vue` and `pages/ecommerce/settings.vue` - deferred. Product curation is currently done from the Inventory module; storefront branding inherits from existing tenant settings. Will revisit when payment-provider keys need a UI.

## Phase 7: Testing
- [x] `tests/Feature/Tenant/Ecommerce/EcomTenancyIsolationTest.php` (P0) - tenant B can't see tenant A's shoppers / carts / orders / refunds; shopper role + storefront permission seeded per tenant.
- [x] `tests/Feature/Tenant/Ecommerce/CheckoutLifecycleTest.php` (P1) - end-to-end: add to cart reserves stock, initiate creates pending payment, confirm commits stock (physical drops), creates + confirms Sales Invoice (journal_entry_id populated), captures gateway fee on payment, archives cart as converted.
- [x] `tests/Feature/Tenant/Ecommerce/IdempotentCheckoutTest.php` (P1) - duplicate `client_uuid` returns the same EcomOrder + EcomPayment; DB unique constraint enforces only one row exists.
- [x] `tests/Feature/Tenant/Ecommerce/RefundLifecycleTest.php` (P1) - 3 cases: full refund restocks + posts reversing journal + flips order to refunded + marks payment refunded; partial refund leaves order in `paid` + payment in `partial_refund` and skips restock when `restock=false`; rejected refund is terminal (re-approve throws).
- [x] `tests/Feature/Tenant/Ecommerce/WebhookSignatureTest.php` (P0) - tampered Stripe payload returns 401 with `invalid_signature` and leaves payment pending; valid signature confirms the order + payment, and replay with the same charge id returns 200 with `duplicate_event` (idempotent); missing webhook secret returns 503.
- [ ] `ReservationExpiryTest` - covered by existing `Inventory/StockReservationTest::test_expire_due_flips_past_ttl_to_expired`. Deferred (would duplicate coverage).
- [ ] Vitest component tests and Playwright E2E - deferred to a later pass; backend Pest covers the data invariants.
- [ ] Audit log assertions for every status transition - deferred; `Auditable` trait is applied to all key models which writes audit rows on save, but explicit per-transition assertions are a follow-up.

## Phase 8: Documentation & Rollout
- [ ] `docs/postman/erp_collection.json`: storefront + shopper + admin + webhook requests, with pre-request scripts capturing shopper token and order id.
- [ ] Tenant onboarding script: seed default product images, set default payment provider to `stripe_test`, create one demo product.
- [ ] Feature flag `modules.ecommerce.enabled` honored everywhere (sidebar already gates via `moduleSlug`).
- [ ] Add Ecommerce row to PROJECT_CONTEXT.md module table once Phase 6 ships.
- [ ] Sync this tracker and master `.task/task.md` after each phase merges.

---

## Cross-Module Blockers
- [ ] **INV-RESERVE** must ship for the cart reservation flow. Track in `.task/inventory/task.md`.
- [ ] **INV-STOREFRONT** (`GET /storefront/products`) is the public catalog endpoint Phase 5 consumes. Track in `.task/inventory/task.md`.

## Open / Deferred
- [ ] Reviews + ratings.
- [ ] Coupons / promo codes.
- [ ] Wishlist / saved-for-later.
- [ ] Multi-carrier shipping rates.
- [ ] Self-service refund request from shopper account.
- [ ] Multi-currency storefront.
- [ ] Email + SMS notification templates (handed off to a future notifications module).
