# Document Numbering Prefixes — Rule & Implementation Guide

> **Scope**: Every feature that auto-generates a business code (Employee ID, Candidate Code, Quotation, Sales Order, Invoice, Subscription, Purchase Order) MUST read its prefix from `SettingService` via the `numbering.*` group. Hardcoded prefixes are **never** acceptable in production code.

---

## 1. Setting Registry

All prefix settings live in the `numbering` group of the `tenant_settings` table. They are seeded by `SettingService::defaults()` on first read (idempotent). **Stored WITH separator** — e.g. `"TT-"`, not `"TT"` — so generators concatenate directly: `{prefix}{rest}`.

| Setting Key | Default Value | Label | Owner Feature |
|---|---|---|---|
| `numbering.employee_id_prefix` | `TT-` | Employee ID prefix | HRM → Employee |
| `numbering.candidate_code_prefix` | `CAN-` | Candidate code prefix | HRM → Recruitment |
| `numbering.quotation_prefix` | `QT-` | Quotation number prefix | Sales → Quotations |
| `numbering.order_prefix` | `SO-` | Sales order number prefix | Sales → Orders |
| `numbering.invoice_prefix` | `INV-` | Invoice number prefix | Sales → Invoices / Subscriptions |
| `numbering.subscription_prefix` | `SUB-` | Subscription number prefix | Sales → Subscriptions |
| `numbering.po_prefix` | `PO-` | Purchase order number prefix | Inventory → Procurement |

### Adding a New Prefix

1. Add a row to `SettingService::defaults()` in the `numbering` group.
2. Add the input field to `pages/settings/index.vue` under the `numbering` tab.
3. Read the value in the generator via `SettingService::get('numbering.{key}')` with an `empty()` fallback.
4. Document it in this file.

---

## 2. Feature-by-Feature Specification

### 2.1 Employee ID (`numbering.employee_id_prefix`)

| Property | Value |
|---|---|
| Setting key | `numbering.employee_id_prefix` |
| Default | `TT-` |
| Format | `{prefix}{NNNN}` — zero-padded, minimum 4 digits, grows naturally past `9999` |
| Sequence | Global per tenant — `MAX(numeric_suffix) + 1`; zero-indexed (`TT-0000` is first) |
| Includes trashed | ✅ Yes — terminated employees keep their IDs forever |
| Generator | `RecruitmentService::generateNextEmployeeId()` |
| Fallback constant | `RecruitmentService::EMPLOYEE_ID_PREFIX = 'TT'` (appended `-` when empty setting) |
| Unique constraint | `employees.employee_id` (non-partial — IDs are never reused after termination) |
| Callers | `RecruitmentService::convertToEmployee()`, `EmployeeService::createEmployee()`, `TenantDatabaseSeeder` |

**Rules:**
- The prefix includes its separator (e.g. `"TT-"`). The generator appends digits directly: `"TT-" + "0001"`.
- When the prefix changes, **existing records are not rewritten** — only new employees use the new prefix.
- **Never** call `Employee::create(['employee_id' => 'TT-0001', ...])` with a literal. Always call `generateNextEmployeeId()`.
- The regex pattern used to scan existing IDs is `^{preg_quote(prefix)}(\d+)$`. If the prefix changes, old records no longer match the pattern — they are treated as "not in sequence" and the new sequence starts from 0 under the new prefix. This is intentional and auditable.

```php
// ✅ Correct
$prefix = app(SettingService::class)->get('numbering.employee_id_prefix');
if (empty($prefix)) {
    $prefix = RecruitmentService::EMPLOYEE_ID_PREFIX . '-';
}

// ❌ Wrong — never hardcode
$employeeId = 'TT-0001';
```

---

### 2.2 Candidate Code (`numbering.candidate_code_prefix`)

| Property | Value |
|---|---|
| Setting key | `numbering.candidate_code_prefix` |
| Default | `CAN-` |
| Format | `{prefix}{YYYYMM}-{NNN}` — e.g. `CAN-202605-001` |
| Sequence | **Monthly** — resets each month. `001`-padded (3 digits minimum). |
| Includes trashed | ✅ Yes — withdrawn applications do not free their number |
| Generator | `Application::generateCandidateCode($reference = null)` (static, called from `creating` model event) |
| Unique constraint | `applications.candidate_code` |
| Callers | `Application` model `creating` event |

