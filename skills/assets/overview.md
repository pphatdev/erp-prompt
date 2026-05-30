# Feature: Fixed Asset Management

## Overview
The **Fixed Asset Management** module is an enterprise-grade component designed to monitor and manage the end-to-end lifecycle of high-value company assets. It spans from capitalization, initial acquisition, custodian assignment, and physical tracking to automated depreciation calculations, asset revaluation, and final disposal/decommissioning.

All asset operations are fully tenant-isolated at the database level and integrated seamlessly with the Financial Management System (FMS), Human Resource Management (HRM), and Fleet modules.

---

## 1. Core Capabilities & Asset Lifecycle

```
[ Acquisition ] ---> [ Capitalization & Tagging ] ---> [ Custodian & Location ]
                                                               |
                                                               v
[ Disposal / Sales ] <--- [ Annual Revaluation ] <--- [ Monthly Depreciation ]
```

### A. Asset Acquisition & Capitalization
- **Capitalization Thresholds**: Filter and automatically flag procurement items that exceed the tenant's capitalization limit (e.g., equipment above $1,000) for capitalization.
- **Granular Registering**: Log historical purchase costs, vendor references, tax codes, insurance policies, salvage values, and useful lifespans.
- **Physical Tagging**: Auto-generate unique asset tracking codes and QR/barcodes mapping to tenant-isolated profiles.

### B. Dynamic Custodian & Location Mapping
- **Custodian Tracking**: Log which department and specific employee currently holds custody of the asset.
- **Location Mapping**: Real-time asset location assignments (branches, warehouses, offices, or remote driver vehicles).
- **Custodian Handover Logs**: Full historical trail of custodianship changes, including digital confirmation sign-offs.

### C. Advanced Financial Management
- **Automated Depreciation Engine**:
  - **Straight-Line Method**: Allocates an equal amount of depreciation each month.
  - **Declining Balance (Single/Double)**: Accelerated depreciation matching asset utility peaks.
  - **Sum-of-the-Years'-Digits (SYD)**: Accelerated fraction-based depreciation.
- **Asset Revaluation**:
  - Support for revaluation surplus or loss adjustments based on verified professional appraisals.
  - Generates specialized revaluation reserves in the General Ledger (GL).
- **Disposal & Retirement**:
  - Handle scrapping, writing off, or selling assets.
  - Automatic calculation of **Net Book Value (NBV)** at disposal timestamp.
  - Compute capital gains or losses and post balancing entries directly to the ledger.

### D. Physical Verification & Auditing
- **QR Code Scanning**: Seamless mobile or web-camera QR scanner interface allowing fast field audits.
- **Verification Cycles**: Annual or bi-annual stock-taking events comparing ledger inventories to scanned results.
- **Audit Reconciliation**: Real-time flagging of "missing," "moved," or "damaged" assets with reconciliation logs.

---

## 2. Decoupled Multi-Tenant Architecture

To ensure strict tenant isolation (P0 compliance), the Fixed Assets module operates under the following structural guidelines:
- **Physical DB Isolation**: All asset records reside in tenant-specific database connections initialized at runtime by the `InitializeTenancyByHandle` middleware.
- **Identifier Prefixing**: QR and barcode URLs map to the tenant's specific subdomain route (e.g., `https://{handle}.erp.domain/assets/verify/{uuid}`).
- **Handovers across Tenants**: Forbidden. Assets, custodians, and locations must strictly share the identical `tenant_id` context.

---

## 3. Cross-Module Integrations Matrix

The Fixed Assets module acts as a bridge across several enterprise business layers:

| Target Module | Integration Type | Mechanism | Business Value |
| :--- | :--- | :--- | :--- |
| **FMS** (Financials) | Ledger Postings | Automated Journal Entries (via `FmsIntegrationService`) | Automatically records depreciation, revaluation gains/losses, asset capitalization, and disposal journal entries. |
| **HRM** (Human Resources) | Custodian Mapping | Employee Entity Linkage (`employee_id` reference) | Ensures assets are returned during employee offboarding and tracks exact custody histories. |
| **Fleet** (Vehicles) | Dual Capitalization | Polymorphic Association (`Vehicle` as an `Asset`) | Links physical company vehicles to the asset ledger for combined maintenance tracking and tax depreciation. |
| **Procurement** | Bill to Asset | Purchase Invoice Event Listening (`BillApproved` Event) | Auto-creates shell asset records from approved purchase bills, eliminating duplicate manual entry. |

---

> [!TIP]
> **Implementation Recommendation**: Always ensure that before performing a monthly depreciation run, the FMS ledger periods are open and active for the tenant. Paused or locked accounting periods will trigger depreciation posting exceptions.
