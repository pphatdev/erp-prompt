# Feature Context: eDocuments (Explorer) (Backend)

Implementation phases for the eDocuments module, focusing on a centralized, tenant-isolated document repository.

## Implementation Phases (Backend Only)

### Phase 1: Repository Schema
- [ ] Create migrations for `folders`, `documents`, and `document_tags`.
- [ ] Implement models with `BelongsToTenant` and `Auditable`.
- [ ] Support polymorphic relationships if documents are linked to other modules (e.g., a Sales Order PDF).

### Phase 2: Document Management Service
- [ ] Implement `DocumentService` for handling file uploads, moving files, and tracking storage usage.
- [ ] Ensure files are stored in tenant-isolated directories using `tenant_path()`.
- [ ] Implement logic for generating secure, expiring public links.

### Phase 3: Metadata & Search
- [ ] Implement tagging functionality for categorization.
- [ ] Create search endpoints supporting metadata and full-text filtering.

### Phase 4: API & Access Control
- [ ] Create `FolderController` and `DocumentController`.
- [ ] Implement `DocumentResource` and `FolderResource`.
- [ ] Define `edocs.policies.*` and `edocs.explorer.*` permission policies.

### Phase 5: QA & Security Testing
- [ ] P0 Tenancy Isolation tests (Assert that Tenant A cannot access Tenant B's files).
- [ ] P1 Permission tests for read/write/share actions.
- [ ] P1 Link expiration tests.
