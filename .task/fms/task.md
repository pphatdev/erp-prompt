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

## Phase 1 — Customer Receipts (Shipped under "Receipts" terminology)
Original spec used "Payments" naming; shipped as **Receipts** to match accountant convention
(customer money-in = AR; supplier money-out = `BillPayment`, separate AP cycle). Functionally
identical to the Phase 1 contract.

- [x] Migration + `receipts` + `receipt_invoice_applications` tables shipped via the AR-cycle migrations.
- [x] `Receipt` model + `ReceiptInvoiceApplication` model (`BelongsToTenant`, `Auditable`, `SoftDeletes`).
- [x] `ReceiptService::record(array $data)` — validates bank GL link + AR account type + invoice ownership + applied-sum equals header amount; posts balanced `DR Bank / CR AR` journal via `AccountingService::postEntry`; bumps `invoices.paid_amount`; flips invoice to `paid` when fully applied; rejects over-application.
- [x] `ReceiptService::cancel(Receipt)` — reverses the journal entry via `AccountingService::reverseEntry`; rolls back `paid_amount` on each applied invoice; restores `paid->confirmed` status.
- [x] `ReceiptController` — index (filters: search/status/customer/bank/date range) + store + show + cancel + `GET /receipts/open-invoices/{customer}` helper for the apply picker.
- [x] Routes: literal `/cancel` + `/open-invoices/{customer}` declared before `apiResource` to avoid parameter capture.
- [x] `fms.receipts.{read,write}` permissions + `ReceiptPolicy`.
- [x] `pages/finance/receipts/index.vue` — Shop-style toolbar (search + status segmented [all/posted/cancelled] + Record Receipt CTA), receipt cards with amount KPI + applied-invoices count + status chip + kebab cancel action. Record modal: pick customer, auto-load open invoices, auto-allocate amount oldest-first when user types the total, bank + AR-account pickers, post button gated on apps-sum == amount within 0.01. Sidebar entry under Finance gated on `fms.receipts.{read,write}`.
- [ ] Pest: `ReceiptApplicationTest` — partial / full / over-application rejection, balanced GL posting, cancel reverses journal + rolls back paid_amount (deferred from this turn).

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
