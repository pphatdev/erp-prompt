# Testing Strategy: Fixed Asset Management

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Assets, depreciation schedules, revaluation logs, and custodians must be strictly isolated per tenant; requests across tenants must fail. |
| **P0** | **Mathematical Accuracy** | Depreciation calculations (Straight-line, Declining balance) must be mathematically correct and enforce that NBV never falls below Salvage Value. |
| **P1** | **Accounting Integration** | Capitalization, monthly depreciation runs, and disposal events must synchronously post balanced journal entries to the FMS ledger. |
| **P1** | **API Contract** | Asset resource endpoints must respond with standard pagination envelopes and strictly camelCase JSON structures. |
| **P1** | **Audit Trail** | All asset mutations must create auditable log entries detailing the old and new states alongside actor IDs. |
| **P2** | **QR Scans & Tracking** | QR codes must generate valid, tenant-scoped verification URLs. |

---

## 2. Backend Testing (Pest PHP)

All backend tests **MUST** execute exclusively against the dedicated test database `erp_system_test` (enforced by `phpunit.xml`). Never run tests on the development/production database (`erp_system`).

### A. Tenant Scoping & Isolation (P0)
- **Rule**: Every Fixed Asset model must utilize `BelongsToTenant`. Tenant A must never be able to access, modify, or delete Tenant B's assets.
- **Pest Test Case**:
  ```php
  it('blocks tenant A user from viewing tenant B assets', function () {
      $tenantA = CentralTenant::factory()->create(['handle' => 'tenant-a']);
      $tenantB = CentralTenant::factory()->create(['handle' => 'tenant-b']);
      
      $assetB = Asset::factory()->create(['tenant_id' => $tenantB->handle]);
      $userA = User::factory()->create(['tenant_id' => $tenantA->handle]);
      
      actingAs($userA)
          ->withHeader('X-Tenant-Handle', 'tenant-a')
          ->getJson("/api/v1/assets/{$assetB->id}")
          ->assertStatus(403); // Or 404 depending on tenant resolver configuration
  });
  ```

### B. Mathematical Accuracy & Salvage Value Cap (P0)
- **Rule**: Depreciation must allocate correct fractions and cap the depreciation amount so that Net Book Value (NBV) never drops below `salvage_value`.
- **Pest Test Case**:
  ```php
  it('caps straight-line depreciation at the salvage value threshold', function () {
      // Asset cost: $1,000, Salvage: $200, Useful life: 10 months. Monthly depreciation = $80.
      // After 9 months, accumulated depreciation is $720, NBV is $280.
      // Next month's depreciation should be capped at $80 (NBV becomes $200), not exceeding it.
      $asset = Asset::factory()->create([
          'purchase_price' => 1000.00,
          'salvage_value' => 200.00,
          'useful_life_months' => 10,
          'accumulated_depreciation' => 750.00, // NBV is $250
          'depreciation_method' => 'straight-line'
      ]);

      $service = app(DepreciationService::class);
      $result = $service->calculateNextMonthlyDepreciation($asset);

      // Remaining depreciable amount is $250 - $200 = $50 (which is less than standard monthly $80)
      expect($result->amount)->toEqual(50.00);
  });
  ```

### C. CamelCase API Contract Verification (P1)
- **Rule**: API responses from `AssetResource` and `AssetDepreciationResource` must output keys in camelCase.
- **Pest Test Case**:
  ```php
  it('returns asset data in camelCase format', function () {
      $asset = Asset::factory()->create();
      
      actingAs($this->adminUser)
          ->withHeader('X-Tenant-Handle', $this->tenant->handle)
          ->getJson("/api/v1/assets/{$asset->id}")
          ->assertJsonStructure([
              'data' => [
                  'id',
                  'assetCode',
                  'purchasePrice',
                  'salvageValue',
                  'netBookValue',
                  'accumulatedDepreciation',
                  'custodianEmployeeId',
                  'createdAt'
              ]
          ]);
  });
  ```

### D. FMS Ledger Postings & Transaction Rollback (P1)
- **Rule**: Depreciation postings must synchronously trigger journal entry creation in FMS. If the journal fails, the asset depreciation state must rollback.
- **Pest Test Case**:
  ```php
  it('rolls back asset changes if FMS journal posting fails', function () {
      $asset = Asset::factory()->create([
          'purchase_price' => 1000.00,
          'salvage_value' => 0.00,
          'useful_life_months' => 10,
          'accumulated_depreciation' => 0.00
      ]);

      // Mock FMS service to throw an exception (e.g. locked period)
      this->mock(FmsIntegrationService::class)
          ->shouldReceive('postDepreciationJournal')
          ->andThrow(new \Exception('GL Period Locked'));

      $service = app(DepreciationService::class);

      expect(fn () => $service->runDepreciationForAsset($asset))
          ->toThrow(\Exception::class, 'GL Period Locked');

      // Assert database transaction rolled back asset's accumulated depreciation
      $asset->refresh();
      expect($asset->accumulated_depreciation)->toEqual(0.00);
  });
  ```

### E. Audit Log Captures (P1)
- **Rule**: Every create, update, revaluation, and disposal transaction must write detailed log records via the `Auditable` trait.
- **Pest Test Case**:
  ```php
  it('writes to audit_logs table on asset revaluation', function () {
      $asset = Asset::factory()->create(['purchase_price' => 1000.00]);

      $service = app(RevaluationService::class);
      $service->revalue($asset, 1200.00, 'Market appraisal');

      $this->assertDatabaseHas('audit_logs', [
          'auditable_type' => Asset::class,
          'auditable_id' => $asset->id,
          'event' => 'revalued'
      ]);
  });
  ```

---

## 3. Postman Verification
- **Collection**: `docs/postman/erp_collection.json` under the **Fixed Assets** folder.
- **Headers**: Enforce `X-Tenant-Handle: {{tenant_handle}}` and `Authorization: Bearer {{access_token}}` on every request.
- **Pre-request Scripts**: Dynamically capture and rotate `lastAssetId` and `activeCustodianId` variables to ensure sequential request chains.

---

## 4. Scheduling & Cron Checks (P1)
- **Rule**: `AssetDepreciationSchedulerJob` executes monthly checks.
- **Verification Plan**: Seed multiple active assets with varying depreciation schedules, execute the schedule checker job, and verify that balanced journal entries are successfully posted for all eligible assets in the tenant's ledger.
