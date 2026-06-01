# Feature: Accounting & General Ledger

## Overview

Accounting is the **accountant's lens** across the entire multi-tenant ERP. Every business event in Sales, Inventory, HRM, and Assets eventually surfaces here as a GL impact. It owns the double-entry engine (Chart of Accounts, Journals, Ledger, Exchange Rates) and exposes operational cross-links into the modules that originate each transaction, so an accountant can stay in one mental model end-to-end.

> Cross-module ownership: Customers, Quotations, Sales Orders, Inventory items, Purchase Orders, Employees, Payroll runs, and Fixed Assets are owned and authored by their operational modules (Sales, Inventory, HRM, Assets). Accounting **links to** those pages and adds a journal/posting layer — it does not duplicate the master data.

---

## Module Taxonomy

The Accounting menu surface (top-level sidebar group `accounting`) groups every GL-affecting feature into eight families:

### 1. Bank
Bank account master and reconciliation surface. A specialized view of asset-type accounts representing physical cash and bank deposits.

### 2. Budget
Period-bound expected DR/CR per account, with live variance tracking against actual ledger postings.

### 3. Sales (AR cycle)
The customer-facing revenue cycle.
- **Customers** — operational master, owned by Sales (`/sales/customers`).
- **Quotation** — pre-binding pricing, owned by Sales (`/sales/quotations`).
- **Invoice** — AR obligation, owned by Sales (`/sales/invoices`), posts `DR AR, CR Revenue` on confirm.
- **Receipt Payment** — customer remittance, settles AR (`DR Cash, CR AR`).
- **Credit Note** — reduces a customer balance for returns/discounts (`DR Sales Returns, CR AR`).
- **Debit Note** — increases a customer balance for under-billing or chargebacks (`DR AR, CR Revenue`).

### 4. Disbursement (AP cycle)
The supplier-facing and employee-facing cash-out cycle.
- **Vendor** — supplier extended with AP fields (bank, payment method, default GL accounts). Lives on the existing `suppliers` table; `/inventory/suppliers?vendor_only=1` filters to the AP set.
- **Bill** — supplier invoice booked into AP (`DR Expense/Asset, CR AP`). Distinct from a PO: a PO is a commitment, a Bill is an obligation.
- **Pay Bill** — settles one or many Bills (`DR AP, CR Cash`).
- **Reimbursement** — pays an employee back for an out-of-pocket expense (`DR Expense, CR Cash`).
- **Cash Advance** — gives an employee cash up front for upcoming expenses (`DR Employee Advances Receivable, CR Cash`).
- **Advance Settlement** — reconciles a Cash Advance against actuals (`DR Expense, CR Employee Advances Receivable`; unused balance returned `DR Cash`).
- **Expense** — direct expense entry without a Bill, typically petty cash or company card (`DR Expense, CR Cash/Credit Card`).

### 5. Inventory (lens)
Inventory operations with their GL impact surfaced.
- **Create new item** — links to `/inventory/products`.
- **Purchase Order** — links to `/inventory/purchase-orders`. Receipt posts `DR Inventory, CR GR/IR Clearing` (offsets when the Bill is booked).
- **Cost of Purchase** — Weighted Average Costing (shipped, inline on PO receipt) updates the on-hand `cost` and posts the inventory value change.
- **Inventory Adjustment** — shrinkage, writedown, and revaluation postings (`DR/CR Inventory, CR/DR Adjustment Expense`).
- **Request Order** — Purchase Requisition (PR) workflow that precedes a PO; not yet implemented.

### 6. Employees (lens)
HRM data with its accounting impact.
- **Personal Administration** — links to `/hrm/employees`.
- **Payroll Accounting** — links to `/hrm/payroll/periods` and exposes the period-close journal (`DR Salary Expense, CR Salaries Payable / Tax Withheld / Cash`).

### 7. Journal
The raw double-entry posting surface — manual adjustments, accruals, reversals, and the audit-grade browser over every entry the system has ever posted.

### 8. Non-Current Asset
Fixed-asset lifecycle with GL postings.
- **Purchase Order** — capitalizable PO that lands on the Asset register (not raw inventory).
- **Register new Asset** — owned by the Assets module (`/assets`). Capitalization posts `DR Asset, CR AP/Cash`.
- **Depreciation and Amortization** — periodic posting `DR Depreciation Expense, CR Accumulated Depreciation`.
- **Asset Disposal** — retirement posting that derecognizes the asset and books gain/loss (`DR Cash + DR Accumulated Depr, CR Asset, ± Gain/Loss on Disposal`).

---

## Core Engine (Always-Owned by Accounting)

Regardless of the taxonomy above, four foundational components live entirely inside Accounting and are not cross-linked:

### A. Multi-Tenant Chart of Accounts (CoA)
Hierarchical ledger structure unique to each tenant. Tree-rolled balances. See [`skill.md`](./skill.md) for the `Account` model contract.

### B. Double-Entry General Ledger
`JournalEntry` (header) + `LedgerEntry` (lines). Balanced posting via `AccountingService::postEntry`. Immutable once posted; corrections flow through `AccountingService::reverseEntry` only.

### C. Multi-Currency Operations
`ExchangeRate` model with uppercase normalization and six-decimal precision. Converts quote-currency operational events to the tenant's base currency at posting time.

### D. Period-End Closing
Fiscal period status (`open`/`locked`) gates new postings by `entry_date`. Closing service rolls Revenue/Expense into Retained Earnings (planned — Phase 4).
