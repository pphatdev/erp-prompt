# Main Project Context

This document outlines the overarching phased implementation strategy for the Enterprise ERP system features. Each module follows a standardized 6-phase progression to ensure multi-tenant security, architectural integrity, and premium UI/UX.

## Core Implementation Phases

### Phase 1: Foundation & Data Schema (Backend)
- **Database Architecture**: Create tenant-scoped migrations in `database/migrations/tenant`.
- **Model Definition**: Implement models with `UUIDs`, `SoftDeletes`, and the `BelongsToTenant` trait.
- **Relationships**: Define Eloquent relationships and constraints.
- **Auditing**: Apply `Auditable` trait to all key models.

### Phase 2: Business Logic & Service Layer (Backend)
- **Service Classes**: Implement domain logic in `app/Tenants/Modules/{Module}/Services`.
- **Atomic Operations**: Ensure all multi-table updates use DB Transactions.
- **Validation**: Define custom Request classes for strict input validation.
- **Exceptions**: Create domain-specific exceptions for business rule violations.

### Phase 3: API Layer & Security (Backend)
- **Controllers**: Implement thin controllers in `app/Tenants/Modules/{Module}/Controllers`.
- **Transformers**: Use `JsonResource` for all API responses.
- **Authorization**: Define Laravel Passport Policies for RBAC/PBAC.
- **Documentation**: Update Postman collections with all endpoints.

### Phase 4: Frontend Scaffolding & Logic (Frontend)
- **Composables**: Create `use{Module}` composables for data fetching with `X-Tenant-Handle`.
- **State Management**: Implement Pinia stores in `src/modules/{module}/store`.
- **Routing**: Define module-specific routes and middleware.
- **Types**: Mandatory TypeScript definitions for all props and state.

### Phase 5: UI/UX Implementation (Frontend)
- **PrimeVue Integration**: Build interfaces using PrimeVue components and `pt` (Pass Through) styling.
- **Form Handling**: Implement VeeValidate/Vuelidate for complex forms.
- **Design Tokens**: Apply custom CSS variables for tenant branding and dark mode.
- **Micro-animations**: Add transitions for premium feel.

### Phase 6: Quality Assurance & Audit (Full-Stack)
- **Tenancy Tests**: P0 Pest tests for data isolation (Tenant A vs Tenant B).
- **Logic Tests**: P1 tests for business rules and edge cases.
- **Audit Verification**: Assert that actions generate correct `audit_logs`.
- **Performance**: Benchmark critical endpoints and UI rendering.

---

## Module Priority Roadmap

| Sequence | Module | Priority | Status | Context Link |
| :--- | :--- | :--- | :--- | :--- |
| 1 | **IAM (Identity & Access Management)** | P0 (Critical) | Complete | [View Context](./iam/context.md) |
| 2 | **Sales (O2C & CRM)** | P1 (High) | Backend Complete | [View Context](./sales/context.md) |
| 3 | **FMS (Financial Management)** | P1 (High) | Backend Complete | [View Context](./fms/context.md) |
| 4 | **HRM (Workforce & Payroll)** | P1 (High) | Complete | [View Context](./hrm/context.md) |
| 5 | **eApprovals** | P1 (High) | Backend Complete | [View Context](./eapprovals/context.md) |
| 6 | **Inventory Management** | P2 (Medium) | Backend & UI Complete | [View Context](./inventory/context.md) |
| 7 | **eDocuments (Policy Explorer)** | P2 (Medium) | Backend Complete | [View Context](./edocuments/context.md) |
| 8 | **Assets (Fixed Asset Management)** | P2 (Medium) | Backend Complete | [View Context](./assets/context.md) |
| 9 | **Fleet Management** | P3 (Low) | Backend Complete | [View Context](./fleet/context.md) |
| 10 | **Project Management** | P3 (Low) | Backend & UI Complete | [View Context](./projects/context.md) |
| 11 | **Document Management** | P3 (Low) | Backend Complete | [View Context](./documents/context.md) |
| 12 | **Reporting & Analytics** | P3 (Low) | Backend & UI Complete | [View Context](./reporting/context.md) |

---

## Documentation Standards
Every module implementation MUST maintain and update the following in its `skills/{module}/` directory:
- `overview.md`: High-level purpose and stakeholders.
- `rules.md`: Technical constraints and business logic.
- `flow.md`: Mermaid diagrams for key workflows.
- `testing.md`: QA strategy and test cases.
- `skill.md`: Metadata for the Agent system.
