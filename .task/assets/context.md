# Feature Context: Fixed Asset Management (Backend)

Implementation phases for the Fixed Asset module, focusing on asset tracking and financial depreciation calculation.

## Implementation Phases (Backend Only)

### Phase 1: Asset Schema
- [ ] Create migrations for `assets` and `depreciation_logs`.
- [ ] Implement models with `BelongsToTenant` and `Auditable`.

### Phase 2: Asset Tracking & Lifecycle
- [ ] Implement `AssetService` for registering, assigning, and disposing of assets.
- [ ] Implement logic for QR code generation or unique tagging.

### Phase 3: Financial Depreciation Engine
- [ ] Implement `DepreciationService`.
- [ ] Support Straight-line and Declining balance depreciation methods.
- [ ] (Future) Integrate with FMS `AccountingService` to post automated journal entries for monthly depreciation.

### Phase 4: API & Access Control
- [ ] Create `AssetController` and `DepreciationController`.
- [ ] Implement `AssetResource` and `DepreciationLogResource`.
- [ ] Define `assets.tracking.*`, `assets.depreciation.*`, and `assets.disposal.*` permission policies.

### Phase 5: QA & Financial Accuracy Testing
- [ ] P0 Tenancy Isolation tests.
- [ ] P1 Mathematical accuracy tests for depreciation algorithms.
