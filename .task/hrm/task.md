# Task Context: HRM

## Objective
Stand up the backend API for the HRM module: workforce records, leave workflow, and payroll engine. Phases 4 (Recruitment/Performance) and 5 (QA & Privacy testing) remain.

## Checklist

### Phase 1 — Workforce
- [x] Migrations: `departments`, `positions`, `employees`
- [x] Models: `Employee`, `Department`, `Position` (UUID, `BelongsToTenant`, `Auditable`)
- [x] Resources, FormRequests, `EmployeeService`
- [x] Controllers: `EmployeeController`, `DepartmentController`, `PositionController`
- [x] Routes: `/api/v1/employees`, `/hrm/departments`, `/hrm/positions`
- [x] Field-level encryption for `base_salary` + `bank_name` / `bank_account_name` / `bank_account_number` (migration `2024_01_01_000020`, Laravel `'encrypted'` cast, account number masked except last 4 digits in `EmployeeResource`)
- [x] Employee Self-Service detail view: Implement `EmployeePolicy` and register it in `TenantServiceProvider` to allow employees to view their own profile details (`$user->employee?->id === $employee->id`) without administrative `hrm.employee.read` permission.

### Phase 2 — Leave
- [x] Migrations: `leave_types`, `leaves`
- [x] Models: `LeaveType`, `Leave` (now exposes `approvalRequests()` morphMany + `activeApprovalRequest()` helper)
- [x] Resources, FormRequests, `LeaveService` (balance calc + approve/reject)
- [x] Controllers: `LeaveController`, `LeaveTypeController`
- [x] Routes: `/api/v1/leaves`, `/leave-types`, `/employees/{employee}/leave-balance`
- [x] eApprovals integration — `LeaveService::submitRequest` opens an `ApprovalRequest` when a workflow with `module=hrm, type=leave` exists. `ApprovalService::notifyOriginModule` dispatches `ApprovalRequestFinalized`; HRM listener `SyncLeaveFromApproval` flips the Leave's status (with balance check). Legacy `/leaves/{id}/approve|reject` returns 422 when a workflow request is active so clients are pushed to `/approval-requests/{id}/process`.
- [x] `hrm.leave.*` policies — `LeavePolicy` + `LeaveTypePolicy` registered with `Gate` in `TenantServiceProvider`; controllers use `$this->authorize(...)` (base `Controller` now uses `AuthorizesRequests`). Owners can view/withdraw their own pending requests without `hrm.leave.delete`.
- [x] Leave withdrawal cancels its pending `ApprovalRequest` (status `cancelled`, history entry attributed to the actor) via `LeaveService::withdraw()`. `LeaveResource` exposes `approvalRequestId` + `approvalStatus`; index query eager-loads `approvalRequests` to avoid N+1. Postman: fixed `Process Approval Request` payload (`status` → `action`), added `Create Leave Approval Workflow`, and `Submit Leave Request` captures `approval_request_id`.

### Phase 2.5 - Holidays + Calendar (Shipped 2026-06-01)
- [x] Migration `2024_01_01_000092_create_holidays_table.php` - holidays with id, name, date, type (`public`/`company`/`optional`), `is_recurring` boolean for yearly anchors, notes. Unique on `(tenant_id, date, name)` so multiple holidays can share a date but each must be uniquely named. Index on date + type.
- [x] `Holiday` model with `BelongsToTenant` + `Auditable` + `SoftDeletes`; type constants, date + bool casts.
- [x] `HolidayService` - CRUD + `occurrencesInRange(from, to)` expanding recurring entries by year (clamps Feb 29 to Feb 28 on non-leap years), + `calendarFeed(from, to)` combining expanded holidays with approved/pending leaves so the month view renders mixed events in one round-trip.
- [x] `HolidayPolicy` registered. `HolidayController` + `HolidayResource` (camelCase). Routes: `apiResource('holidays')` + `GET /hrm/calendar?from=&to=`. Index filters: `?type`, `?search`, `?from`, `?to`, `?recurring_only`.
- [x] Perms `hrm.holiday.{read,write,delete}` seeded. Module slugs `hrm-timeoff-holidays` (`/hrm/timeoff/holidays`) and `hrm-timeoff-calendar` (`/hrm/timeoff/calendar`) seeded under `hrm-timeoff` (sort_order 5 + 6).
- [x] UI `pages/hrm/timeoff/holidays/index.vue` - KPI strip (Total, Public, Company, Optional), type chips, search, table with date+weekday, name, type badge, recurring chip, notes, edit/delete actions. New/Edit modal with name, date, type, recurring checkbox, notes.
- [x] UI `pages/hrm/timeoff/calendar/index.vue` - month grid (7 cols / 6 rows / 42 cells, anchored on the Sunday before the 1st). Prev / Today / Next month navigation watched to reload the feed. Legend chips for Public / Company / Optional / Leave. Each day cell shows day number + event count + colored event pills (holidays color-coded by type, leaves in warning). Click empty day to add holiday (gated by `hrm.holiday.write`), click holiday pill to edit, click leave pill is a no-op (leaves owned by the Leaves page). Today's cell ringed in primary, weekends faintly tinted, other-month cells dimmed.
- [x] Sidebar: Holidays + Calendar entries added under HRM > Time & Attendance. Breadcrumb map gains `holidays` + `calendar`.

