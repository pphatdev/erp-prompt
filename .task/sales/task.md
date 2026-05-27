# Task Context: Sales (Hybrid O2C)

## Objective
Implement the canonical hybrid sales flow per [`rules/hybrid_sales_business_flow.md`](../../rules/hybrid_sales_business_flow.md): Customer → Quotation → Sales Order → fulfillment fan-out into Invoice (AR posted), Subscription (provisioning hook), Hardware (stock deduction).

## Checklist — Backend

### Phase 1 — Foundations
- [x] `2024_01_01_000034_add_product_type_and_variants.php` adds `products.product_type` (`hardware`\|`software`), `is_active`, `description_long`
- [x] `product_variants` table with jsonb `attributes` for generic axes
- [x] `Product` model: `TYPE_HARDWARE` / `TYPE_SOFTWARE` constants, `isSoftware()` / `isHardware()` helpers, `variants()` relation
- [x] `ProductVariant` model

### Phase 2 — Quotation
- [x] `2024_01_01_000035_create_quotations_tables.php` (`quotations` + `quotation_items`)
- [x] `Quotation` + `QuotationItem` models (status `new`/`confirmed`/`cancelled`)
- [x] `QuotationService` (create, addItem, confirm, cancel; snapshots `product_name`/`product_type`/`unit_price` at quote time)
- [x] `QuotationController` + `QuotationResource` + `QuotationItemResource`
- [x] `StoreQuotationRequest`, `AddQuotationItemRequest`

### Phase 3 — Sales Order conversion
- [x] `2024_01_01_000036_extend_orders_for_hybrid_sales.php` (orders.quotation_id unique, orders.subtotal/tax_amount/due_date/confirmed_at/cancelled_at/cancel_reason, order_items.product_id/variant_id/product_type/variant_sku/due_date/notes, quantity widened to decimal(12,2))
- [x] `Order` model status constants + relations (`quotation`, `invoice`, `subscription`)
- [x] `OrderItem` model relations + helpers
- [x] `OrderService::createFromQuotation`, tightened `confirmOrder`, `cancelOrder`
- [x] `OrderController::storeFromQuotation` + tightened resource

### Phase 4 — Invoice + AR
- [x] `2024_01_01_000037_create_invoices_tables.php` (`invoices` 1:1 with `orders`, `invoice_items`, `journal_entry_id` FK)
- [x] `Invoice` + `InvoiceItem` models
- [x] `InvoiceService::createFromOrder`, `confirm` (posts balanced AR journal via `AccountingService`), `cancel`
- [x] `InvoiceController` + `InvoiceResource` + `InvoiceItemResource`
- [x] AR/Revenue/Tax account codes resolved via `SettingService` keys (`fms.ar_account_code`, etc.) with defaults `1200`/`4000`/`2150`

### Phase 5 — Subscription + event
- [x] `2024_01_01_000038_create_subscriptions_tables.php` (`subscriptions` 1:1 with `orders`, `subscription_items`)
- [x] `Subscription` + `SubscriptionItem` models
- [x] `SubscriptionService::createFromOrder` (only when order has software lines), `confirm` (dispatches event), `cancel`
- [x] `SubscriptionConfirmed` event
- [x] `ProvisionSubscriptionTenant` listener (no-op + log — extension point documented)
- [x] Event listener registered in `TenantServiceProvider::boot`
- [x] `SubscriptionController` + `SubscriptionResource`

### Phase 6 — Fulfillment orchestrator
- [x] `OrderFulfillmentService::fulfill` always creates Invoice; software → Subscription; hardware → StockService `out` movements
- [x] Default warehouse resolution: `inventory.default_warehouse_code` setting → single-warehouse fallback → throws
- [x] Whole orchestrator runs inside `OrderService::confirmOrder`'s transaction (no partial fulfillment possible)

### Phase 7 — Routes + tests
- [x] All routes registered under `/api/v1` with `auth:api`
- [x] Pest feature test `HybridSalesFlowTest` covers: happy path end-to-end (quote→order→confirm→invoice+sub+stock, then confirm invoice posts balanced AR journal, then confirm subscription fires event); unconfirmed-quote rejection; cancelled-quote can't be confirmed

