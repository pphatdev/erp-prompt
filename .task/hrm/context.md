# Feature Context: Human Resource Management (HRM) (Backend)

Implementation phases for the HRM module, focusing on workforce management, payroll compliance, and leave workflows.

## Implementation Phases (Backend Only)

### Phase 1: Workforce & Org Schema — DONE
- [x] Migrations for `departments`, `positions`, `employees`.
- [x] `Employee`, `Department`, `Position` models with `BelongsToTenant` and `Auditable`.
- [x] Sensitive-data encryption for employee profiles. Migration `2024_01_01_000020_encrypt_employee_compensation_fields.php` converts `employees.base_salary` from `decimal(15,2)` to a text column carrying Laravel ciphertext and adds nullable `bank_name`, `bank_account_name`, `bank_account_number`. The `Employee` model casts all four as `'encrypted'`. `EmployeeResource` exposes them only to callers with `hrm.payroll.read` and masks `bank_account_number` to last-4. `PayrollService::computeFor()` continues to coerce `(float) $employee->base_salary` (the decrypted accessor returns a numeric string).
- [ ] Self-Service Profile Detail Access: Resolve issue where employees cannot view their own details. Create `EmployeePolicy` (similar to `LeavePolicy`) and authorize `view` if the authenticated user's linked employee ID matches the record (`$user->employee?->id === $employee->id`), bypassing the requirement for administrative `hrm.employee.read` permission. Register the policy in `TenantServiceProvider`.

