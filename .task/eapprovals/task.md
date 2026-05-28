# eApprovals Feature Task Checklist

## 1. Setup & Foundations
- [x] Create `App\Tenants\Modules\Approvals` namespace and directory structure in Backend.
- [ ] Create `src/modules/approvals/` directory in Frontend.
- [x] Set up database migrations for Workflow Policies, Requests, and Actions (Audit Logs).

## 2. Backend Implementation (Laravel)
- [x] Implement IAM permissions (`approvals.requests.*`, `approvals.actions.*`, `approvals.workflows.*`).
- [x] Create State Machine for approval flows (Pending -> Approved/Rejected).
- [x] Implement atomic transitions and side-effects.
- [x] Implement multi-stage approval paths (dynamic rules & conditional logic).
- [x] Create Notifications integration (WebSocket and Email).
- [x] Ensure `Auditable` trait is used and all approval steps are logged.
- [x] Handle escalation and reminders for pending approvals.
- [x] Support delegation of approval authority.

## 3. Frontend Implementation (Nuxt/PrimeVue)
- [x] **Forms**: Implement dynamic form components that adapt to the requested type (e.g., Leave Request, Overtime Request, Expense).
- [x] **Requests**: Develop "My Requests" view for users to track status (Pending, Approved, Rejected, Sent Back) and view history.
- [x] **Approvals**: Develop Review Portal for managers (`/approvals`) to review pending requests and make decisions.
- [x] Create vertical timeline component for visualizing approval history.
- [x] Implement UI badge indicators for pending approvals count.
- [ ] Implement bulk actions to process multiple requests simultaneously.
- [x] Add comments functionality for Reject and Send Back actions.

## 4. Testing (Pest PHP & Vitest)
- [x] **P0**: Tenancy Isolation - Verify Tenant A cannot see Tenant B's pending requests.
- [x] **P0**: Authorization - Only designated approver can process request status.
- [x] **P1**: State Machine Integrity - Verify transitions (e.g., cannot approve rejected).
- [x] **P1**: API Contract - Ensure endpoints match `erp_collection.json`.
- [ ] **P2**: Notifications - Status changes must trigger real-time WebSocket alerts.
- [x] Postman Verification: Update `postman.json` with all endpoints and ensure comment field is included.
