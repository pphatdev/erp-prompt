# Task Checklist: eDocuments (Explorer) — Backend

> Skill: [`skills/edocuments/skill.md`](../../skills/edocuments/skill.md) | Rules: [`skills/edocuments/rules.md`](../../skills/edocuments/rules.md) | Context: [`./context.md`](./context.md)

Scope confirmed 2026-05-29: Full backend pass (eDocuments only — CMS `documents/` is a separate effort tracked at [`.task/documents/task.md`](../documents/task.md)).

## Current State (audited 2026-05-29)

- [x] Migrations: `folders`, `documents`, `tags`, `document_tag` (`2024_01_01_000010_create_document_tables.php`)
- [x] Models: `Document`, `Folder`, `Tag` with `BelongsToTenant` + `Auditable` + `SoftDeletes` (Tag missing Auditable + SoftDeletes)
- [x] `DocumentService::uploadDocument` with banned extensions + MIME guards + path traversal sanitisation
- [x] `DocumentController` (index w/ search, store, show, download), `FolderController` (index, store, show)
- [x] Routes wired in `routes/tenant.php` (lines 297–300)

## Phase 1: Schema completion (P0/P1)

Migration filenames follow tenant convention `2024_01_01_000XXX_*.php` (highest existing is 068).

- [x] `2024_01_01_000069_create_document_shares_table.php` — `id` uuid, `document_id` fk, `token` (unique, 64-char), `expires_at`, `password_hash` nullable, `max_downloads` nullable, `downloads_count` default 0, `created_by` fk users, `tenant_id`, `timestamps`, `softDeletes`
- [x] `2024_01_01_000070_create_document_acknowledgements_table.php` — `id` uuid, `document_id` fk, `user_id` fk, `acknowledged_at` timestamp, `tenant_id`, `timestamps`; composite unique (`document_id`, `user_id`, `tenant_id`)
- [x] `2024_01_01_000071_create_document_versions_table.php` — `id` uuid, `document_id` fk, `version_number` int, `path`, `size_bytes`, `mime_type`, `filename`, `uploaded_by_id`, `change_summary` text nullable, `tenant_id`, `timestamps`; unique (`document_id`, `version_number`)
- [x] `2024_01_01_000072_add_softdeletes_to_tags_table.php` — add `deleted_at` column to existing `tags` table
- [x] Add `Auditable` + `SoftDeletes` traits to `App\Models\Tenant\Tag`
- [x] Add `DocumentShare`, `DocumentAcknowledgement`, `DocumentVersion` Eloquent models with relations + wire `versions()`/`shares()`/`acknowledgements()` on `Document`

## Phase 2: Service layer expansion (P1)

- [x] `DocumentService::updateDocument(Document, array)` — rename, retag, move folder, polymorphic re-link
- [x] `DocumentService::deleteDocument(Document)` — soft-delete + remove storage file (transactional)
- [x] `DocumentService::moveToFolder(Document, ?Folder)` — guard tenant scoping
- [x] `DocumentService::createVersion(Document, UploadedFile, ?string $summary)` — atomic write, MIME re-check, monotonic `version_number`
- [x] `FolderService` — `createFolder`, `renameFolder`, `moveFolder` (cycle guard), `deleteFolder` (block if non-empty unless `force=true`)
- [x] `ShareLinkService::createLink(Document, array)` — generate 64-char `Str::random` token, set `expires_at`, optional `password_hash` (bcrypt), optional `max_downloads`
- [x] `ShareLinkService::resolve(token, ?password)` — `410 Gone` on expired, `403` on bad password, `429` on download cap, increment `downloads_count` via `recordDownload()`
- [x] `AcknowledgementService::acknowledge(Document, User)` — idempotent insert (`firstOrCreate`)
- [x] `AcknowledgementService::summary(Document)` — `{ totalEligible, acknowledged, pending: [...] }`

## Phase 3: Search & metadata (P1)

- [x] `Tag` CRUD (`TagController` index/store/show/update/destroy) — wired at `/document-tags`
- [x] `DocumentController::index` filters: `folderId`, `tagIds[]`, `uploaderId`, `mimeType`, `from`, `to`, `polymorphicType`, `rootOnly`
- [x] Postgres ILIKE for `title`/`filename`; `tsvector` GIN deferred until ranking is needed
- [x] Pagination on `FolderController::index` (now uses `paginateQuery`)

## Phase 4: API surface & resources (P1)