### Phase 8 — Docs
- [x] `skills/sales/rules.md` rewritten to reflect shipped implementation (models, services, status flow, atomicity, account/warehouse resolution, API surface)
- [x] `skills/sales/flow.md` updated with sequence diagram of the backend call graph + cancellation guards
- [x] This `.task/sales/task.md` updated

## Checklist — Frontend

### Phase 1 — Types + composable
- [x] `frontend/types/sales.ts` mirrors every backend Resource (camelCase wire format)
- [x] `frontend/composables/useSales.ts` — `quotations` / `orders` / `invoices` / `subscriptions` / `catalogue` namespaces + shared `statusBadgeVariant`
- [x] Backend `ProductResource` extended with `product_type` + eager-loaded `variants` (so the inline variant picker has everything in one fetch)

### Phase 2 — Quotation UI
- [x] `pages/sales/quotations/index.vue` — card grid + filter strip + create modal with line items, inline variant dropdown per product, live subtotal
- [x] `pages/sales/quotations/[id].vue` — detail (summary tiles, line item table, confirm/cancel/convert-to-order actions, audit trail)

### Phase 3 — Sales Order UI
- [x] `pages/sales/orders/index.vue` — card grid showing invoice/subscription badges per order
- [x] `pages/sales/orders/[id].vue` — detail with atomic-confirm warning, downstream artifact links, line item table

### Phase 4 — Invoices + Subscriptions UI
- [x] `pages/sales/invoices/index.vue` — card grid + status filter (includes `paid`); "Posted to GL" badge when `journalEntryId` is set
- [x] `pages/sales/invoices/[id].vue` — detail with AR-posting warning, journal entry surface, line item table, cancel guard
- [x] `pages/sales/subscriptions/index.vue` — card grid with billing-cycle + provisioning badges
- [x] `pages/sales/subscriptions/[id].vue` — detail with provisioning event explainer, provisioned-tenant block, line items

### Phase 5 — Navigation + docs
- [x] `layouts/default.vue` Sales nav group with Quotations / Sales Orders / Invoices / Subscriptions children (permission-gated)
- [x] Breadcrumb `SLUG_LABELS` updated for the new path segments
- [x] This task tracker updated

