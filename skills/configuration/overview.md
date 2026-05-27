# Feature: Configuration & Tenant Settings

## Overview
The Configuration module is a dedicated administrative space allowing each tenant (customer company) to manage their organization's global settings, branding, and active modules. It is strictly limited to users with high-level Administration roles.

## Implementation Status

| Subsystem | Status | Notes |
|---|---|---|
| Key/value `tenant_settings` table | ✅ Shipped | Migration `2024_01_01_000033_create_tenant_settings_table.php`; jsonb `value` column; unique `(tenant_id, key)`. |
| `SettingService` (get/set/bulkSet, lazy defaults, in-memory cache) | ✅ Shipped | `app/Tenants/Modules/Settings/Services/SettingService.php` |
| `SettingController` (index, update, public) | ✅ Shipped | `GET/PUT /api/v1/settings`, `GET /api/v1/settings/public` (no auth) |
| Frontend tabbed settings page | ✅ Shipped | `pages/settings.vue` — Branding / Locale / Notifications / Security |
| Customizer ↔ backend sync | ✅ Shipped | `CustomizerOffcanvas.setAccent` PUTs `branding.primary_color` when authenticated |
| `tenantStore.syncBranding()` from `/settings/public` | ✅ Shipped | Runs on `app.vue` mount; user `localStorage.accent` override wins |
| Logo file upload (storage + signed URL) | ⏳ Planned | Current implementation stores only `branding.logo_url` string |
| Module toggle middleware (`CheckModuleEnabled`) | ⏳ Planned | Keys reserved as `modules.{slug}.enabled` |
| Redis cross-request cache | ⏳ Planned | Currently per-request in-memory only |
| **Numbering Prefixes** (`numbering.*` group) | ✅ Shipped | 7 configurable prefixes; see [`numbering.md`](./numbering.md) for full spec |

See `rules.md` for the storage contract and key conventions, `flow.md` for sequence diagrams, `numbering.md` for the prefix code system.

## 1. Core Modules

### Company Profile
- **Organization Details**: Legal company name, tax identification number (TIN), business registration number, and corporate address.
- **Contact Information**: Primary corporate email, support phone numbers, and website.

### Localization & Regional Settings
- **Time & Date**: Default timezone, preferred date/time formatting.
- **Financial**: Base currency configuration, default tax rates (VAT/GST).
- **Language**: Default system language for new employees.

### Branding & UI Customization
- **Visual Assets**: Uploading primary logos, favicons, and login screen backgrounds.
- **Design Tokens**: Configuring primary brand colors (CSS variables) to match the company's identity across the Nuxt 3 frontend.

### Module & Feature Management
- **Module Toggles**: Enabling or disabling specific ERP modules (e.g., Fleet Management, HRM, Inventory) based on the tenant's subscription or operational needs.
- **Workflow Settings**: Customizing global workflow statuses and eApproval hierarchies.

### Security & Compliance
- **Session Policies**: Configuring idle timeouts and MFA requirements.
- **Audit Retention**: Defining how long specific audit logs are retained before archival (subject to system hard limits).

### Document Numbering Prefixes
- **Per-module prefix codes**: Each auto-generated business code (Employee ID, Candidate, Quotation, Order, Invoice, Subscription, PO) reads its prefix from the `numbering.*` settings group.
- **Tenant-configurable**: Admins change prefixes via Settings → Numbering tab. Changes apply to new records only; existing codes are immutable.
- **Full specification**: See [`numbering.md`](./numbering.md) for setting keys, format patterns, sequence rules, and implementation checklist for adding new prefix-bearing features.