- [x] Refactor `DocumentResource` → camelCase (`mimeType`, `sizeBytes`, `folderId`, `uploaderId`, `documentableType`, `documentableId`, `createdAt`, `updatedAt`, `versionsCount`)
- [x] Refactor `FolderResource` → camelCase (`parentId`, `childrenCount`, `documentsCount`, `createdAt`, `updatedAt`)
- [x] Add `TagResource`, `DocumentShareResource`, `DocumentVersionResource`, `DocumentAcknowledgementResource` (camelCase)
- [x] FormRequests: `StoreDocumentRequest`, `UpdateDocumentRequest`, `MoveDocumentRequest`, `StoreVersionRequest`, `StoreFolderRequest`, `UpdateFolderRequest`, `MoveFolderRequest`, `StoreTagRequest`, `UpdateTagRequest`, `CreateShareLinkRequest`
- [x] `DocumentController::update`, `destroy`, `move`, `acknowledge`, `acknowledgementSummary`, `versions`, `createVersion`
- [x] `FolderController::update`, `destroy`, `move`
- [x] `ShareController::index`, `store`, `destroy`, `publicShow`, `publicDownload`
- [x] Routes wired in `routes/tenant.php` (authenticated eDocs block + `/public/shares/{token}` + `/public/shares/{token}/download`)

## Phase 5: Access control (P0)

- [x] `EDocsPermissionSeeder` covering `edocs.policies.{read,write,delete,share}`, `edocs.explorer.{read,write,delete,share}`, `edocs.search.read`, `edocs.tags.{read,write,delete}`
- [x] Call `EDocsPermissionSeeder` from `TenantDatabaseSeeder`
- [x] `DocumentPolicy` — view/create/update/delete/share/acknowledge gates
- [x] `FolderPolicy` — view/create/update/delete gates (move reuses `update`)
- [x] `DocumentTagPolicy` — view/create/update/delete
- [x] Register policies in `TenantServiceProvider::boot()` via `Gate::policy()`
- [x] Apply `$this->authorize(...)` calls in all eDocuments controller actions

## Phase 6: QA (P0/P1)

- [ ] `tests/Feature/Tenant/EDocuments/DocumentIsolationTest.php` — P0: Tenant A cannot read/download/list Tenant B's documents (storage path + API + share token)
- [ ] `tests/Feature/Tenant/EDocuments/FolderIsolationTest.php` — P0: same for folders
- [ ] `tests/Feature/Tenant/EDocuments/ShareLinkExpiryTest.php` — P0: expired token returns `410 Gone`; bad password returns `403`; download cap returns `429`
- [ ] `tests/Feature/Tenant/EDocuments/UploadGuardsTest.php` — banned extensions/MIMEs rejected; path traversal sanitised
- [ ] `tests/Feature/Tenant/EDocuments/SearchScopingTest.php` — P1: search results never leak across tenants
- [ ] `tests/Feature/Tenant/EDocuments/AcknowledgementTest.php` — idempotent ack; summary counts correct
- [ ] `tests/Feature/Tenant/EDocuments/PermissionsTest.php` — P1: missing `edocs.*` slug → `403`
- [ ] `tests/Feature/Tenant/EDocuments/ResourceContractTest.php` — camelCase JSON + standard pagination envelope

## Frontend Explorer MVP (shipped 2026-05-29)

- [x] `composables/useEDocuments.ts` — typed wrapper over folders, documents, versions, shares, tags
- [x] `stores/edocuments.ts` — current folder + breadcrumb state (Pinia, flat)
- [x] `pages/edocuments/index.vue` — breadcrumb header, KPI cards, search, sub-folder grid, document table with kebab actions (preview / download / rename / share / delete), inline modals (new folder, upload with drag-drop, rename, share-link, preview)
- [x] `pages/share/[token].vue` — no-layout public viewer for share recipients (handles 410 Gone, 403 password, 429 cap)
- [x] Sidebar entry at `layouts/default.vue:683` flipped to `route: '/edocuments'`, `operational: true`, `permission: 'edocs.explorer.read'`

## Out of scope (deferred / explicitly excluded)

- Acknowledgement UI (per Explorer MVP scope answer)
- Version history viewer (per Explorer MVP scope answer)
- Advanced filter sidebar (tags / mime / uploader / date — backend exposes them; UI deferred)
- Elasticsearch / full-text index beyond Postgres ILIKE + GIN-on-tsvector (defer ranking)
- Inline preview rendering (frontend concern)
- CMS (`documents/`) module — tracked separately at [`.task/documents/task.md`](../documents/task.md)