## Open / Planned
- [ ] Real `ProvisionSubscriptionTenant` listener — create Central\Tenant, run tenant migrations, seed default IAM, create admin User, email activation link
- [ ] Credit-note / invoice reversal flow in FMS (currently confirmed invoices can't be cancelled at all)
- [ ] Tax engine to populate `quotations.tax_amount` / `orders.tax_amount` / `invoices.tax_amount` (currently always 0)
- [ ] Kanban funnel view across all four stages (Quote → Order → Invoice + Subscription)
- [ ] Realtime broadcast on status transitions via Reverb (`sales.{handle}` channel) — surface live status badge updates on the list pages
- [ ] Dedicated permission slugs (`sales.quotations.*`, `sales.invoices.confirm`, etc.) for finance separation of duties; frontend nav permissions already wired to `sales.crm.*` / `sales.orders.*`
- [ ] Variant-level stock tracking (current StockMovement is product-level — variants share one stock bucket)
- [ ] CoA seeder for the default AR/Revenue/Tax accounts so a fresh tenant can invoice without manual setup
- [ ] Variant management UI on the Products page (currently variants are read-only via the eager-loaded backend response — no create/edit yet)
- [ ] PDF export of quotation/invoice (print stylesheet exists implicitly via Tailwind; dedicated print view pending)
- [ ] Vitest coverage for the Quotation builder (line totals, variant price overrides, canSubmit guard)

## Phase 9 — Target-flow refactor (Planned)
Spec: [`rules/hybrid_sales_business_flow.md`](../../rules/hybrid_sales_business_flow.md), [`skills/sales/rules.md`](../../skills/sales/rules.md), [`skills/sales/flow.md`](../../skills/sales/flow.md).

### 9.1 Status enum refactor
- [x] Migration `2024_01_01_000049_remap_sales_status_columns` (per-tenant): `quotations.status` new→draft, confirmed→won, cancelled→lost (backfill `loss_reason`); `orders.status` new→draft, confirmed→confirm, cancelled→cancel; `subscriptions.status` new/confirmed→active.
- [x] Migration `2024_01_01_000050_add_from_opportunity_id_to_quotations` — adds `from_opportunity_id`, `loss_reason`, `won_by/at`, `lost_by/at`.
- [x] Drop legacy `Quotation::STATUS_NEW` / `STATUS_CONFIRMED` / `STATUS_CANCELLED` constants. Same for `Order` and `Subscription`.
- [x] Update `QuotationService`, `OrderService`, `SubscriptionService` to use new constants.

### 9.2 Quotation → Lead-to-Customer conversion
- [x] Rename `QuotationService::confirm` → `win` (atomic Lead → Customer creation, CrmContact create, draft Order create). `cancel` → `lose` (requires `loss_reason`).
- [x] Rename routes: `POST /quotations/{id}/confirm` → `/win`, `POST /quotations/{id}/cancel` → `/lose`. Removed `/convert-to-order`.
- [x] Add `quotations.from_opportunity_id` (UUID nullable, FK → opportunities.id) — see migration above.
- [x] Sales-side `HandleLeadQualified` listener — creates a `CrmActivity` of type=task on the Opportunity.
- [x] Deleted legacy `App\Tenants\Modules\Sales\Listeners\CreateDraftQuotationOnOpportunityWon`.

### 9.3 Tenant provisioning trigger move
- [x] `OrderService::confirmOrder` invokes `TenantProvisioningService::provisionForCustomer` after commit when `customer_type=tenant` AND has subscription.
- [x] Removed `TenantProvisioningService` call from `CustomerController::store()`.
- [x] Deleted `SubscriptionConfirmed` event + `ProvisionSubscriptionTenant` listener.
- [x] `SubscriptionService::createFromOrder` returns subscriptions with `status=active` directly (no separate confirm step).
- [x] `InvoiceService::confirm` no longer auto-activates linked subscription (already active); injected `SubscriptionService` dependency removed.

### 9.4 Subscription lifecycle additions
- [x] `SubscriptionService::renew(Subscription, ?string $cycle)` — extends `end_date`, issues renewal Invoice.
- [x] `SubscriptionService::changePlan(Subscription, array $data, string $action)` — upgrade/downgrade variant + delta/credit invoice.
- [x] Scheduled command `subscriptions:expire` — daily job (02:00) per-tenant fan-out via `App\Console\Commands\ExpireSubscriptions`.
- [x] `POST /subscriptions/{id}/renew` route + `SubscriptionController::renew`.
- [x] `POST /subscriptions/{id}/change-plan` route + `SubscriptionController::changePlan`.

### 9.5 Customer Account dashboard (Frontend)
- [x] `pages/sales/customers/[id]/account.vue` — Access URL chip + copy button, active/expired/cancelled subscription cards, countdown badges, Renew/Upgrade/Downgrade/Cancel modal flows.
- [x] `useSales().subscriptions.renew(id, payload)` / `.changePlan(id, payload)` composable methods (+ removed `.confirm`).
- [x] `components/sales/SubscriptionCountdown.vue` — color-coded by days remaining (green >30, amber 7-30, red <7, danger when ≤0).
- [x] Link to dashboard from Customer detail page (only when `customerType === 'tenant'`).
- [x] Subscription detail page rewritten with the same Renew/Upgrade/Downgrade modals (legacy SubscriptionConfirmed copy removed).

### 9.6 Testing
- [x] Pest: `QuotationWinTest` — atomic Lead→Customer conversion, CrmContact creation, draft Order auto-creation, idempotency on re-win, lose closes lead.
- [x] Pest: `OrderConfirmProvisioningTest` — provisioning fires only for tenant-type customer with software line; failure doesn't roll back the order; mocks `TenantProvisioningService`.
- [x] Pest: `SubscriptionLifecycleTest` — renew, upgrade (delta invoice), downgrade (credit invoice), cancel, expire transitions.
- [x] Pest: `StatusRemapMigrationTest` — verifies legacy → new value mapping on a seeded dataset.
- [x] Rewrote existing `HybridSalesFlowTest` for the new flow (subscriptions start active, no SubscriptionConfirmed event).

### 9.7 Docs sync
- [x] `rules/hybrid_sales_business_flow.md` describes target flow + status migration.
- [x] `skills/sales/{flow,rules,skill}.md` mark target sections Planned, current sections Shipped.
- [x] `skills/crm/{flow,rules,skill}.md` document B2B Product Schedule + handoff event.
