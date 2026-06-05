# Testing Strategy: Human Resource Management (HRM)

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Employee PII, salaries, and candidate quiz attempts must be strictly scoped to `tenant_id`. |
| **P0** | **Privacy & Security**| Magic-link tokens must be cryptographically secure; candidates must be strictly sandboxed from accessing any central, payroll, or other candidate data (403/404). |
| **P1** | **Calculations** | Payroll engine must correctly apply tax/deduction logic. Quiz auto-grader must correctly score multi-choice/short-answers. |
| **P1** | **API Contract** | Synchronized with `erp_collection.json`; correct answers must never be leaked in active quiz responses. |
| **P2** | **Integration** | Leave requests trigger `eApprovals` workflows; quiz submissions transition the application pipeline status. |

## 2. Backend Testing (Pest PHP)

### Privacy & Isolation (P0)
- **Rule**: Employees can only see their own payslips/details unless they have `hrm.employee.read`.
- **Test Case**: Login as standard employee and attempt to fetch another employee's record. Assert `403`.

### Payroll Logic
- **Rule**: Tax and deduction calculations must be accurate based on tenant rules.
- **Test Case**: Use a unit test for `PayrollService` with a set of mock salary data.

### Tenancy
- **Rule**: Employee records are tenant-scoped.
- **Test Case**: Verify `tenant_id` is automatically applied via the `BelongsToTenant` trait.

### Candidate Quizzing (P0 & P1)
- **Rule**: Candidates using magic-links can only access their allocated quiz questions and attempts. Correct answers are omitted from the quiz payload.
- **Test Case**: Assert candidate endpoint returns `403` when trying to request quiz correct answers or modifying scores directly. Verify completed quiz attempts are immutable.

## 2. Postman Verification
- **Collection**: `postman.json`
- **Tests**: Verify that sensitive fields (Salary, National ID) are omitted in the standard list response, and active quiz payloads omit correct answers.

## 3. Integration
- **Rule**: Leave requests must trigger an entry in `approvals` module.
- **Test Case**: `assertDatabaseHas('approval_requests', ['module' => 'hrm'])`.
- **Rule**: Quiz submissions must automatically transition candidate application status.
- **Test Case**: Assert the application status transitions to `assessment_completed` upon quiz submit.

## 4. Offer & Onboarding Pipeline (Phase 8 + 8.5)

The recruitment-to-onboarding chain splits its side-effects across two boundaries (offer acceptance and appointment-request approval). Tests must lock the split so the conversion does not silently slip back to offer-acceptance.

### Offer acceptance (no employee creation)
- **Rule**: `OfferService::createOffer` requires `application.status === 'offer'`. Drafting on any other status throws `DomainException`.
- **Test Case**: Seed an application at `interview`, attempt `createOffer`, assert exception + message.
- **Rule**: `OfferService::markAccepted` flips `offer.status` to `accepted`, stamps `signed_at`, and transitions the application from `offer` → `hired`. It does NOT create an Employee row or seed an onboarding checklist.
- **Test Cases**:
  - After accept: `assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'hired', 'employee_id' => null])`.
  - `assertDatabaseMissing('employees', ['email' => $app->applicant_email])`.
  - `assertDatabaseCount('onboarding_checklists', 0)`.
- **Rule**: `markAccepted` is idempotent.
- **Test Case**: Call `markAccepted` twice; assert only one application transition and no exception on the second call.

### Appointment-request approval (Employee + checklist materialise here)
- **Rule**: Approval of an `EmployeeAppointment` request runs `SyncEmployeeAppointmentFromApproval::handle`, which (inside one DB transaction): calls `RecruitmentService::convertToEmployee`, links `offers.employee_id`, seeds the default onboarding checklist, and transitions the application from `hired` → `onboarding`.
- **Test Cases**:
  - Approve the request → `assertDatabaseHas('employees', ['email' => $app->applicant_email, 'status' => 'active'])`.
  - `assertDatabaseHas('applications', ['id' => $app->id, 'status' => 'onboarding'])`.
  - `assertDatabaseHas('offers', ['id' => $offer->id, 'employee_id' => $employee->id])`.
  - `assertDatabaseCount('onboarding_checklists', 1)` + `assertDatabaseCount('onboarding_tasks', 11)`.
- **Rule**: A rejected approval leaves the application at `hired` and does NOT create an Employee.
- **Test Case**: Reject the request → assert appointment row status = `rejected`, application still `hired`, no Employee, no checklist.
- **Rule**: Listener failures during conversion are logged but do not 500 the approval-action HTTP response.
- **Test Case**: Stub `RecruitmentService::convertToEmployee` to throw; approve request; assert HTTP 200 from approval endpoint and `Log::warning` was called once.

### Workflow status registry
- **Rule**: `hrm.application` workflow_statuses includes `final_interview` and `onboarding`. `hired` is non-terminal with `allowed_next = ['onboarding']`. `onboarding` is the terminal-success row.
- **Test Case**: After `seedWorkflowStatuses`, query `workflow_statuses` for module `hrm.application` and assert these rows + flags.
