# Feature Context: Fleet Management (Backend)

Implementation phases for the Fleet module, focusing on vehicle tracking, maintenance, and fuel management.

## Implementation Phases (Backend Only)

### Phase 1: Asset Schema
- [ ] Create migrations for `vehicles`, `maintenance_logs`, and `fuel_logs`.
- [ ] Implement models with `BelongsToTenant` and `Auditable`.

### Phase 2: Fleet Management Service
- [ ] Implement `VehicleService` for vehicle lifecycle and assignments.
- [ ] Implement logic for logging maintenance and fuel expenses.
- [ ] Create automated checks for maintenance thresholds based on mileage or date.

### Phase 3: API & Access Control
- [ ] Create `VehicleController`, `MaintenanceController`, and `FuelController`.
- [ ] Implement `VehicleResource`, `MaintenanceResource`, and `FuelResource`.
- [ ] Define `fleet.vehicles.*`, `fleet.maintenance.*`, and `fleet.fuel.*` permission policies.

### Phase 4: Integration
- [ ] (Future) Integrate with external Telematics via `TrackingService`.
- [ ] Connect maintenance/fuel costs to FMS (Financial Management System).

### Phase 5: QA & Testing
- [ ] P0 Tenancy Isolation tests.
- [ ] P1 Logic tests for maintenance alerts.