### Phase 2: Leave & Time Tracking — DONE
- [x] Migrations for `leave_types` and `leaves`.
- [x] `LeaveService` with balance accrual (annual allowance − YTD approved days) and request lifecycle (`submitRequest`, `approve`, `reject`, `syncFromApproval`).
- [x] eApprovals integration. `LeaveService::submitRequest()` looks up `ApprovalWorkflow::where(module=hrm, type=leave)`; when present it opens an `ApprovalRequest` via `ApprovalService::submitRequest` inside the same DB transaction. `ApprovalService::notifyOriginModule()` now dispatches `App\Tenants\Modules\Approvals\Events\ApprovalRequestFinalized`. The HRM listener `App\Tenants\Modules\HRM\Listeners\SyncLeaveFromApproval` matches `requestable_type=App\Models\Tenant\Leave` and calls `LeaveService::syncFromApproval()` (which re-checks balance on approve). Legacy `POST /leaves/{id}/approve|reject` returns 422 when an active workflow request exists so clients funnel through `POST /approval-requests/{id}/process`.
- [x] `hrm.leave.*` Eloquent Policies. `LeavePolicy` + `LeaveTypePolicy` registered in `TenantServiceProvider::boot()`. `LeavePolicy::view/delete` short-circuits for the linked owner (via `User::employee()` HasOne, newly added). The base `App\Http\Controllers\Controller` adopts `AuthorizesRequests` so `$this->authorize()` works in `LeaveController` and `LeaveTypeController`.
- [x] Withdrawal flow. `LeaveService::withdraw(Leave)` runs inside a transaction: any `pending` `ApprovalRequest` linked to the leave is marked `cancelled` (with an `ApprovalHistory` entry attributed to `Auth::id()` or the employee's user_id) before the leave is soft-deleted. `LeaveResource` exposes `approvalRequestId` + `approvalStatus`; `Leave::activeApprovalRequest()` prefers the eager-loaded `approvalRequests` collection when present (avoids N+1 on `GET /leaves`).

### Phase 3: Payroll Engine & Compensation — DONE (compliance + Policies pending)
- [x] Migrations for `payroll_periods`, `payslips` (deductions stored in `payslips.deductions` JSON column).
- [x] `PayrollService` computes gross, flat tax (10%), NSSF (4%), net.
- [x] Automated Journal Entry posting to FMS on `closePeriod()`. Migration `2024_01_01_000021_add_journal_entry_to_payroll_periods.php` adds nullable `journal_entry_id` + `closed_at`. `PayrollService` now depends on `AccountingService` and posts a balanced accrual journal (Dr Wage Expense / Cr Tax / Cr NSSF / Cr Wages Payable) keyed on `PAYROLL-{period_id}`. Account codes are configurable via `config/hrm/payroll.php` (env-overridable `PAYROLL_ACCOUNT_*`). Missing chart codes raise a `DomainException` listing what's needed; the close transaction rolls back so the period stays at `processed` for retry. Net is derived as the balancing figure (`gross - tax - nssf`) to avoid cumulative per-payslip rounding tripping `AccountingService::validateBalancedEntry()`.
- [ ] `hrm.payroll.*` Policy classes (`PayrollPeriodPolicy`, `PayslipPolicy`).

### Public Careers Surface — DONE
- [x] `PublicCareersController` exposes `GET /api/v1/public/job-vacancies`, `GET /api/v1/public/job-vacancies/{id}`, `POST /api/v1/public/applications` outside `auth:api`. Listings hard-filter `status=open`; detail routes return 404 for anything non-open. Submission cross-checks the vacancy status and returns 422 if no longer open. Tenant still resolves via `X-Tenant-Handle` so the public listing is tenant-scoped even without Passport. Closes the gap a public careers page would have had — candidates can now browse and apply without an admin token.

### Phase 4: Recruitment & Performance — MVP DONE
- [x] Migrations: `job_vacancies` (+ `applications`), `appraisals`.
- [x] Models: `JobVacancy`, `Application`, `Appraisal`. Each ships a `STATUS_FLOW`-style transition map so services can fail fast on invalid moves.
- [x] `RecruitmentService` — vacancy CRUD + `publishVacancy()` (draft → open, stamps `posted_at`) + `closeVacancy(reason: closed|filled)`; application submit + `transitionApplication()` validated against `Application::STATUS_FLOW`.
- [x] `PerformanceService` — appraisal lifecycle `draft → submitted → reviewed → closed`; `review()` accepts reviewer-provided rating + notes and stamps `reviewed_at`; closed records are immutable.
- [x] Resource-layer masking:
  - `ApplicationResource` hides `expectedSalary` unless `hrm.recruitment.read`.
  - `AppraisalResource` masks `overallRating` / `strengths` / `improvements` / `goals` unless caller is the employee, the reviewer, or holds `hrm.performance.read`.
- [ ] Interview scheduling / structured feedback (separate `interviews` + `interview_feedback` tables — deferred).
- [ ] 360-degree feedback (peers/subordinates write back into the same cycle) — `goals` JSON column is the placeholder for OKR storage; no dedicated tables yet.
- [x] `hrm.recruitment.*` / `hrm.performance.*` Eloquent Policies. `JobVacancyPolicy` (view/create/update/delete/publish/close), `ApplicationPolicy` (view/create — open per Phase 6 magic-link plan/transition/delete), `AppraisalPolicy` (view/create/update/submit/review/close/delete). All three registered in `TenantServiceProvider::boot()`. Controllers now drive authorization through `$this->authorize(...)`; FormRequest `authorize()` thinned to `return true`. AppraisalPolicy short-circuits for the reviewee (view, submit) and the assigned reviewer (view, review) via `$user->employee?->id` against `employee_id` / `reviewer_id`.

### Phase 5: QA & Privacy Testing — NOT STARTED
- [ ] P0 Privacy tests: regular employees cannot read others' salaries.
- [ ] P1 Payroll accuracy fixtures.
- [ ] P0 Tenancy isolation for workforce records.

### Phase 6: Candidate Login & Quiz Assessment — BACKEND MVP DONE
- [x] Tenant migration `2024_01_01_000022_create_quiz_assessment_tables.php` creates `quizzes` / `quiz_questions` / `quiz_attempts`. `quiz_questions.correct_answer` is a `text` column carrying Laravel ciphertext (cast `'encrypted'`); `quiz_attempts.secure_token_hash` is a unique SHA-256 hash — the raw token is never persisted.
- [x] Magic-link flow. Admin calls `POST /api/v1/applications/{id}/quiz-attempts` (gated by `hrm.quiz.write`); `QuizService::assignToApplication` mints a 64-char random token, stores only its hash, advances the application to `assessment`, and returns `{ attempt, token, candidateUrl }`. The token never appears again in any subsequent read.
- [x] Candidate endpoints live OUTSIDE `auth:api`. `GET /api/v1/candidate/auth?token=...` resolves the attempt by hash and returns the attempt + sanitised quiz (no `correct_answer` field). `POST /api/v1/candidate/quizzes/{attempt}/start|submit?token=...` drive the lifecycle.
- [x] Auto-grading. `QuizService::submitAttempt` grades single/multiple-choice (sorted-array equality) and short-text (case-insensitive trim), persists `score` + `passed` on the attempt, and calls `WorkflowStatusService::validateTransition` to flip the linked Application to `assessment_completed`. If the tenant has removed that status from `hrm.application`, the completion is still recorded; the Application stays in `assessment` for manual triage.
- [x] Workflow seed updated. `hrm.application` now ships with `assessment` (after `screening`) and `assessment_completed` (before `interview`). New `hrm.quiz_attempt` module seeded: `invited → in_progress → completed | expired | abandoned`. `TransitionApplicationRequest` loosened to `string|max:40` so tenant-customised statuses pass validation.
- [ ] Sandboxed Nuxt `/candidate/quiz` frontend (deferred — backend slice only).

### Phase 7: Advanced Interviewing & Panel Feedback — BACKEND MVP DONE
- [x] Migration `2024_01_01_000023_create_interview_tables.php` introduces `interviews`, `interview_feedback` (unique on `(interview_id, interviewer_id)`), and an `interview_interviewer` M:N pivot. Foreign keys: `application_id → applications` (cascade), `quiz_attempt_id → quiz_attempts` (set null), `interviewer_id → employees` (cascade).
- [x] `InterviewService` covers schedule / reschedule / cancel / complete / feedback upsert / scorecard aggregate. Scheduling makes a best-effort transition of the linked Application into `interview` via `WorkflowStatusService::validateTransition`; the transition is swallowed if disallowed so the interview record still lands. `scorecardFor()` returns `{ averageRating, recommendations: tally, submittedCount, pendingCount }`.
- [x] `CalendarSyncService::buildInvite()` emits an RFC 5545 ICS document — escapes per §3.3.11, embeds all interviewers + the candidate as ATTENDEEs, served via `GET /api/v1/interviews/{id}/invite.ics` as `text/calendar`. Full Google Calendar / Microsoft Graph OAuth integration is deferred; download-and-attach is the current MVP.
- [x] `InterviewPolicy` short-circuits `view` and `submitFeedback` for any caller whose linked Employee is in the `interview_interviewer` pivot — interviewers don't need `hrm.recruitment.write` to score their own panels.
- [x] Workflow seed adds `hrm.interview` (`scheduled → completed | cancelled | no_show`).
- [ ] Real Google Calendar + Microsoft Graph integration (OAuth bind, ICS push, RSVP webhooks).

### Phase 8: Digital Offer & Onboarding Pipeline — NEW SCOPE (Backend: DONE, Frontend: PLANNED)
- [x] Migrations for `offers`, `onboarding_checklists`, and `onboarding_tasks` tables.
- [x] DocuSign eSignature integration webhooks listening to document signatures.
- [x] Auto-creation and data syncing from `Application` $\rightarrow$ `Employee` record upon accepted offer sign.
- [x] Candidate History Linkage: Update schema to add a nullable `employee_id` to the `applications` table. When an application transitions to `hired` and an `Employee` profile is created, this field must link the application to the new employee.
- [x] Active Registry Enrollment: Upon transition to `hired`, the new `Employee` record must be created with the initial workflow status `active` by calling `EmployeeService::createEmployee(...)`.
- [ ] Frontend UI: Offers list directory (`/hrm/offers`) to manage job offers, and onboarding checklists dashboard (`/hrm/onboarding`) to track tasks.
- [ ] Frontend UI: Offer tab integration on candidate detail profile (`/hrm/recruitments/candidates/[id].vue`) supporting drafting, sending (Mock/DocuSign), manual wet signature acceptance, and declines.


## Configurable status flows

All lifecycle statuses (application pipeline, leave decision, appraisal cycle, vacancy state, employee status, payroll period state) live in the per-tenant `workflow_statuses` table, scoped by a dotted `module` key. The hardcoded `STATUS_FLOW` constants on `Application` and `Appraisal` were removed; the table is authoritative.

`WorkflowStatusService` (in IAM) is the single read/validate gateway. Domain services inject it and call `initialFor()` when creating records and `validateTransition()` before any state change. `TenantDatabaseSeeder::seedWorkflowStatuses()` ships canonical defaults for 6 modules (`hrm.application`, `hrm.leave`, `hrm.appraisal`, `hrm.vacancy`, `hrm.employee`, `hrm.payroll_period`); tenant admins can extend or customize via the CRUD endpoints.

See `skills/hrm/rules.md` §2 → "Status Flows".

## Module Layout (backend)

```
app/
├── Models/Tenant/
│   ├── Department.php
│   ├── Position.php
│   ├── Employee.php
│   ├── LeaveType.php
│   ├── Leave.php
│   ├── PayrollPeriod.php
│   ├── Payslip.php
│   ├── JobVacancy.php         (new — Phase 4A)
│   ├── Application.php        (new — Phase 4A)
│   └── Appraisal.php          (new — Phase 4B)
└── Tenants/Modules/HRM/
    ├── Controllers/  EmployeeController, DepartmentController, PositionController,
    │                 LeaveController, LeaveTypeController,
    │                 PayrollPeriodController, PayslipController,
    │                 JobVacancyController, ApplicationController, AppraisalController
    ├── Requests/     Store/Update Employee/Department/Position/Leave/LeaveType/hrm/payrollPeriod,
    │                 StoreJobVacancy, StoreApplication, TransitionApplication,
    │                 Store/UpdateAppraisal
    ├── Resources/    Employee/Department/Position/Leave/LeaveType/hrm/payrollPeriod/Payslip Resource,
    │                 JobVacancy/Application/Appraisal Resource
    └── Services/     EmployeeService, LeaveService, PayrollService,
                      RecruitmentService, PerformanceService
```

## API Surface (`/api/v1`, requires `auth:api` + tenant context)

| Method | Path                                       | Purpose                              |
|--------|--------------------------------------------|--------------------------------------|
| CRUD   | `/employees`                               | Workforce records                    |
| CRUD   | `/hrm/departments`                             | Org units                            |
| CRUD   | `/hrm/positions`                               | Job titles                           |
| CRUD   | `/leave-types`                             | Annual allowance catalogue           |
| RUD    | `/leaves`                                  | Submit & list requests, withdraw     |
| POST   | `/leaves/{leave}/approve`                  | Approve pending request              |
| POST   | `/leaves/{leave}/reject`                   | Reject pending request               |
| GET    | `/employees/{employee}/leave-balance`      | Per-type balance sheet               |
| CR     | `/hrm/payroll-periods`                         | Create & list periods                |
| POST   | `/hrm/payroll-periods/{id}/process`            | Generate payslips for active staff   |
| POST   | `/hrm/payroll-periods/{id}/close`              | Lock period                          |
| GET    | `/payslips`, `/payslips/{id}`              | List / view payslip                  |
| CRUD   | `/job-vacancies`                           | Vacancy listings                     |
| POST   | `/job-vacancies/{id}/publish`              | Draft → open                         |
| POST   | `/job-vacancies/{id}/close`                | Close with reason (closed/filled)    |
| GET    | `/applications`                            | List with filters (vacancy/status)   |
| POST   | `/applications`                            | Submit application                   |
| PATCH  | `/applications/{id}/status`                | Pipeline transition (validated)      |
| DELETE | `/applications/{id}`                       | Withdraw                             |
| CRUD   | `/appraisals`                              | Cycle CRUD                           |
| POST   | `/appraisals/{id}/submit`                  | draft → submitted                    |
| POST   | `/appraisals/{id}/review`                  | submitted → reviewed                 |
| POST   | `/appraisals/{id}/close`                   | reviewed → closed                    |
| CRUD   | `/workflow-statuses` (IAM)                 | Per-tenant status flow configuration |
| GET    | `/workflow-statuses/modules`               | Distinct configured module keys      |
| GET    | `/candidate/auth`                          | Validate magic-link quiz token       |
| GET    | `/candidate/quizzes`                       | List allocated quiz assessments       |
| POST   | `/candidate/quizzes/{id}/start`             | Start the timed assessment           |
| POST   | `/candidate/quizzes/{id}/submit`            | Grade & submit answers               |
| CRUD   | `/interviews`                              | Schedule & view interviews           |
| POST   | `/interviews/{id}/feedback`                | Submit structured interview feedback |
| CRUD   | `/offers`                                  | Create, view and send offer letters  |
| POST   | `/offers/{id}/esign-webhook`                | DocuSign hook capturing eSignature   |

## Permission Hooks
- `hrm.employee.write`      — workforce mutations
- `hrm.leave.write`         — leave create/approve/reject
- `hrm.payroll.read`        — view others' salary / payslip
- `hrm.payroll.write`       — create/process payroll periods
- `hrm.recruitment.read`    — view applicant `expectedSalary`
- `hrm.recruitment.write`   — create/update/publish vacancy, transition application
- `hrm.recruitment.interview` — schedule interviews and write feedback panels
- `hrm.recruitment.offer`     — draft and approve job offer templates
- `hrm.quiz.read`           — view quizzes and candidate scores
- `hrm.quiz.write`          — create/modify quizzes and questions
- `hrm.performance.read`    — view rating / notes / goals on appraisals you don't own or review
- `hrm.performance.write`   — create/update/submit/review/close appraisals
- `iam.workflow_statuses.write` — manage the per-tenant status table