### Phase 3 — Payroll
- [x] Migrations: `payroll_periods`, `payslips`
- [x] Models: `PayrollPeriod`, `Payslip`
- [x] Resources, FormRequest, `PayrollService` (compute earnings + tax/NSSF deductions)
- [x] Controllers: `PayrollPeriodController`, `PayslipController`
- [x] Routes: `/api/v1/hrm/payroll-periods`, `/hrm/payroll-periods/{id}/process|close`, `/payslips`
- [x] Postman collection updated (`docs/postman/erp_collection.json` → "HRM" folder, 24 requests + 7 new collection variables)
- [ ] Queue `processPeriod()` for tenants with > 200 employees
- [x] FMS journal entry posting on `closePeriod()` — migration `2024_01_01_000021` adds `journal_entry_id` + `closed_at`; `PayrollService` aggregates gross/tax/nssf, derives net as the balancing figure, looks up four account codes from `config/hrm/payroll.php`, and posts a balanced journal via `AccountingService::postEntry()`. Missing codes ⇒ 422 listing what to create. Reference `PAYROLL-{periodId}` enforces idempotency. `PayrollPeriodResource` exposes `journalEntryId` + `closedAt`.
- [ ] PDF payslip generator + ESS portal endpoint

### Configurable status flows (cross-cutting — shipped)
- [x] Migration `2024_01_01_000019_create_workflow_statuses_table.php`
- [x] `App\Models\Tenant\WorkflowStatus` model + `forModule` scope
- [x] `App\Tenants\Modules\IAM\Services\WorkflowStatusService` (`for` / `lookup` / `initialFor` / `validateTransition` / `flushCache`)
- [x] CRUD: `GET/POST/PUT/DELETE /api/v1/workflow-statuses` + `GET /workflow-statuses/modules`
- [x] Seeder `TenantDatabaseSeeder::seedWorkflowStatuses()` populates 6 modules (`hrm.application`, `hrm.leave`, `hrm.appraisal`, `hrm.vacancy`, `hrm.employee`, `hrm.payroll_period`)
- [x] Domain services refactored — `RecruitmentService`, `PerformanceService`, `LeaveService`, `PayrollService`, `EmployeeService` all consult the table
- [x] Removed `Application::STATUS_FLOW` + `Appraisal::STATUS_FLOW` constants — DB is authoritative
- [x] Postman "IAM" folder grew 5 → 11 with Workflow Status requests
- [x] Skills doc: `skills/hrm/rules.md` §2 "Status Flows" subsection
- [ ] Frontend `/workflow-statuses` admin page (deferred — user requested API only)

### Public Careers surface (cross-cutting — shipped)
- [x] `App\Tenants\Modules\HRM\Controllers\PublicCareersController` — `GET /api/v1/public/job-vacancies` (filtered to `status=open`), `GET /api/v1/public/job-vacancies/{id}` (404s anything non-open), `POST /api/v1/public/applications` (rejects closed/draft vacancies with 422)
- [x] Routes live OUTSIDE `auth:api`; tenant still resolves through `X-Tenant-Handle` (InitializeTenancyByHandle)
- [x] Postman "Recruitment" folder reordered: candidate flow (List Public Vacancies → Get Public Vacancy → Public: Submit Application) first, admin paths after. New `public_job_vacancy_id` collection variable.

### Phase 4 — Recruitment & Performance (MVP shipped)
- [x] Migrations: `job_vacancies`, `applications`, `appraisals`
- [x] Models: `JobVacancy`, `Application` (with `STATUS_FLOW` map), `Appraisal` (with lifecycle map)
- [x] Resources, FormRequests, `RecruitmentService` + `PerformanceService`
- [x] Controllers: `JobVacancyController`, `ApplicationController`, `AppraisalController`
- [x] Routes: `/api/v1/job-vacancies` (+ publish/close), `/applications` (+ PATCH /status), `/appraisals` (+ submit/review/close)
- [x] Postman: "Recruitment" (12 requests) + "Performance" (8 requests) sub-folders
- [ ] Interview scheduling / structured feedback (deferred — not in MVP scope)
- [ ] 360-degree peer feedback + OKR tracking (deferred — `Appraisal.goals` JSON column holds OKR placeholders)
- [x] `hrm.recruitment.*` / `hrm.performance.*` Eloquent Policies — `JobVacancyPolicy`, `ApplicationPolicy`, `AppraisalPolicy` registered in `TenantServiceProvider`. Controllers gate every action via `$this->authorize(...)`; FormRequests now `return true` from `authorize()`. AppraisalPolicy short-circuits for the reviewee (view/submit) and assigned reviewer (view/review).

### Phase 5 — QA & Privacy
- [ ] P0: tenant isolation tests (Pest)
- [ ] P0: privacy — non-payroll users cannot read others' `base_salary`/payslip
- [ ] P1: payroll accuracy fixtures (tax/NSSF brackets)
- [ ] Audit log assertions on hire, terminate, approve, process

