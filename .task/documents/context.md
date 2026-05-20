# Feature Context: Document Management (CMS) (Backend)

Implementation phases for the Documents (CMS) module, focusing on version control, document locking, and workflows.

## Implementation Phases (Backend Only)

### Phase 1: CMS Schema
- [ ] Create migrations for `cms_folders`, `cms_documents`, and `cms_document_versions`.
- [ ] Implement models (`CmsFolder`, `CmsDocument`, `CmsDocumentVersion`) with `BelongsToTenant` and `Auditable`.

### Phase 2: Versioning & Storage Logic
- [ ] Implement `CmsDocumentService` for handling check-in/checkout (locking).
- [ ] Support uploading new versions and tracking history.
- [ ] Implement encryption at rest using Laravel's encryption features (simulated/abstracted in service).

### Phase 3: Workflows & Compliance
- [ ] Integrate with eApprovals module for document workflows.
- [ ] Implement retention policies tracking (e.g., expiry dates).

### Phase 4: API & Access Control
- [ ] Create `CmsFolderController` and `CmsDocumentController`.
- [ ] Implement `CmsDocumentResource` and `CmsDocumentVersionResource`.
- [ ] Define `documents.storage.*`, `documents.versioning.*`, and `documents.workflows.*` permission policies.

### Phase 5: QA & Concurrency Testing
- [ ] P0 Tenancy Isolation tests.
- [ ] P1 Concurrency tests (Assert that two users cannot checkout the same document simultaneously).
