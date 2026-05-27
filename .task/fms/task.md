# Task Checklist: FMS (Finance)

> See [`skills/fms/skill.md`](../../skills/fms/skill.md) for the canonical Finance scope. The sidebar "Finance" group includes mirrored Invoices/Subscriptions from Sales (code stays under `App\Tenants\Modules\Sales\*`) plus the FMS-owned ledger, Payments, and Estimates.

## Shipped
- [x] Chart of Accounts (`accounts` table + `Account` model + CRUD).
- [x] Journal Entries + Ledger (`journal_entries`, `ledger_entries`, `AccountingService::postEntry`).
- [x] AR posting on Invoice confirm (Sales-side `InvoiceService::confirm` → `AccountingService`).
- [x] Account-code resolution via `SettingService` (`fms.ar_account_code` / `fms.revenue_account_code` / `fms.tax_account_code`).

## Mirrored under Finance sidebar (code owned by Sales)
- [x] Invoices — routes `/sales/invoices`, `/api/v1/invoices`.
- [x] Subscriptions — routes `/sales/subscriptions`, `/api/v1/subscriptions`.

## Phase 1 — Payments (Planned)
Spec: [`skills/fms/skill.md`](../../skills/fms/skill.md) § 4.

- [ ] Migration `create_payments_table` — columns: `id`, `payment_number` (unique), `invoice_id` (FK), `customer_id` (FK), `amount` (decimal 14,2), `method` (`cash`/`bank_transfer`/`card`/`mobile_money`/`other`), `reference` (string nullable), `paid_at` (date), `journal_entry_id` (FK nullable, set on apply), `created_by`, `tenant_id`.
- [ ] `Payment` model (`BelongsToTenant`, `Auditable`, `SoftDeletes`).
- [ ] `PaymentService::record(array $data)` — creates the Payment row.
- [ ] `PaymentService::apply(Payment $payment, Invoice $invoice, float $amount)` — `DR Cash, CR AR` via `AccountingService::postEntry`; bumps `invoices.paid_amount`; marks invoice `status=paid` when fully applied; rejects with `DomainException` if total applied > invoice total.
- [ ] `PaymentController` + Resource — index, store, show, apply.
- [ ] Routes: `apiResource('payments')->only(['index','store','show'])` + `POST /payments/{payment}/apply`.
- [ ] `fms.payments.{read,write,delete}` permissions + `PaymentPolicy`.
- [ ] Add `fms.cash_account_code` setting (default `1000`).
- [ ] `pages/finance/payments/index.vue` + `[id].vue` (list + apply modal).
- [ ] Pest: `PaymentApplicationTest` — partial / full / over-application rejection, balanced GL posting.

## Phase 2 — Estimates (Planned)
Spec: [`skills/fms/skill.md`](../../skills/fms/skill.md) § 5.

- [ ] Migration `create_estimates_table` + `estimate_items_table` — mirrors `quotations` schema but with no FK to Lead/Opportunity. Status enum: `draft`/`sent`/`converted`/`expired`/`declined`.
- [ ] `Estimate` + `EstimateItem` models.
- [ ] `EstimateService::create / addItem / send / convertToQuotation / decline / expire`.
- [ ] Migration `add_from_estimate_id_to_quotations` — nullable FK so a Quotation produced by Estimate conversion is auditable.
- [ ] `EstimateController` + Resource.
- [ ] Routes: `apiResource('estimates')` + status-transition POSTs.
- [ ] `fms.estimates.{read,write,delete}` permissions + `EstimatePolicy`.
- [ ] `pages/finance/estimates/index.vue` + `new.vue` + `[id].vue` (build, send, convert to Quotation).
- [ ] Pest: `EstimateConversionTest` — converts to Quotation atomically with snapshot, prevents double-conversion, validates status transitions.

## Phase 3 — Reporting tie-ins (Planned)
- [ ] AR aging report includes pending Payments.
- [ ] Estimate-to-Quotation conversion rate widget on the Sales dashboard.
- [ ] Payment method breakdown widget.

## Phase 4 — Exchange Rates (Shipped)
Default pair USD → KHR but model is generic (any 3-letter ISO base/quote).

- [x] Migration `000063_create_exchange_rates_table` — `base_currency`, `quote_currency`, `rate (18,6)`, `effective_date`, `source`, `notes`, `is_active`. Unique on `(tenant_id, base, quote, effective_date)`.
- [x] `ExchangeRate` model (BelongsToTenant, Auditable, SoftDeletes) — currencies uppercased on save.
- [x] `ExchangeRateService` — `create/update/archive` + `latest(base, quote, on?)` + `convert(amount, from, to, on?)` with reverse-pair fallback (1/rate) and a 'no rate' DomainException.
- [x] `ExchangeRateController` — index w/ pair+date filters, CRUD, `GET /exchange-rates/latest`, `GET /exchange-rates/convert?amount=&from=&to=&on=`.
- [x] `ExchangeRatePolicy` (`fms.exchange_rate.{read,write,delete}`) registered.
- [x] Permissions seeded in `TenantDatabaseSeeder`.
- [x] Routes registered (literal `/latest` + `/convert` declared before `apiResource` to avoid parameter capture).
- [x] `pages/finance/exchange-rates.vue` — current-rate KPI, live converter (debounced via watch + seq guard), filter bar with base/quote pickers, table + create/edit modal with COMMON_CURRENCIES preset.
- [x] Sidebar nav entry + `fms-exchange-rates` ModuleSeeder entry.
