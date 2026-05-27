---
name: financial-management
description: Accounting logic, general ledger entries, AR/AP, Invoices, Subscriptions, Payments, Estimates, and financial reporting.
---
# Financial Management (FMS / Finance)

Use this skill when implementing accounting logic, general ledger entries, tax compliance, or financial reporting. Accuracy and data integrity are paramount in this module.

## Module surface (sidebar)

```
Finance
├── Invoices         — mirrored from Sales (App\Tenants\Modules\Sales\Invoice). Routes: /sales/invoices, /api/v1/invoices
├── Subscriptions    — mirrored from Sales (App\Tenants\Modules\Sales\Subscription). Routes: /sales/subscriptions, /api/v1/subscriptions
├── Payments         — Planned. Customer-receipt entity tied to Invoices, posts CR AR / DR Cash.
└── Estimates        — Planned. Pre-binding rough pricing — separate from Sales Quotations.
```

> **Invoices** and **Subscriptions** are owned by the Sales module today; this skill surfaces them under Finance for the Finance team's workflow. The backend namespaces (`App\Tenants\Modules\Sales\*`) and routes are unchanged.

## Workflows
1. **Journal Entry Lifecycle**: Create, validate, and post double-entry transactions to the General Ledger.
2. **Invoice → Payment (Planned)**: Customer pays an Invoice → record `Payment` → post `DR Cash, CR AR` → close the Invoice line.
3. **Estimates → Quotation (Planned)**: A pre-binding `Estimate` can be promoted to a formal Sales `Quotation` once the customer engages — see § Estimates below.
4. **Monthly Closing**: Perform period-end adjustments, depreciation runs, and lock the fiscal period.
5. **Accounts Reconciliation**: Match internal records with external bank statements and sub-ledger totals.

## Guidelines

### 1. General Ledger (GL)
- **Immutable Entries**: Journal entries must never be modified or deleted. Use reversal entries for corrections.
- **Double-Entry**: Every transaction MUST have equal debits and credits.

### 2. Accounts Payable & Receivable (AP/AR)
- **Aging Reports**: Implement logic to track overdue payments and receivables automatically.
- **Bank Reconciliation**: Ensure bank statements can be imported and matched with internal records.

### 3. Tax Compliance
- **Multi-Jurisdiction**: Support dynamic tax calculation rules based on tenant configuration.
- **Reporting**: Generate VAT/GST reports that comply with local regulations.

### 4. Payments (Planned)

New entity `payments` — owned by FMS. One Payment row per customer remittance. Application logic:
- `POST /payments { invoice_id, amount, method, reference, paid_at }` — records the receipt; `PaymentService::record()` writes the row.
- `PaymentService::apply(Payment $payment, Invoice $invoice, float $amount)` — wraps `AccountingService::postEntry({DR Cash, CR AR})` and bumps `invoices.paid_amount`. Marks invoice `status=paid` when fully applied.
- Methods enum: `cash | bank_transfer | card | mobile_money | other`.
- Partial payments supported — `Invoice.paid_amount` accumulates; remaining = `total_amount - paid_amount`.

### 5. Estimates (Planned)

New entity `estimates` — owned by FMS. Pre-binding, informal pricing document distinct from Sales `Quotations`:
- No FK to Lead / Opportunity (Estimates can exist for cold prospects with no CRM record).
- Status flow: `draft → sent → converted | expired | declined`.
- `EstimateService::convertToQuotation(Estimate $estimate)` — snapshots the lines into a new Sales `Quotation` (status=`draft`) and links via `quotations.from_estimate_id`. The Sales rep then walks the Quotation through `win`/`lose`.
- Useful for: long-cycle B2B deals where Finance prices a ballpark, OR for B2C customers asking "how much would this cost roughly?" before formal qualification.

## Best Practices
- **Audit Trail**: Every financial movement must be linked to a source document (Invoice, Receipt, or Journal Voucher).
- **Currency Handling**: Use a dedicated Money library or high-precision decimals (DECIMAL(19,4)) for all financial values.
- **Security**: Finance operations require the highest level of RBAC (`fms.ledger.write`, planned `fms.payments.write`, planned `fms.estimates.write`).

## Troubleshooting
- **Unbalanced Ledger**: If debits don't match credits, check the `AccountingService` for unhandled edge cases in transaction splits.
- **Missing Transactions**: Ensure that `DB::transaction()` is used in all service methods that affect multiple ledger accounts.
- **Performance**: Analytical reports (Balance Sheet, P&L) should be optimized with materialized views or dedicated read-replicas.
- **Payment / Invoice mismatch (Planned)**: If `invoice.paid_amount > invoice.total_amount`, `PaymentService::apply()` rejects with `DomainException`. Issue a refund (planned) instead of over-applying.