**Rules:**
- The monthly reset makes it easy to identify when a candidate applied from the code alone.
- `$reference` defaults to `now()`; pass `applied_at` when backfilling historical rows.
- The separator between prefix and month is part of the prefix string (e.g. `"CAN-"` → `"CAN-202605-001"`). If you set `"CAND-"`, the output becomes `"CAND-202605-001"`.
- Because generation is in a `creating` model event, it fires automatically on `Application::create()` — do not call the static method manually in service layers unless you are generating a preview.

```php
// Format: {prefix}{YYYYMM}-{NNN}
// prefix from setting: "CAN-"  →  "CAN-202605-001"
// prefix from setting: "CAND/" →  "CAND/202605-001"
```

---

### 2.3 Quotation Number (`numbering.quotation_prefix`)

| Property | Value |
|---|---|
| Setting key | `numbering.quotation_prefix` |
| Default | `QT-` |
| Format | `{prefix}{YYYYMMDD}-{XXXXXX}` — e.g. `QT-20260527-A3B2C1` |
| Sequence | **Date + random 6-char alphanum** — no sequential counter; uniqueness relies on random component |
| Includes trashed | N/A |
| Generator | `QuotationService::generateQuoteNumber()` (private) |
| Unique constraint | `quotations.quote_number` (unique index) |
| Callers | `QuotationService::createQuotation()` |

**Rules:**
- The format intentionally uses randomness rather than a counter because quotations are frequently drafted, discarded, and never submitted — a gap-free sequence would leak volume to clients.
- The unique constraint on `quote_number` is the collision guard. The probability of a 36⁶ ≈ 2.2 billion space collision on the same date is negligible in practice but non-zero — callers may retry on a `23505` violation.

---

### 2.4 Sales Order Number (`numbering.order_prefix`)

| Property | Value |
|---|---|
| Setting key | `numbering.order_prefix` |
| Default | `SO-` |
| Format | `{prefix}{YYYYMMDD}-{XXXXXX}` — e.g. `SO-20260527-A3B2C1` |
| Sequence | Date + random 6-char alphanum |
| Generator | `OrderService::generateOrderNumber()` (private) |
| Callers | `OrderService::createOrder()` |

---

### 2.5 Invoice Number (`numbering.invoice_prefix`)

| Property | Value |
|---|---|
| Setting key | `numbering.invoice_prefix` |
| Default | `INV-` |
| Format | `{prefix}{YYYYMMDD}-{XXXXXX}` — e.g. `INV-20260527-A3B2C1` |
| Sequence | Date + random 6-char alphanum |
| Generator | `InvoiceService::generateInvoiceNumber()` (private) AND `SubscriptionService::generateInvoiceNumber()` (private) |
| Callers | `InvoiceService::confirmInvoice()`, `SubscriptionService::generateInvoice()` |

> **Note**: Both `InvoiceService` and `SubscriptionService` read from the same `numbering.invoice_prefix` key. Invoices generated from subscriptions share the same namespace as manually-raised invoices — they are all "invoices".

---

### 2.6 Subscription Number (`numbering.subscription_prefix`)

| Property | Value |
|---|---|
| Setting key | `numbering.subscription_prefix` |
| Default | `SUB-` |
| Format | `{prefix}{YYYYMMDD}-{XXXXXX}` — e.g. `SUB-20260527-A3B2C1` |
| Sequence | Date + random 6-char alphanum |
| Generator | `SubscriptionService::generateSubscriptionNumber()` (private) |
| Callers | `SubscriptionService::createSubscription()` |

---

### 2.7 Purchase Order Number (`numbering.po_prefix`)

| Property | Value |
|---|---|
| Setting key | `numbering.po_prefix` |
| Default | `PO-` |
| Format | `{prefix}{YYYYMMDD}-{XXXXXX}` — e.g. `PO-20260527-A3B2C1` |
| Sequence | Date + random 6-char alphanum |
| Generator | `ProcurementService::generatePoNumber()` (private) |
| Callers | `ProcurementService::createPo()` |

---

## 3. The Setting Contract (Rules ALL Features Must Follow)

### 3.1 Always Read from `SettingService`, Never from Constants

```php
// ✅ CORRECT pattern — every generator must follow this
private function generateXxxNumber(): string
{
    $prefix = app(SettingService::class)->get('numbering.xxx_prefix');
    if (empty($prefix)) {          // ← MUST use empty(), not ?? or null-check
        $prefix = 'DEFAULT-';      // ← fallback is the last resort only
    }
    // ... rest of generation
}
```

