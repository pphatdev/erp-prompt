# Feature Context: Reporting & Analytics (Backend)

Implementation phases for the Reporting (BI) module, focusing on data aggregation, dashboards, and scheduled reports.

## Implementation Phases (Backend Only)

### Phase 1: BI Schema
- [ ] Create migrations for `dashboards`, `widgets`, and `scheduled_reports`.
- [ ] Implement models with `BelongsToTenant` and `Auditable`.

### Phase 2: Report Generation Engine
- [ ] Implement `ReportGeneratorService` for executing queries and formatting data (e.g., CSV, JSON).
- [ ] Implement logic to support caching of heavy analytical queries.
- [ ] (Future) Integrate with an Excel/PDF generator library.

### Phase 3: Dashboard Management
- [ ] Create API for saving and retrieving custom user dashboards and widgets.
- [ ] Ensure that data returned by widgets respects user permissions (e.g., Row-Level Security based on roles).

### Phase 4: API & Access Control
- [ ] Create `DashboardController`, `WidgetController`, and `ReportController`.
- [ ] Implement resources for returning structured dashboard layouts.
- [ ] Define `reporting.dashboards.*` and `reporting.reports.*` permission policies.

### Phase 5: QA & Performance Testing
- [ ] P0 Tenancy Isolation tests (Crucial for analytics to never leak cross-tenant data).
- [ ] P1 Query performance tests (Ensure heavy aggregations are indexed or cached).
