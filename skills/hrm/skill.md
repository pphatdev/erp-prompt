---
name: human-resource-management
description: Employees, departments, leave, attendance, payroll, recruitment (ATS), and appraisals. Handles sensitive PII.
---
# Human Resource Management (HRM)

Use this skill when building or extending workforce, leave, attendance, payroll, recruitment, or performance features. This module handles **sensitive PII** — every change here is a P0/P1 risk.

## Module surface (shipped)

```
Human Resource (sidebar group)
├── Employees                      — CRUD + self-service variant
├── Departments
├── Positions
├── Leave
│   ├── Leave Requests
│   └── Leave Types
├── Shifts
├── Attendance                     — clock-in / clock-out + reconciliation against shifts
├── Overtime
├── Payroll
│   ├── Payroll Periods
│   └── Payslips
├── Recruitment
│   ├── Vacancies                  — internal + public careers portal (/public/job-vacancies)
│   ├── Applications
│   └── Candidates (Kanban)
└── Appraisals
```

| Layer | Path |
|---|---|
| Controllers | `app/Tenants/Modules/HRM/Controllers/*.php` |
| Services | `app/Tenants/Modules/HRM/Services/{EmployeeService, LeaveService, AttendanceService, PayrollService, RecruitmentService, ...}.php` |
| Resources | `app/Tenants/Modules/HRM/Resources/*.php` |
| Models | `app/Models/Tenant/{Employee, Department, Position, LeaveRequest, LeaveType, Shift, AttendanceLog, Overtime, PayrollPeriod, Payslip, Vacancy, Application, Quiz, QuizAttempt, Appraisal}.php` |
| Policies | `app/Policies/{Employee, Leave, Payroll, Application, ...}Policy.php` |
| Migrations | `database/migrations/tenant/{date}_create_hrm_tables.php` (+ later additions for recruitment, quizzes, appraisals) |
| Seeder | `TenantDatabaseSeeder.php` — seeds default leave types, workflow statuses, an admin user + linked employee using `RecruitmentService::generateNextEmployeeId()` |
| Pages | `frontend/pages/hrm/{employees, departments, positions, leaves, leave-types, shifts, attendance, overtime, payroll, recruitments/{vacancies, applications, candidates}, appraisals}.vue` + public `pages/careers/*.vue` |

## Permission slug catalog

```
hrm.employee.{read,write,delete}
hrm.employee.read.self                  ← self-service: scoped to authenticated user's employee row
hrm.department.{read,write,delete}
hrm.position.{read,write,delete}
hrm.leave.{read,write,delete}
hrm.leave.read.self                     ← self-service
hrm.payroll.{read,write,run}            ← gates base_salary visibility
hrm.attendance.{read,write}
hrm.attendance.read.self
hrm.recruitment.{read,write,delete}
hrm.recruitment.application.{read,write}
hrm.appraisal.{read,write}
```

Self-service `.self` permissions pair with ownership policies — e.g. `EmployeePolicy::view()` returns `true` when `($user->hasPermission('hrm.employee.read') OR ($user->hasPermission('hrm.employee.read.self') AND $user->employee_id === $row->id))`.

## Critical rules

### 1. Employee data privacy (P0)
- `Employee::base_salary` requires `hrm.payroll.read` — Resource hides the field otherwise.
- `Employee::national_id`, `bank_account`, `tax_id` are encrypted at the cast level. Use `'encrypted'` cast in the model; never store plaintext.
- Self-service responses MUST scrub financial fields. `EmployeeResource` checks the current user's permission set before exposing each field.
- See [`employee_data_collection.md`](./employee_data_collection.md) — **mandatory** read for any HRM change.

### 2. Candidate → Employee conversion (P0)
- Hiring is **explicit**, not implicit. Setting an Application's status to `hired` does NOT create an Employee.
- Conversion endpoint: `POST /applications/{id}/convert-to-employee` (single) or `POST /applications/bulk-convert-to-employees`.
- Reversible for 7 days: `POST /applications/{id}/revert-employee-conversion`.
- On conversion, new Employee is provisioned with `status = active` so they appear in registry views immediately.
- Recruitment history (Application, quiz attempts, interview feedback, offer files) links to the Employee via `employees.application_id` (nullable). See [`rules.md`](./rules.md) § "Hire → Employee Conversion Contract" and [`recruitment/flow.md`](./recruitment/flow.md) Stage 4.5.

### 3. Numbering — Employee ID + Candidate code
- Employee ID format `{prefix}{NNNN}`, zero-padded ≥ 4 digits. Generator: `RecruitmentService::generateNextEmployeeId()`.
- Candidate code format `{prefix}{YYYYMM}-{NNN}`, monthly reset. Generator: `Application::generateCandidateCode()` (fired in `creating` model event).
- Both read prefixes from `SettingService` (`numbering.employee_id_prefix`, `numbering.candidate_code_prefix`). Hardcoded prefixes forbidden. See [`skills/configuration/numbering.md`](../configuration/numbering.md).
- Both `employee_id` and `candidate_code` carry unique DB constraints (employee_id non-partial — terminated employees keep their IDs forever).