**Why `empty()` and not `?? 'DEFAULT-'`?**
The `Setting` model stores `value` as JSON (`'json'` cast). A valid saved value of `""` (empty string) should fall through to the fallback — `empty('')` returns `true`, while `'' ?? 'fallback'` returns `''` (incorrect).

### 3.2 Prefix Includes the Separator

The separator (dash `-`, slash `/`, underscore `_`, or any custom character) is **part of the prefix string stored in the setting**. The generator concatenates directly:

```
stored value: "TT-"    →   generated: "TT-0001"     ✅
stored value: "TT"     →   generated: "TT0001"       ⚠️ missing separator
stored value: "EMP/"   →   generated: "EMP/0001"     ✅ valid custom separator
```

The frontend settings UI MUST include a hint like `"Include separator (e.g. TT-)"` so users understand the convention.

### 3.3 Changing a Prefix Does Not Rewrite Existing Records

**This is correct behavior, not a bug.** Business codes are permanent identifiers — they appear on contracts, invoices, and external systems. The system enforces this:

- **Sequential generators** (Employee ID, Candidate Code): The sequence-scan regex is anchored to the current prefix. Old records with a different prefix are **outside the scan window** — the new sequence starts from `0` or `001`. Both old (`TT-0001`) and new (`TT2-0000`) records coexist in the table.
- **Random generators** (all others): Already stateless per-record — changing the prefix only affects the next created document.

**UI obligation**: The settings page MUST display a callout: `"Changes only affect new records; existing codes are not rewritten."` (already implemented in `pages/settings/index.vue`).

### 3.4 Unique Constraints Are the Final Collision Guard

Every numbered field carries a unique DB constraint. Concurrent creation under the same prefix is safe:

| Feature | Unique Column | Constraint Type |
|---|---|---|
| Employee | `employees.employee_id` | Non-partial (includes soft-deleted) |
| Candidate | `applications.candidate_code` | Standard unique |
| Quotation | `quotations.quote_number` | Standard unique |
| Order | `orders.order_number` | Standard unique |
| Invoice | `invoices.invoice_number` | Standard unique |
| Subscription | `subscriptions.subscription_number` | Standard unique |
| Purchase Order | `purchase_orders.po_number` | Standard unique |

Callers running inside a DB transaction should be prepared to retry on a PostgreSQL `23505` violation for the sequential generators (Employee, Candidate). Random generators have an astronomically small collision window but should also handle `23505` gracefully.

### 3.5 Do Not Cache Prefix Values Across Requests

`SettingService` maintains a **per-request** in-memory cache (a single PHP object lifetime). Do not store prefix values in static properties or application-level caches — this would prevent changes from taking effect without a server restart.

```php
// ❌ WRONG — static cache survives across requests in long-running processes
private static ?string $cachedPrefix = null;
private function getPrefix(): string
{
    return self::$cachedPrefix ??= app(SettingService::class)->get('numbering.employee_id_prefix');
}

// ✅ CORRECT — call SettingService fresh each generation (it's already per-request cached)
private function getPrefix(): string
{
    return app(SettingService::class)->get('numbering.employee_id_prefix') ?: 'TT-';
}
```

---

## 4. Backend Implementation Checklist

When adding a new prefix-bearing feature:

- [ ] Add setting key to `SettingService::defaults()` in the `numbering` group with a sensible default
- [ ] Add to the `Setting::GROUPS` constant (`'numbering'` is already registered)
- [ ] Implement `generateXxxNumber()` in the feature's Service, reading from `SettingService`
- [ ] Guard with `if (empty($prefix)) { $prefix = 'DEFAULT-'; }`
- [ ] Add a unique index on the generated code column in the migration
- [ ] Handle `23505` at the caller level if running a sequential generator inside a transaction
- [ ] Never call `Model::create(['code' => 'HARDCODED-VALUE', ...])` — always generate
- [ ] Ensure `TenantDatabaseSeeder` calls `ensureDefaults()` **before** any seeded records that carry the new code

---

## 5. Frontend Implementation Checklist

For `pages/settings/index.vue` (Numbering tab):

