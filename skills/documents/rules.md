# Document Management (CMS) Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `documents`
- **Actions**: `read`, `write`, `delete`, `manage`

### Feature Matrix — Admin Scope:
| Feature | Read | Write | Delete | Manage |
|---------|------|-------|--------|--------|
| `storage` | `documents.storage.read` | `documents.storage.write` | `documents.storage.delete` | - |
| `versioning` | `documents.versioning.read`| - | - | `documents.versioning.manage`|
| `workflows` | `documents.workflows.read` | `documents.workflows.write` | `documents.workflows.delete` | - |

### Feature Matrix — `.self` Scope (User Self-Service):
Users can access or upload their own drafts or private documents:
- `documents.storage.read.self` — Read own uploaded documents.
- `documents.storage.write.self` — Edit or upload new version of own documents.

---

## 2. Implementation Standards

### Versioning & Concurrency Flow
1. **Checkout**: User locks the document (sets `locked_by_id = auth_user_id` and stamps `locked_at`). Prevents concurrent editing by other users.
2. **Update**: User uploads new content. Server validates size, extension, and magic bytes.
3. **Check-in**: System appends a new traceable version in `document_versions` (auto-increments `version_number` and stores encrypted physical file), then unlocks the document (`locked_by_id = null`, `locked_at = null`).
4. **Traceability**: Record old/new metadata, actor ID, and exact timestamp in the `audit_logs` trail using the `Auditable` trait.
5. **Retrieval**: Support historical version browsing, restoring past versions, and secure download links.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\Documents`
- **Model Invariants (P0)**:
  - All models must reside flat under `App\Models\Tenant\` (e.g. `CmsDocument`, `CmsDocumentVersion`, `CmsFolder`) and utilize `BelongsToTenant`, `SoftDeletes`, and `Auditable` traits.
  - Concurrency checks must reject checkout or checkin attempts if another user already holds the active lock, returning a standard `409 Conflict` or specific validation exception.
  - Multi-table mutations (e.g. `createDocument` or `checkin` with file version addition) must be atomic and wrapped in a `DB::transaction()`.
- **API Security & Policies**:
  - Always enforce authorization inside controllers via Policies (`CmsDocumentPolicy`, `CmsFolderPolicy`) using standard permission keys.
- **Resource JSON Format (P0)**:
  - Keys returned from resources (`CmsDocumentResource`, `CmsDocumentVersionResource`, `CmsFolderResource`) **MUST** use camelCase (e.g. `cmsFolderId`, `lockedBy`, `lockedAt`, `versionNumber`, `sizeBytes`, `changeSummary`, `uploadedBy`, etc.). Never return raw snake_case database columns directly.
  - Return JsonResource instances directly from controllers to preserve the validation/missing value pipeline.
- **Pagination**:
  - Index/list endpoints must return the standard pagination envelope: `{ data: [...], pagination: { page, limit, total, totalPages } }`.

### Frontend (Nuxt/PrimeVue)
- **Directory Path Structure**:
  - Pages organize strictly by URL: `frontend/pages/documents/index.vue`, `frontend/pages/documents/folders.vue`, and `frontend/pages/documents/history.vue`. Nested per-module assets inside a `src/modules/` folder are forbidden.
  - Composables, stores, and components must be flat: `frontend/composables/useCmsDocuments.ts`, `frontend/stores/documents.ts`, `frontend/components/DocumentExplorer.vue`.
- **Premium UI & Interactive Previews**:
  - Dynamic file explorer view with integrated PDF/Image previewers within the dashboard.
  - Implement responsive drag-and-drop file uploaders with real-time uploading progress animations.
  - Use custom card styles with standard design variables (e.g., `.glass-card`, `--color-primary-rgb`).
- **Standard Layout Mechanics (P2)**:
  - **Confirmations**: No native browser `confirm()` or `alert()`. Irreversible updates, locks, or version purges must use `useToast().confirm()`.
  - **Date Formatting**: Every date or datetime render must use formatting helpers `formatDate` or `formatDateTime` from `~/composables/useDateFormat.ts`.
  - **Table Row Actions**: List tables with $\ge 2$ row actions must use the standard 30x30 kebab trigger (`ti-dots-vertical`) with fixed dropdown positioning and outside-click dismissal.
- **API Fetching**:
  - Always route through `useApi()`. Direct `$fetch` or `useFetch` are prohibited because they bypass tenant-scoping context headers (`X-Tenant-Handle`).

---

## 3. Storage & Retention (P0)
- **Tenancy Scoping**: Physical files must reside inside the tenant-isolated directory using `tenant_path()`. Never expose direct server paths to clients. Serve documents using short-lived signed URLs.
- **Retention Policies**: Automate the lifecycle of documents from active use to archival and deletion. Banned executable extensions (e.g. `.php`, `.py`, `.sh`, `.exe`, `.js`) and forbidden MIME types must be rejected on upload via server-side verification using fileinfo magic bytes.

