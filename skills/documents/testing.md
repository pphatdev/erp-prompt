# Testing Strategy: Document Management

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Document folders, records, and files must be strictly isolated per tenant; requests across tenants must fail. |
| **P0** | **Concurrency** | Locked documents (checkout) must block checkout/checkin requests from other users with `409 Conflict` or standard exceptions. |
| **P1** | **API Contract** | Responses must use camelCase JSON keys, matching the standard pagination envelope. |
| **P1** | **Versioning** | File check-in must reliably generate a traceable new version row, preserving the previous file copy. |
| **P1** | **Audit Trail** | All mutations on folders, documents, and versions must write logs via the `Auditable` trait. |
| **P2** | **Validation** | Executive extensions (e.g. `.php`, `.py`) and dangerous MIME types must be rejected on upload. |

---

## 2. Backend Testing (Pest PHP)
All backend tests **MUST** execute against the dedicated test database `erp_system_test` (enforced by `phpunit.xml`). Never run tests on the development/production database (`erp_system`).

### A. Tenant Scoping & Isolation (P0)
- **Rule**: Every document-related model must use `BelongsToTenant`. Tenant A must never be able to access Tenant B's resources.
- **Pest Test Case**:
  ```php
  it('blocks tenant A user from viewing tenant B documents', function () {
      $tenantA = CentralTenant::factory()->create(['handle' => 'tenant-a']);
      $tenantB = CentralTenant::factory()->create(['handle' => 'tenant-b']);
      
      $documentB = CmsDocument::factory()->create(['tenant_id' => $tenantB->handle]);
      
      $userA = User::factory()->create(['tenant_id' => $tenantA->handle]);
      
      actingAs($userA)
          ->withHeader('X-Tenant-Handle', 'tenant-a')
          ->getJson("/api/v1/cms-documents/{$documentB->id}")
          ->assertStatus(403); // or 404 depending on routing resolver
  });
  ```

### B. Concurrency Locks (P0)
- **Rule**: Checking out a document locks it to that specific user ID. Other users cannot check in or check out the document.
- **Pest Test Case**:
  ```php
  it('blocks user B from checking in a document checked out by user A', function () {
      $userA = User::factory()->create();
      $userB = User::factory()->create();
      $document = CmsDocument::factory()->create(['locked_by_id' => $userA->id, 'locked_at' => now()]);
      
      actingAs($userB)
          ->withHeader('X-Tenant-Handle', $this->tenant->handle)
          ->postJson("/api/v1/cms-documents/{$document->id}/checkin", [
              'change_summary' => 'Hacked checkin'
          ])
          ->assertStatus(422); // or 409 depending on custom exception handling
  });
  ```

### C. CamelCase API Contract Verification (P1)
- **Rule**: API responses from `CmsDocumentResource`, `CmsDocumentVersionResource`, and `CmsFolderResource` must output keys in camelCase.
- **Pest Test Case**:
  ```php
  it('returns cms document data in camelCase format', function () {
      $document = CmsDocument::factory()->create();
      
      actingAs($this->adminUser)
          ->withHeader('X-Tenant-Handle', $this->tenant->handle)
          ->getJson("/api/v1/cms-documents/{$document->id}")
          ->assertJsonStructure([
              'data' => [
                  'id',
                  'title',
                  'cmsFolderId',
                  'lockedBy',
                  'lockedAt',
                  'retentionExpiry',
                  'latestVersion',
                  'versions',
              ]
          ]);
  });
  ```

### D. Audit Logging Assertions (P1)
- **Rule**: Modifying folders, documents, or versions must trigger `Auditable` trait captures.
- **Pest Test Case**: Assert that saving or locking a document creates an entry in the `audit_logs` table containing the user ID, event type (`created`, `updated`), and exact old/new key-value values.

### E. Attachment Validation (P2)
- **Rule**: Executable extensions and invalid MIMEs are rejected.
- **Test Case**: Attempt to upload a file named `shell.php` or a PHP file masked with an `image/png` MIME, and assert that the request throws an exception or returns a `422` error.

---

## 3. Postman Verification
- **Collection**: `docs/postman/erp_collection.json` under the **eDocuments** / **CMS** folder.
- **Headers**: Enforce `X-Tenant-Handle` and `Authorization: Bearer` on every mock request.
- **Mock Payload Guidelines**: Standardize on real-world PDF/Word attachment samples.