### Phase 6 — Candidate Login & Quiz Assessment (Backend MVP shipped)
- [x] Migration `2024_01_01_000022_create_quiz_assessment_tables.php` — `quizzes`, `quiz_questions` (Laravel-`encrypted` `correct_answer`), `quiz_attempts` (SHA-256 `secure_token_hash`, expires_at, answers JSON, score, passed)
- [x] Models: `Quiz`, `QuizQuestion`, `QuizAttempt` (token hash hidden via `$hidden`)
- [x] `QuizService` — quiz authoring, magic-link issuance (raw token returned once, only its hash stored), idempotent start, auto-grading (`single_choice` / `multiple_choice` / `short_text`), application transition to `assessment_completed`
- [x] Admin `QuizController` — `apiResource('quizzes')`, `POST /quizzes/{id}/questions`, `POST /applications/{id}/quiz-attempts` (returns `{ attempt, token, candidateUrl }`)
- [x] Candidate-facing `CandidateQuizController` — public routes `/candidate/auth`, `/candidate/quizzes/{attempt}/start|submit` (token via query string, no Passport). Sanitised quiz payload omits `correct_answer`.
- [x] `QuizPolicy` registered with `Gate`; `hrm.quiz.read|write|delete`
- [x] Seeder: `hrm.application` workflow extended with `assessment` + `assessment_completed`; new `hrm.quiz_attempt` module (`invited → in_progress → completed | expired | abandoned`)
- [x] `TransitionApplicationRequest` no longer hardcodes the enum — `workflow_statuses` is authoritative
- [x] Postman: new HRM › Quiz Assessment subfolder (10 requests), `quiz_id` / `quiz_attempt_id` / `candidate_quiz_token` / `vehicle_id` collection variables added
- [ ] Frontend Portal: Candidate Sandboxed Assessment dashboard `/candidate/quiz?token=...` using Nuxt 3 & PrimeVue (deferred — backend only)

### Phase 7 — Advanced Interviewing & Panel Feedback (Backend MVP shipped)
- [x] Migration `2024_01_01_000023_create_interview_tables.php` — `interviews`, `interview_feedback` (unique `(interview_id, interviewer_id)`), `interview_interviewer` M:N pivot
- [x] Models: `Interview` (HasMany feedback, BelongsToMany interviewers, `averageRating()` accessor), `InterviewFeedback`
- [x] `InterviewService` — schedule (best-effort moves Application → `interview`), reschedule (only when scheduled), cancel (workflow-validated), complete, `submitFeedback` (upsert by interviewer), `scorecardFor()` aggregate (`averageRating`, recommendations tally, submitted/pending counts)
- [x] `CalendarSyncService` — RFC 5545 (.ics) invite builder including all interviewers + candidate as ATTENDEEs. Real Google/Outlook OAuth deferred.
- [x] `InterviewController` — apiResource + `/cancel`, `/complete`, `/feedback`, `/scorecard`, `/invite.ics`
- [x] `InterviewPolicy` registered with `Gate`; assigned-interviewer short-circuit on `view` + `submitFeedback`
- [x] Seeder: new `hrm.interview` workflow_statuses module (`scheduled → completed | cancelled | no_show`)
- [x] Postman: HRM › Interviewing subfolder (9 requests), `interview_id` collection variable added

### Phase 8 — Digital Offer & Onboarding Pipeline (Workforce Transition)
- [ ] Migrations: `offers`, `onboarding_checklists`, `onboarding_tasks`
- [ ] Models: `Offer`, `OnboardingChecklist`, `OnboardingTask`
- [ ] Services: `ESignatureService` (DocuSign API signature integration), `OnboardingService` (provisioning task triggers for IT & finance)
- [x] Hand-off: Automatic `Employee` creation trigger on digital sign acceptance of offer letter
- [x] Schema update: Add `employee_id` (nullable foreign key) to `applications` table to link hired candidates to their employee profiles
- [x] History linkage: Update `Employee` model and services to link and traverse pre-hire details (applications, quiz assessments, interviews, panel feedback, offer letters) in the employee's timeline/profile
- [x] Workforce listing: Assert that the newly provisioned employee record defaults to the initial `active` status so they are instantly searchable and displayed in the global employee directory listing (`GET /employees`)

### Fixed Asset Custody Integration (Assets tab)
- [x] Model relationship: Add `assets()` HasMany relationship on the `Employee` model pointing to `Asset` on `custodian_employee_id`.
- [x] Response serialization: Update `EmployeeResource` to serialize `assets` via conditional `whenLoaded` closure using `AssetResource::collection`.
- [ ] API Eager Loading: Load `assets` relationship inside `EmployeeController@show` and `@me` endpoints.
- [ ] Profile Assets UI Tab: Update `frontend/pages/hrm/employees/[id].vue` to display a dedicated **Assets** tab showing all physical fixed assets currently in custody of the employee (asset code, name, category, condition, status).

