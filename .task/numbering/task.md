# Document Numbering Prefixes — Task Tracker

> Rule source: [`skills/configuration/numbering.md`](../../skills/configuration/numbering.md)
> Last synced: 2026-05-27

---

## ✅ Phase 1 — Core Infrastructure (DONE)

### Setting Storage
- [x] `tenant_settings` table exists with `key`, `value` (jsonb), `group`, `type`, `label`, `is_public`, `tenant_id`
- [x] Unique constraint on `(tenant_id, key)`
- [x] `SettingService` — `get()`, `set()`, `bulkSet()`, `ensureDefaults()`, per-request in-memory cache
- [x] `Setting` model — `value` cast changed from `'array'` to `'json'` (scalar types preserved on round-trip)
- [x] `settings.write` permission enforced in `UpdateSettingsRequest::authorize()`
- [x] `PUT /api/v1/settings` bulk update endpoint
- [x] `GET /api/v1/settings?group=numbering` group-filtered read endpoint

### All 7 Prefix Keys Registered in `SettingService::defaults()`
- [x] `numbering.employee_id_prefix` → default `TT-`
- [x] `numbering.candidate_code_prefix` → default `CAN-`
- [x] `numbering.quotation_prefix` → default `QT-`
- [x] `numbering.order_prefix` → default `SO-`
- [x] `numbering.invoice_prefix` → default `INV-`
- [x] `numbering.subscription_prefix` → default `SUB-`
- [x] `numbering.po_prefix` → default `PO-`
- [x] `'numbering'` added to `Setting::GROUPS` constant

---

## ✅ Phase 2 — Generator Implementations (DONE)

### Each generator reads prefix from `SettingService` with `empty()` fallback
- [x] `RecruitmentService::generateNextEmployeeId()` — reads `numbering.employee_id_prefix`, fallback `EMPLOYEE_ID_PREFIX . '-'`
- [x] `Application::generateCandidateCode()` — reads `numbering.candidate_code_prefix`, fallback `'CAN-'`
- [x] `QuotationService::generateQuoteNumber()` — reads `numbering.quotation_prefix`, fallback `'QT-'`
- [x] `OrderService::generateOrderNumber()` — reads `numbering.order_prefix`, fallback `'SO-'`
- [x] `InvoiceService::generateInvoiceNumber()` — reads `numbering.invoice_prefix`, fallback `'INV-'`
- [x] `SubscriptionService::generateInvoiceNumber()` — reads `numbering.invoice_prefix`, fallback `'INV-'`
- [x] `SubscriptionService::generateSubscriptionNumber()` — reads `numbering.subscription_prefix`, fallback `'SUB-'`
- [x] `ProcurementService::generatePoNumber()` — reads `numbering.po_prefix`, fallback `'PO-'`

### Unique Constraints (Final Collision Guard)
- [x] `employees.employee_id` — unique (non-partial, includes soft-deleted)
- [x] `applications.candidate_code` — unique
- [x] `quotations.quote_number` — unique
- [x] `orders.order_number` — unique
- [x] `invoices.invoice_number` — unique
- [x] `subscriptions.subscription_number` — unique
- [x] `purchase_orders.po_number` — unique

### Seeder Compliance
- [x] `TenantDatabaseSeeder` calls `ensureDefaults()` **before** creating the base employee record
- [x] Base employee `employee_id` generated via `generateNextEmployeeId()` — no hardcoded `'TT-0001'`

---

## ✅ Phase 3 — Frontend Settings UI (DONE)

- [x] Numbering tab exists in `pages/settings/index.vue`
- [x] All 7 prefix inputs present with `v-model="draft['numbering.xxx']"`
- [x] `maxlength="16"` on all inputs
- [x] Live preview shown for each: `{{ draft['numbering.xxx'] || 'DEFAULT-' }}EXAMPLE`
- [x] Section header includes separator hint: `"Include any separator (e.g. TT-)"`
- [x] Immutability callout present: `"Changes only affect new records; existing codes are not rewritten."`
- [x] `save()` bulk-sends only changed keys via `PUT /api/v1/settings`

---

## 🔲 Phase 4 — Robustness & Production Hardening (OPEN)

### 4.1 Collision Retry Handling for Sequential Generators
The sequential generators (Employee ID, Candidate Code) can race under concurrent creation. The unique constraint is the final guard, but callers should handle `23505` gracefully.

- [ ] `EmployeeService::createEmployee()` — wrap `Employee::create()` in a retry loop (max 3 attempts) catching `Illuminate\Database\UniqueConstraintViolationException` on `employee_id`
- [ ] `RecruitmentService::convertToEmployee()` — same retry on `Employee::create()`
- [ ] `Application` model `creating` event — `generateCandidateCode()` retry on `23505` for `candidate_code`
- [ ] Document the retry pattern in `skills/configuration/numbering.md §3.4`

### 4.2 Prefix Validation on Save
The `UpdateSettingsRequest` currently has no type-level validation for `numbering.*` values — any string (or null) passes. Add server-side rules to prevent degenerate inputs.

- [ ] Add conditional validation rule in `UpdateSettingsRequest::rules()`:
  - For keys matching `numbering.*_prefix`: `string|max:16|nullable|regex:/^[A-Za-z0-9\/\-_\.]*$/`
  - Reject values longer than 16 characters (mirrors frontend `maxlength="16"`)
  - Reject values containing whitespace or control characters
- [ ] Return a clear 422 message: `"Prefix must be 1–16 characters: letters, digits, dash, slash, underscore, or dot."`

