# Feature Context: Unified Calendar and Holiday Management

Detailed implementation phases for the Unified Calendar and Holiday Management module, covering the holiday registry, leaves aggregation, shift roster previews, compensatory days calculations, and daily attendance status overrides.

## Implementation Phases

### Phase 1: Database Schema and Tenant Models
- [ ] Create database migration for `holidays` and `calendar_events` tables.
- [ ] Establish primary key UUID boot triggers, SoftDeletes, and Auditable traits on all models.
- [ ] Import and verify multi-tenant connection scoping via `BelongsToTenant`.

### Phase 2: Company Holiday Registry and Overtime multipliers
- [ ] Implement `HolidayService` to handle holiday creation and checking.
- [ ] Build math validators resolving overtime pay rate multipliers on holiday logs (3.0x).
- [ ] Create weekend compensatory holiday generators scheduling paid days off on adjacent Monday dates.

### Phase 3: Consolidated Event Compilation
- [ ] Implement `CalendarEventService::getCombinedEvents()` querying holidays, leaves, shifts, and CRM schedules.
- [ ] Enforce date query validation (max 90 days range limits).
- [ ] Implement conditional model serialization and privacy masking hiding detailed sick leaves from unauthorized employees.

### Phase 4: Daily Reconciler Holiday Override
- [ ] Update `ReconcileAttendanceJob` to query active holidays.
- [ ] Ensure absent employees on holidays are marked with "holiday" status instead of "absent".
- [ ] Reconcile payroll standard monthly workdays counts to subtract recognized holidays during period close.

### Phase 5: Front-Counter Unified Dashboard (Nuxt 3)
- [ ] Design PrimeVue calendar dashboard page with monthly, weekly, and agenda view layouts.
- [ ] Integrate togglable checkbox layers showing and hiding schedule details in real-time.
- [ ] Build personal shift schedules and swap request modals.

### Phase 6: QA Testing and API Collections
- [ ] Create Pest integration test suites checking isolation, leave masking, and compensatory shifts.
- [ ] Configure Postman automated collections verifying holiday creation and unified queries.