- [ ] Add `<input v-model="draft['numbering.xxx_prefix']" />` for every new key
- [ ] Show a live preview: `{{ draft['numbering.xxx_prefix'] || 'DEFAULT-' }}EXAMPLE`
- [ ] Include the hint: `"Changes only affect new records; existing codes are not rewritten."`
- [ ] Max length: `maxlength="16"` — prevents absurdly long prefixes that could break DB column widths
- [ ] The `save()` function already handles bulk-saving all `draft` changes — no extra wiring needed

---

## 6. Sequence Formats — Quick Reference

| Feature | Format Pattern | Example |
|---|---|---|
| Employee ID | `{prefix}{NNNN}` | `TT-0001`, `TT-10000` |
| Candidate Code | `{prefix}{YYYYMM}-{NNN}` | `CAN-202605-001` |
| Quotation | `{prefix}{YYYYMMDD}-{RAND6}` | `QT-20260527-A3B2C1` |
| Sales Order | `{prefix}{YYYYMMDD}-{RAND6}` | `SO-20260527-A3B2C1` |
| Invoice | `{prefix}{YYYYMMDD}-{RAND6}` | `INV-20260527-A3B2C1` |
| Subscription | `{prefix}{YYYYMMDD}-{RAND6}` | `SUB-20260527-A3B2C1` |
| Purchase Order | `{prefix}{YYYYMMDD}-{RAND6}` | `PO-20260527-A3B2C1` |

`{RAND6}` = 6-character uppercase alphanumeric random string (`Str::random(6)`).
`{NNNN}` = zero-padded integer, minimum 4 digits, no upper cap.
`{NNN}` = zero-padded integer, minimum 3 digits, monthly reset.

---

## 7. Storage Details

- **Table**: `tenant_settings`
- **Column**: `value` — stored as JSON, cast via Eloquent `'json'` cast (not `'array'` — `json` preserves scalar types on round-trip; `array` forces associative-array decode)
- **Key uniqueness**: `(tenant_id, key)` — one row per setting per tenant
- **Group**: `numbering` — all prefix settings share this group for bulk retrieval via `GET /api/v1/settings?group=numbering`
- **API**: `PUT /api/v1/settings` with `{ settings: [{ key: "numbering.employee_id_prefix", value: "EMP-" }] }` — requires `settings.write` permission

---

## 8. Related Files

| File | Role |
|---|---|
| [`app/Tenants/Modules/Settings/Services/SettingService.php`](../../backend/app/Tenants/Modules/Settings/Services/SettingService.php) | Authoritative read/write; `defaults()` declares all prefix rows |
| [`app/Models/Tenant/Setting.php`](../../backend/app/Models/Tenant/Setting.php) | Eloquent model; `value` cast is `'json'` |
| [`app/Tenants/Modules/HRM/Services/RecruitmentService.php`](../../backend/app/Tenants/Modules/HRM/Services/RecruitmentService.php) | `generateNextEmployeeId()` |
| [`app/Models/Tenant/Application.php`](../../backend/app/Models/Tenant/Application.php) | `generateCandidateCode()` — called from `creating` event |
| [`app/Tenants/Modules/Sales/Services/QuotationService.php`](../../backend/app/Tenants/Modules/Sales/Services/QuotationService.php) | `generateQuoteNumber()` |
| [`app/Tenants/Modules/Sales/Services/OrderService.php`](../../backend/app/Tenants/Modules/Sales/Services/OrderService.php) | `generateOrderNumber()` |
| [`app/Tenants/Modules/Sales/Services/InvoiceService.php`](../../backend/app/Tenants/Modules/Sales/Services/InvoiceService.php) | `generateInvoiceNumber()` |
| [`app/Tenants/Modules/Sales/Services/SubscriptionService.php`](../../backend/app/Tenants/Modules/Sales/Services/SubscriptionService.php) | `generateInvoiceNumber()` + `generateSubscriptionNumber()` |
| [`app/Tenants/Modules/Inventory/Services/ProcurementService.php`](../../backend/app/Tenants/Modules/Inventory/Services/ProcurementService.php) | `generatePoNumber()` |
| [`database/seeders/TenantDatabaseSeeder.php`](../../backend/database/seeders/TenantDatabaseSeeder.php) | Calls `ensureDefaults()` before seeding employee; uses `generateNextEmployeeId()` |
| [`frontend/pages/settings/index.vue`](../../frontend/pages/settings/index.vue) | Numbering tab UI — all prefix inputs |
