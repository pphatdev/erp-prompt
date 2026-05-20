# Feature Context: Sales & CRM (Backend)

Implementation phases for the Sales & CRM module, focusing on the revenue lifecycle and multi-tenant isolation.

## Implementation Phases (Backend Only)

### Phase 1: Data Architecture & Schemas
- [x] Create tenant-scoped migrations for `customers`, `leads`, `orders`, and `order_items`.
- [x] Implement models with `UUIDs`, `SoftDeletes`, `BelongsToTenant`, and `Auditable`.
- [x] Define complex relationships (e.g., Customer has many Orders, Order has many Items).

### Phase 2: CRM & Lead Management
- [x] Implement `CrmService` for customer and lead lifecycle management.
- [x] Create `CustomerController` and `LeadController`.
- [x] Implement `CustomerResource` and `LeadResource`.
- [x] Apply `sales.crm.*` and `sales.leads.*` permission policies.

### Phase 3: Order-to-Cash (O2C) Engine
- [x] Implement `OrderService` for the core sales workflow.
- [x] Ensure atomic transactions for Order creation and Inventory interaction.
- [x] Implement `OrderController` with CRUD operations.
- [x] Create `OrderResource` including line items and totals.

### Phase 4: Invoicing & FMS Integration
- [ ] Implement `InvoiceService` for billing generation.
- [ ] Create `InvoiceController` and `InvoiceResource`.
- [ ] Integrate with Financial Management (FMS) for AR (Accounts Receivable) posting.
- [ ] Define `sales.orders.*` and `sales.invoices.*` permission policies.

### Phase 5: QA & Integration Testing
- [x] P0 Tenancy Isolation tests for Sales data.
- [x] P1 Business logic tests for Order-to-Cash workflow.
- [x] Audit log verification for order status changes.
