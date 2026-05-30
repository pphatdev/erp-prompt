# Feature: Accounting & General Ledger

## Overview
The core accounting engine of the multi-tenant ERP system, providing financial governance, transactional tracking, period closing, and multi-currency reporting. It represents the financial double-entry system of truth, ingesting entries from operational modules (Assets, Sales, HRM) and summarizing them for stakeholders.

---

## Core Components

### 1. Multi-Tenant Chart of Accounts (COA)
A hierarchical ledger structure unique to each tenant. It maps business operations to organized financial accounts.
* **Hierarchical Structure**: Standard tree modeling where parent accounts sum child account balances.
* **Categorized Accounts**: Classified strictly into assets, liabilities, equity, revenues, and expenses.
* **Integrity**: Enforces uniqueness of account codes per tenant to prevent confusion or collision.

### 2. Double-Entry General Ledger (GL)
The central repository for all financial transactions, designed with strict auditing and data safety standards.
* **Balanced Ledger Entries**: Every transaction balances total debits and total credits to 0 decimal variance.
* **Journalizing Operations**: Standardized templates for posting, capturing dates, descriptions, references, and lines.
* **Immutability Principle**: Posted general ledger items cannot be updated or deleted. Corrections must be handled through reverse journal entries.

### 3. Multi-Currency Operations
Built-in capabilities to record and report transactions in multiple currencies using dynamically updated exchange rates.
* **Active Exchange Rates**: Resolving current rates between base and quote currencies for international operations.
* **Rate Normalization**: Automatically converting foreign currency amounts to the tenant's base currency during posting.

### 4. Trial Balances & Period Closing
Period-end workflows that ensure books are balanced and finalized before generating statutory financial reports.
* **Fiscal Period Lock**: Preventing further ledger adjustments or posts once a period is closed.
* **Trial Balance Auditing**: Spotting out-of-balance accounts or anomalies before period closings.
