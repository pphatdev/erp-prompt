# Task Checklist: Document Management (CMS)

Implementation tracking for the Document Management (CMS) system.

## Backend (Laravel)

### 1. Database & Models
- [x] Create core migrations for `cms_folders`, `cms_documents`, and `cms_document_versions` (completed in `2024_01_01_000015_create_cms_tables.php`)
- [x] Implement Eloquent models under `App\Models\Tenant\CmsFolder`, `CmsDocument`, and `CmsDocumentVersion`
- [x] Wire `BelongsToTenant` and `SoftDeletes` traits on CMS models
- [ ] Wire `Auditable` trait on CMS models to capture audit logging events

### 2. Service Layer & Controller Alignment
- [x] Scaffolding controllers `CmsFolderController` and `CmsDocumentController`
- [x] Core service operations `CmsDocumentService::createDocument`, `checkout`, and `checkin` with file version validation
- [ ] Refactor `CmsDocumentResource`, `CmsDocumentVersionResource`, and `CmsFolderResource` to output `camelCase` response keys
- [ ] Enforce standard `409 Conflict` concurrency responses on checkout lock collisions

### 3. Security & IAM Integration
- [ ] Create `CmsPermissionSeeder.php` containing admin permissions and self-service scopes
- [ ] Call `CmsPermissionSeeder` from `TenantDatabaseSeeder`
- [ ] Implement standard policies `CmsDocumentPolicy.php` and `CmsFolderPolicy.php`
- [ ] Register policies in `TenantServiceProvider.php` and authorize controller calls

---

## Frontend (Nuxt 3)

### 1. Composables & Stores
- [ ] Create flat API wrapper `frontend/composables/useCmsDocuments.ts` routing strictly through `useApi()`
- [ ] Create flat Pinia store `frontend/stores/documents.ts` for managing files explorer directories and directory search queries

### 2. Layouts & Pages
- [ ] Link `documents` sidebar menu item or folder view in layouts
- [ ] Create folder navigation layout `frontend/pages/documents/index.vue`
  - [ ] Add summary statistic KPI blocks (total folders, total files, locked files)
  - [ ] Render directory view via folder grid cards using CSS design tokens (`.glass-card`, `--color-primary-rgb`)
  - [ ] Collapse row actions into a 30x30 kebab dropdown (`ti-dots-vertical`)
  - [ ] Implement dynamic preview modal components (PDF, images) inside `onMounted` hook
  - [ ] Implement drag-and-drop file uploader with upload progress indicator
  - [ ] Implement edit, checkout, check-in drawer/modal forms
  - [ ] Implement delete/lock confirmations via `useToast().confirm()`

---

## QA & Testing

### 1. Backend Integration Tests (Pest)
- [ ] Create `tests/Feature/Tenant/Documents/CmsDocumentIsolationTest.php` to verify P0 tenancy isolation
- [ ] Create `tests/Feature/Tenant/Documents/CmsDocumentLockTest.php` verifying checkout concurrency locking
- [ ] Create `tests/Feature/Tenant/Documents/CmsResourceContractTest.php` asserting camelCase JSON schemas and pagination shape
- [ ] Create audit log tests asserting mutations register in `audit_logs`

### 2. API Documentation
- [ ] Add CMS Documents API endpoint schema requests and responses inside `docs/postman/erp_collection.json`