### 4.3 Separator Convention Enforcement (Frontend UX)
The rule states: *"The separator is PART of the stored prefix."* Currently the UI only mentions it in a header paragraph — individual fields lack a per-field separator hint.

- [ ] Add per-field helper text below each input: `"Include separator — e.g. 'TT-' not 'TT'"`
- [ ] Add a format preview that detects if the user forgot a separator (no trailing `-`, `/`, `_`) and shows a soft warning: `⚠️ Your code will look like TT0001 — add a separator like TT-`

### 4.4 Prefix Change History / Audit Visibility
When a prefix is changed via `PUT /settings`, the `Auditable` trait on `Setting` logs old/new values. But there is no UI surface to see this history.

- [ ] Expose prefix change history in the Settings → Numbering tab (optional: collapsible "Change history" panel per prefix row)
- [ ] Alternatively, ensure the Audit Log page (`/audit`) shows `tenant_settings` mutations filtered by `group=numbering`

---

## 🔲 Phase 5 — Postman & API Documentation (OPEN)

> Per `AGENTS.md §7`, the Postman collection must be updated whenever a feature is added or changed.

- [ ] Add `GET /api/v1/settings?group=numbering` example to Postman collection
- [ ] Add `PUT /api/v1/settings` example for bulk prefix update:
  ```json
  {
    "settings": [
      { "key": "numbering.employee_id_prefix", "value": "EMP-" },
      { "key": "numbering.invoice_prefix", "value": "INV-" }
    ]
  }
  ```
- [ ] Add pre-request script to capture `tenant` header from environment variable
- [ ] Document expected `200` response with full refreshed settings catalogue
- [ ] Document `403` (missing `settings.write`) and `422` (invalid key format) error examples

---

## 🔲 Phase 6 — Testing (OPEN)

> Per `AGENTS.md §5`, P0 = tenancy isolation, P1 = business logic, P2 = UX/audit.

### P1 — Business Logic Tests (Pest)
- [ ] `test('employee id uses configured prefix')` — set `numbering.employee_id_prefix = 'EMP-'`, create employee, assert `employee_id` starts with `EMP-`
- [ ] `test('employee id falls back to TT- when prefix is empty')` — set prefix to `null`, assert fallback `TT-0000`
- [ ] `test('candidate code uses configured prefix')` — set `numbering.candidate_code_prefix = 'APP-'`, submit application, assert `candidate_code` starts with `APP-`
- [ ] `test('quotation number uses configured prefix')` — set `numbering.quotation_prefix = 'Q-'`, create quotation, assert `quote_number` starts with `Q-`
- [ ] `test('invoice number uses configured prefix')` — covers both `InvoiceService` and `SubscriptionService`
- [ ] `test('po number uses configured prefix')` — set `numbering.po_prefix = 'PURCHASE-'`, create PO, assert `po_number` starts with `PURCHASE-`
- [ ] `test('existing employee id not changed when prefix changes')` — create employee with `TT-`, change prefix to `EMP-`, assert existing employee still has `TT-0000`
- [ ] `test('sequence continues from max under new prefix')` — change prefix, verify new IDs start from `0000`

### P0 — Tenancy Isolation Tests (Pest)
- [ ] `test('tenant A prefix change does not affect tenant B employee ids')` — two tenants, set different prefixes, assert each tenant generates codes with its own prefix
- [ ] `test('tenant A cannot read tenant B settings via GET /settings')` — assert cross-tenant setting access returns 404

### P2 — Audit Tests
- [ ] `test('changing a prefix creates an audit log entry')` — change `numbering.employee_id_prefix`, assert `audit_logs` row with old/new values

---

## 🔲 Phase 7 — Future: New Prefix-Bearing Features

When a new feature needs a code prefix, complete this checklist before shipping:

- [ ] Add setting key to `SettingService::defaults()` in `numbering` group
- [ ] Implement `generateXxxNumber()` in the feature's Service using `SettingService::get()` + `empty()` guard
- [ ] Add unique index on the generated code column in the migration
- [ ] Add input field to `pages/settings/index.vue` Numbering tab with preview, hint, and `maxlength="16"`
- [ ] Update `skills/configuration/numbering.md` §1 Setting Registry table
- [ ] Update `skills/configuration/numbering.md` §2 with feature spec
- [ ] Update `skills/configuration/numbering.md` §6 Quick Reference
- [ ] Update `.task/numbering/task.md` with the new feature
- [ ] Add Postman examples for the new code column
- [ ] Write P1 Pest tests

---

## Known Gaps / Technical Debt

| # | Gap | Severity | Status |
|---|---|---|---|
| G-1 | No retry on `23505` for sequential generators — concurrent creation can throw unhandled DB exception | P1 | Open |
| G-2 | `UpdateSettingsRequest` accepts any string for `numbering.*` — no max-length or charset enforcement at API layer | P1 | Open |
| G-3 | `EMPLOYEE_ID_PREFIX = 'TT'` constant in `RecruitmentService` is only used as fallback, but its name implies it's the source of truth — misleading to future devs | P2 | Open (rename to `EMPLOYEE_ID_FALLBACK_PREFIX`) |
| G-4 | Per-field "include separator" hint missing from individual prefix inputs (only in section header) | P2 | Open |
| G-5 | Prefix change history not surfaced in the Settings UI | P3 | Open |
| G-6 | Postman collection has no `numbering` group examples | P2 | Open |