### 4. Leave & attendance
- Leave balance accrual: tenure-based, computed against YTD approved + pending. Enforce balance check before allowing submission.
- Multi-level approvals via **eApprovals**: leave requests dispatch into the approval workflow; days lock during the workflow.
- Attendance reconciliation: daily job matches `attendance_logs` against shifts + grace periods + public holidays + leaves to compute `present` / `late` / `absent` / `on_leave`.
- Full spec: [`time_off_attendance/rules.md`](./time_off_attendance/rules.md).

### 5. Payroll
- Runs as a queued job (`Laravel Queue`) — payroll for large headcounts must not block the request.
- Payslip PDFs are generated server-side, signed-URL access only, and stored under tenant-scoped storage.
- Posts to FMS: payroll run debits a Salary expense account and credits a Payroll Payable liability. CoA codes are seeded by `TenantDatabaseSeeder::seedChartOfAccounts()`.

### 6. Candidate quiz + ATS security
- Magic-link auth: short-lived hashed token scoped to `application_id` → temporary `candidate` role session limited to reading the quiz + submitting answers.
- Quiz answer keys are encrypted at rest (`'encrypted'` cast) to prevent answer leakage from a DB dump.
- Auto-grading on submission updates `quiz_attempts` and writes the score back to the Application.

### 7. Fixed Asset Custodian Scoping (Fixed Asset Management Link)
- Employees in active custody of company property (e.g., laptops, vehicles) must have their physical assets displayed in a dedicated **Assets** tab on their profile workspace (both admin and self-service views).
- The single employee fetch queries (`show` and `me` inside `EmployeeController`) must eagerly load the `assets` relationship.
- `EmployeeResource` serializes the custody records under the `assets` key using `AssetResource` conditionally to avoid N+1 queries.

### 8. Tenant-configurable settings
- All module thresholds, probation rules, working week maps, geofence radius coordinates, FMS account mapping codes, and appraisal calculation ratios must be loaded from `tenant_settings` via `SettingService::get('hrm.*')`.
- Hardcoding rules or account mappings is strictly forbidden. Developers must declare default values in `SettingService::defaults()` and follow the dotted-key registry specified in [`rules.md`](./rules.md) § 10.

## Frontend integration

- **Self-service pages** live under `My Workspace` sidebar group (hidden for admins via `.filter(group => !(group.id === 'self-service' && authStore.isAdmin))`).
- **Public careers portal** at `/careers` (no auth required) hits `GET /public/job-vacancies` and `POST /public/applications`. Magic-link assessment continues without a real user session.
- **Kanban**: `pages/hrm/recruitments/candidates.vue` uses a drag/drop column view (one PrimeVue dependency justified here).
- **Confirm modals**: every state-changing action (publish vacancy, close payroll period, convert to employee) uses `useToast().confirm({ color: 'warning' | 'danger' })` — never `window.confirm`. See `rules/frontend/standards.md` § 6.

## Status

| Feature | Backend | Frontend |
|---|:---:|:---:|
| Employees CRUD + self-service | ✅ | ✅ |
| Departments + Positions | ✅ | ✅ |
| Leave Types + Requests + accrual + eApprovals routing | ✅ | ✅ |
| Shifts + Attendance (clock in/out) + reconciliation | ✅ | ✅ |
| Overtime | ✅ | ✅ |
| Payroll Periods + Payslip generation (PDF) | ✅ | ✅ |
| Vacancies + public careers portal | ✅ | ✅ |
| Applications + Candidates Kanban | ✅ | ✅ |
| Candidate quiz (magic link, sandboxed, auto-graded) | ✅ | ✅ |
| Appraisals | ✅ | ✅ |
| Explicit candidate→employee conversion (single + bulk + revert) | ✅ | ✅ |
| Fixed Asset Custody Integration (Assets tab) | ✅ | ✅ |


## Troubleshooting

| Symptom | Cause | Fix |
|---|---|---|
| Employee can see another's `base_salary` | Resource didn't gate on `hrm.payroll.read` | Check `EmployeeResource::toArray()` — wrap the field with `$this->when($request->user()->hasPermission('hrm.payroll.read'), ...)` |
| Leave balance wrong | `LeaveAccrualJob` hasn't run for the period | Run the job; verify YTD calculation includes both approved + pending |
| Setting status to `hired` didn't create an Employee | Correct behavior — conversion is explicit | Call `POST /applications/{id}/convert-to-employee` |
| Payroll fails | Missing tax config for an employee | Check `PayrollService` logs — fill `tax_config` for the offending employee |
| New Employee ID collides | Two concurrent creates raced past the sequential generator | Caller must catch PostgreSQL `23505` and retry — see `skills/configuration/numbering.md` § 3.4 |
| Magic-link quiz session lets candidate see other quizzes | Token scoping broke — should be application-scoped | Verify the temporary session role has read access **only** to the quiz tied to `application_id` |

## Read next
- [`employee_data_collection.md`](./employee_data_collection.md) — **MANDATORY** for any HRM change
- [`rules.md`](./rules.md) — full conversion contract + permission matrix
- [`flow.md`](./flow.md) — top-level HRM flows
- [`recruitment/flow.md`](./recruitment/flow.md) — vacancy → application → hire flow
- [`time_off_attendance/rules.md`](./time_off_attendance/rules.md) — leave + attendance spec
- [`testing.md`](./testing.md) — P0/P1/P2 test matrix
