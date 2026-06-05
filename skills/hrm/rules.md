# Human Resource Management (HRM) Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `hrm`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix — Admin Scope:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `employee` | `hrm.employee.read` | `hrm.employee.write` | `hrm.employee.delete` | `hrm.employee.export` |
| `payroll` | `hrm.payroll.read` | `hrm.payroll.write` | - | `hrm.payroll.export` |
| `leave` | `hrm.leave.read` | `hrm.leave.write` | `hrm.leave.delete` | `hrm.leave.export` |
| `performance`| `hrm.performance.read` | `hrm.performance.write` | - | `hrm.performance.export` |
| `recruitment`| `hrm.recruitment.read` | `hrm.recruitment.write` | `hrm.recruitment.delete` | `hrm.recruitment.export` |
| `quiz`      | `hrm.quiz.read`        | `hrm.quiz.write`        | `hrm.quiz.delete`        | `hrm.quiz.export`        |

### Feature Matrix — `.self` Scope (Self-Service):
These permissions are granted to the seeded `employee` role and pair with policy ownership checks. They **never** unlock admin endpoints — the policy only honors them when the target row belongs to the caller (`$user->employee?->id === $row->employee_id`). See [`iam/rules.md`](../iam/rules.md) for the convention.

| Permission | Endpoint(s) | Notes |
|---|---|---|
| `hrm.employee.read.self`     | `GET /employees/me`, `GET /employees/{self}` | Read own profile only |
| `hrm.employee.write.self`    | `PATCH /employees/me` | Whitelisted fields: `first_name`, `last_name`, `phone`. Never salary/bank/email/dept/position/status |
| `hrm.leave.read.self`        | `GET /leaves`, `GET /leaves/{own}`, `GET /employees/{self}/leave-balance` | List force-filters to caller's employee_id |
| `hrm.leave.write.self`       | `POST /leaves`, `DELETE /leaves/{own-pending}` | Service layer asserts employee_id matches caller |
| `hrm.payslip.read.self`      | `GET /payslips`, `GET /payslips/{own}` | List force-filters to caller's employee_id |
| `hrm.performance.read.self`  | `GET /appraisals/{own-or-reviewer}` | Allowed when caller is `employee_id` OR `reviewer_id` on the row |
| `hrm.performance.submit.self`| `POST /appraisals/{own}/submit` | Submit own self-assessment |

## 2. Implementation Standards

### Employee & Payroll Flow
1. **Hire/Onboard**: Create profile and set compensation.
2. **Tracking**: Log time, attendance, and leave requests.
3. **Payroll Prep**: Aggregate earnings and deductions.
4. **Processing**: Execute payroll engine with tax calculations.
5. **Disbursement**: Generate payslips and post bank transfer file.
6. **Compliance**: Archive period data for reporting.
7. **Recruitment History Linkage**: Link all pre-hire candidate documentation (applications, quiz assessments, interviews, panel feedback, offer letters) to the new employee profile upon hire to maintain absolute auditability and onboarding context.
8. **Workforce Registry Enrollment**: Ensure newly hired candidates are instantly enrolled as `active` in the employee database so they appear on all workforce directory list views.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\HRM`
- **Service Layer**: Logic in `Services/hrm/payrollService.php`, `Services/LeaveService.php`.
- **Candidate-to-Employee Linkage**: When a candidate is hired, the successful `Application` must be linked to an `Employee` via `applications.employee_id` so the employee profile can traverse the complete pre-hire record (quizzes, structured interview scores, feedback panels, signed offers). **The linkage is a deliberate two-step flow, not a side-effect of the status transition** — see "Hire → Employee Conversion Contract" below.
  - *Employee List Enrollment*: The conversion service must call `EmployeeService::createEmployee` with the initial `active` workflow status. This ensures that the newly created record is immediately searchable and visible in `GET /api/v1/employees` (the workforce directory screen).

- **Auto-generated `applications.candidate_code`** (P1): Every `Application` row carries a human-readable candidate code following the pattern `CAN-<YYYYMM>-<NNN>` (e.g. `CAN-202605-001`). The numeric component is **per-month** so recruiters can scan a code and immediately tell when the candidate was received. Implementation lives on the model — `App\Models\Tenant\Application::generateCandidateCode($reference = null)` is called from the `creating` event when `candidate_code` is empty; the reference month is taken from `applied_at` (falls back to `now()`). The generator scans `withTrashed()` so withdrawn applications **do not free their numbers for reuse** (mirrors the `employee_id` audit invariant). Sequence is computed from `MAX(numeric_suffix)` of the same-month prefix; the unique constraint on `applications.candidate_code` (migration `2024_01_01_000028_add_candidate_code_to_applications_table.php`) is the final guard against concurrent-submission races — callers running inside a DB transaction should be prepared to retry on a 23505 violation. The migration backfills pre-existing rows in `applied_at` order so the historical sequence matches the order applications were received in each month. `ApplicationResource` exposes the field as `candidateCode`.
- **Hire → Employee Conversion Contract**: Transitioning an application to `hired` only changes status — it does **not** auto-create an employee. `RecruitmentService::transitionApplication` must stay free of `Employee::create()`-side-effects. Conversion happens via dedicated endpoints so it's auditable, idempotent, and reversible within a bounded window.
  - **Single convert** — `POST /applications/{application}/convert-to-employee` → `RecruitmentService::convertToEmployee(Application)`. Requires both `hrm.recruitment.write` AND `hrm.employee.write` (policy `ApplicationPolicy::convert`). Idempotent: if `employee_id` is already set, returns the linked employee without creating a duplicate. Email is the dedupe key — an **active** `Employee` with the same email is reused instead of cloned (response signals this with `linkedExisting: true`). Soft-deleted matches (terminated or post-revert) are **ignored** — a fresh row is created in that case so rehires get a new Employee record and a new `employee_id`. Stamps `applications.converted_at = now()` on link.
  - **DB constraint contract**: `employees.email` carries a **partial unique index** that only enforces when `deleted_at IS NULL` (migration `2024_01_01_000026_make_employees_email_unique_partial.php`). Without it, a soft-deleted row keeps the email "reserved" and blocks rehires with a 23505 violation. `employees.employee_id` is **intentionally NOT partial** — terminated employees keep their IDs forever (audit invariant); revert is the only path that frees an ID, via the rename trick documented below.
  - **Auto-generated `employee_id`** — produced by `RecruitmentService::generateNextEmployeeId()` following the pattern `<EMPLOYEE_ID_PREFIX>-<NNNN>` (default: `TT-0000`, `TT-0001`, …, `TT-9999`, `TT-10000` once the sequence overflows the pad floor). **Zero-indexed**: the first auto-issued ID on a fresh tenant is `TT-0000`. Once any matching row exists, subsequent calls return `MAX(numeric_suffix) + 1`. The prefix and pad width live as `RecruitmentService::EMPLOYEE_ID_PREFIX` / `EMPLOYEE_ID_PAD` constants so a future tenant-configurable prefix can swap them without touching call sites. The unique constraint on `employees.employee_id` is the final guard against concurrent-conversion races; the surrounding DB transaction keeps the window small. **Do not hand-roll a sequence**: call `generateNextEmployeeId()` if you need an ID outside the conversion flow (e.g. seeders, bulk-import).
  - **Termination vs. revert — different ID lifecycles**:
    - **Termination** (`EmployeeService::terminateEmployee`) keeps `employee_id` intact on the soft-deleted row. Terminated employees **never free up their IDs for reuse** — historical audit/hrm/payroll references stay resolvable.
    - **Revert** (`revertEmployeeConversion`) is an "undo a mistake" action within a 7-day window. It **does** free the ID so the next conversion can re-issue it. Implementation: before soft-deleting the linked employee, the revert renames `employee_id` from `TT-0003` to `TT-0003-REV-<uniqid>` via an audited `update()` (the rename is captured in the Auditable trail). The renamed value no longer matches the generator's `^<prefix>-(\d+)$` regex, so the original number drops out of `MAX(numeric_suffix)` and the next convert picks it up. The unique constraint on `employee_id` is preserved (the renamed value is unique) and a future conversion creates a brand-new row with the freed number.
    - **Effect on the sequence**: if `TT-0002` is the highest and you `Convert → TT-0003`, then `Revert TT-0003`, the next convert produces `TT-0003` again. If you `Convert → TT-0003`, `Convert → TT-0004`, then `Revert TT-0003`, the next convert produces `TT-0005` (max is still TT-0004; the gap at `TT-0003` remains).
  - **Bulk convert** — `POST /applications/bulk-convert-to-employee` with `{ ids: string[] }` (1–200 UUIDs). Returns `{ converted, alreadyLinked, ineligible, missing, errors }` so the UI can surface partial outcomes without per-row 404 noise. Each row is gated through the same policy; any row not in `hired` lands in `ineligible`, any row already linked lands in `alreadyLinked`.
  - **Revert conversion** — `POST /applications/{application}/revert-employee-conversion` → `RecruitmentService::revertEmployeeConversion(Application)`. Refuses with 422 unless: status=`hired`, `employee_id` set, AND `converted_at` ≤ `RecruitmentService::REVERT_CONVERSION_WINDOW_DAYS` (7 days). Requires `hrm.recruitment.write` AND `hrm.employee.delete` (policy `ApplicationPolicy::revertConversion` — stricter than `convert` because it soft-deletes a workforce record). Soft-deletes the linked `Employee` (preserves audit trail; the next `convertToEmployee` creates a fresh row since `withTrashed()` is not used in the email lookup) and nulls both `employee_id` and `converted_at`. Outside the window, recruiters must use the normal off-boarding flow (`EmployeeService::terminateEmployee`).
  - **Schema** — `applications.converted_at TIMESTAMP NULL` (migration `2024_01_01_000025_add_converted_at_to_applications_table.php`). The `Application` model casts it as `datetime` and exposes it as `convertedAt` in `ApplicationResource`. Frontend reads this to decide whether to render the revert affordance.
- **Privacy**: Employee sensitive data must be encrypted at rest *and* gated at the Resource layer. The `Employee` model casts `base_salary`, `bank_name`, `bank_account_name`, `bank_account_number` via `App\Models\Casts\EncryptedWithFallback` (Laravel ciphertext stored in `text` columns; falls back to returning the raw value on `DecryptException` so legacy/seeded plaintext rows don't 500 list endpoints — re-encryption happens on the next UPDATE). `EmployeeResource` returns them only to callers with `hrm.payroll.read` and masks `bank_account_number` to the last 4 digits. New PII fields must follow the same dual-layer pattern — never rely on Resource masking alone, and never use stock `'encrypted'` cast for fields that may hold mixed/legacy data; use `EncryptedWithFallback` instead.
- **Authorization**: Eloquent Policies are the source of truth (`EmployeePolicy`, `LeavePolicy`, `LeaveTypePolicy`, plus existing `UserPolicy`/`RolePolicy`). Register every new policy in `TenantServiceProvider::boot()` and call `$this->authorize(...)` from controllers — never inline `$user->can()` checks for CRUD gates. FormRequest `authorize()` should usually `return true` so the controller-level policy fires.
  - *Self-Service Access*: Regular employees must be allowed to view their own profile details. In `EmployeePolicy::view`, short-circuit and return `true` if the authenticated user's linked employee record matches the requested employee (`$user->employee?->id === $employee->id`), allowing them to view their details without the broader administrative `hrm.employee.read` permission.
- **Workflows**: Use `eApprovals` integration for leave and expense requests. When a tenant has an `ApprovalWorkflow` with `module=hrm, type=leave`, `LeaveService::submitRequest` automatically opens an `ApprovalRequest`. Decisions flow through `POST /api/v1/approval-requests/{id}/process`; `ApprovalService` dispatches `ApprovalRequestFinalized`, and the `SyncLeaveFromApproval` listener flips the `Leave.status` via `LeaveService::syncFromApproval()`. The legacy `/leaves/{id}/approve|reject` endpoints remain only as a stop-gap for tenants without a configured workflow.
- **FMS posting**: `PayrollService::closePeriod()` aggregates gross/tax/nssf across payslips and posts a balanced accrual journal (`Dr EXP-WAGES / Cr LIA-TAX / Cr LIA-NSSF / Cr LIA-WAGES`) via `AccountingService::postEntry()` inside one DB transaction. Account codes come from `config('payroll.accounts')` (env-overridable). Missing codes raise `DomainException` → 422 listing what to create; the close rolls back so the period stays in `processed` for retry. Reference is `PAYROLL-{period_id}` — idempotent against accidental double-close.
- **Quiz Assessment (Phase 6)**: Magic-link only. Admin assigns a published `Quiz` to an `Application` via `POST /applications/{id}/quiz-attempts`; `QuizService` returns the raw token **once** and persists only its SHA-256 hash on the `quiz_attempt`. Candidate endpoints live outside `auth:api` and authenticate purely on `?token=...`. Correct answers live as Laravel-encrypted ciphertext on `quiz_questions.correct_answer` and are *never* serialised into the candidate-facing payload — only `QuizResource` exposes them, and only when the caller holds `hrm.quiz.write`. Auto-grading flips the linked `Application` to `assessment_completed` (must exist in `workflow_statuses` for `hrm.application` — seeded by default).
- **Public Careers surface**: Candidate-facing endpoints (`/api/v1/public/job-vacancies`, `/public/job-vacancies/{id}`, `POST /public/applications`) live outside `auth:api` but inside the tenant-scoped middleware group, so `X-Tenant-Handle` is still mandatory. Hard rule: public listings + show MUST filter `status=open`; submission MUST reject anything else with 422. Never expose vacancies in `draft`/`paused`/`closed`/`filled` through the public surface — admin routes are the only path for those.
- **Interviewing (Phase 7)**: Interviews use a 3-table schema — `interviews` (lifecycle), `interview_interviewer` (M:N assignment pivot), `interview_feedback` (unique row per interviewer per interview). `InterviewPolicy::submitFeedback` short-circuits for assigned interviewers — they DO NOT need `hrm.recruitment.write` to score their own panels. Scheduling best-effort moves the linked Application to `interview` via `WorkflowStatusService` (swallows disallowed transitions). HR finalises hire/reject via `/applications/{id}/status`, never through the interview lifecycle. Calendar invites ship as RFC 5545 ICS files (`/interviews/{id}/invite.ics`); Google/Outlook OAuth is deferred — do not call third-party calendar APIs from `CalendarSyncService` until that integration lands.
- **Fixed Asset Custody Integration**: To support asset auditing and tracking, the employee profile (administrative and self-service) must display all physical assets currently assigned to them as a custodian.
  - The `Employee` model must define a `HasMany` relationship `assets()` pointing to `Asset::class` with the foreign key `custodian_employee_id`.
  - `EmployeeResource` must serialize the assigned items under the `assets` key using `AssetResource::collection` inside a conditional `whenLoaded` closure to prevent duplicate/unloaded database calls.
  - The frontend employee detail page (`frontend/pages/hrm/employees/[id].vue`) must display an **Assets** tab with a detailed grid showing each asset's code, name, category, condition, and status.

### Status Flows (Configurable per tenant)
All HRM lifecycle statuses are stored in the central `workflow_statuses` table and resolved at runtime by `App\Tenants\Modules\IAM\Services\WorkflowStatusService`. **Do NOT add `const STATUS_FLOW = [...]` to domain models.**

| Module key | Initial | Terminal | Used by |
|---|---|---|---|
| `hrm.application` | `applied` | `hired`, `rejected`, `withdrawn` | `RecruitmentService::transitionApplication` |
| `hrm.leave` | `pending` | `approved`, `rejected` | `LeaveService::approve` / `reject` |
| `hrm.appraisal` | `draft` | `closed` | `PerformanceService::submit` / `review` / `close` |
| `hrm.vacancy` | `draft` | `closed`, `filled` | `RecruitmentService::publishVacancy` / `closeVacancy` |
| `hrm.employee` | `active` | `terminated` | `EmployeeService::terminateEmployee` |
| `hrm.payroll_period` | `draft` | `closed` | `PayrollService::processPeriod` / `closePeriod` |
| `hrm.quiz_attempt` | `invited` | `completed`, `expired` | `QuizService::startAttempt` / `submitAttempt` |

---

## 9. Code Numbering (Tenant-Configurable)

Auto-generated identifiers read their prefix from per-tenant settings. Admins edit them under **Settings → Numbering**. Stored values include any separator (e.g. `TT-`), so the generator concatenates directly: `{prefix}{rest}`. If the per-tenant setting is missing, null, or empty, the generator MUST fall back to its conventional default (e.g. `'TT-'` or `'CAN-'`) to guarantee business continuity. Changes only affect new records — historical codes are not rewritten.

| Entity | Setting key | Default | Format | Generator |
|---|---|---|---|---|
| Employee | `numbering.employee_id_prefix` | `TT-` | `{prefix}0001` (zero-padded, grows) | `RecruitmentService::generateNextEmployeeId` |
| Candidate / Application | `numbering.candidate_code_prefix` | `CAN-` | `{prefix}YYYYMM-NNN` (resets monthly) | `Application::generateCandidateCode` |

The Employee generator is sequential (DB `MAX+1` including soft-deleted) so terminated employees never free their numbers for reuse. The Candidate generator is month-bucketed for at-a-glance vintage.

Service contracts:
- `$statuses->initialFor($module): string` — bootstrap status when creating a record. Inject `WorkflowStatusService` into the constructor instead of hardcoding.
- `$statuses->validateTransition($module, $from, $to): void` — throws `DomainException` on invalid moves; the controller catches and returns 422.
- `$statuses->lookup($module, $key): ?WorkflowStatus` — fetch a single row (label/color/icon).
- `$statuses->flushCache()` — call after mutating the table.

Defaults are seeded by `TenantDatabaseSeeder::seedWorkflowStatuses()` (idempotent). Tenant admins can rename labels, change colors/icons, reorder, or add new statuses via `GET/POST/PUT/DELETE /api/v1/workflow-statuses`. Removing a terminal-only status is safe; removing a status that's still referenced by live records will leave those records with an unknown status — transition validation then fails fast.

### Frontend (Nuxt/PrimeVue)
- **Path**: `frontend/pages/hrm/`
- **Self-Service**: Implement a dedicated `/me` portal for employees to view payslips and apply for leave.
- **Directives**: Hide sensitive compensation data using `v-can="'hrm.payroll.read'"` or similar.
- **Candidate Assessment Portal**: Dedicated sandboxed `/candidate/quiz` route authenticating via secure magic-link token (`GET /api/v1/candidate/auth?token=...` which exchanges token for a limited JWT scope).

---

## 10. Tenant-Configurable HRM Settings

To support multi-tenant operational flexibility, all business rules, thresholds, and account mappings for the HRM module are driven by settings stored in the central `tenant_settings` table.

### Key Design and Architecture Rules:
1. **Tenant Admin Scoping & Permission Gates (P0)**: All configuration views and mutations under `/api/v1/settings` must be strictly gated.
   - **Read operations** (`GET /api/v1/settings?group=hrm`) require `settings.read`.
   - **Write/Update operations** (`PUT /api/v1/settings`) require `settings.write`.
   - **Self-Service Restriction**: Standard employee accounts (with only `.self` scope permissions) are strictly banned from directly requesting or writing raw HRM settings. They must receive only pre-computed, sanitised values in feature-specific payloads (e.g., payslips, leave balances).
2. **Database Scoping & Isolation (P0)**: Stored strictly within the tenant-isolated database's `tenant_settings` table (managed via standard `stancl/tenancy` connection switching). A settings mutation on Tenant A *never* bleeds into, edits, or alters the configurations of Tenant B ("effect self tenants").
3. **Authoritative Access**: Always retrieve settings using `SettingService::get('hrm.xxx.yyy')` with a strict `empty()` check fallback. Do NOT query `Setting::class` directly in service layers.
4. **Key Namespace**: Keys must strictly follow the `hrm.{submodule}.{setting_name}` format. The first segment auto-resolves the `group` as `hrm` (all settings belong to the `hrm` group to simplify retrieval, or sub-grouped where central tabs require).
5. **Lazy Default Seeding**: All default settings must be declared inside `SettingService::defaults()` in the `hrm` group so that they are auto-populated for new tenants upon their first setting read or settings panel access.
6. **Validation**: Any updates to settings via `PUT /api/v1/settings` must be validated against the data types (boolean, integer, string, json, array) and rules specified below.

---

### Exhaustive HRM Setting Registry

| Setting Key | Default Value | Type | Submodule | Description & Enforcement Rules |
|---|---|---|---|---|
| **Recruitment & Hiring** | | | | |
| `hrm.recruitment.probation_period_default` | `3` | `integer` | Recruitment | Default probation period in months applied during candidate-to-employee conversion when not overridden. |
| `hrm.recruitment.revert_window_days` | `7` | `integer` | Recruitment | The bounded time window (in days) inside which an admin/recruiter can revert a converted employee back to candidate status. |
| `hrm.recruitment.enable_public_careers` | `true` | `boolean` | Recruitment | Global switch to expose or hide the `/public/job-vacancies` endpoint. |
| **Leave & Time Off** | | | | |
| `hrm.leave.standard_work_week` | `[1, 2, 3, 4, 5]` | `json` | Leave | Array of active working days (1=Mon, 7=Sun). Used by `LeaveService` to calculate duration by skipping weekends/non-working days. |
| `hrm.leave.accrual_cycle_start` | `"calendar_year"` | `string` | Leave | Boundaries for leave accumulation. Allowed values: `"calendar_year"` (Jan 1), `"fiscal_year"` (Oct 1), or `"hire_date"` (anniversary). |
| `hrm.leave.allow_negative_balance` | `false` | `boolean` | Leave | When `true`, bypassed balance validation allows employees to request leave even if requested days exceed their current balance. |
| `hrm.leave.max_carryover_days` | `5.0` | `float` | Leave | Maximum number of unused leave days allowed to carry over to the next year cycle. Exceeded days are forfeited during cycle reset. |
| **Attendance & Clocking** | | | | |
| `hrm.attendance.enable_geofencing` | `false` | `boolean` | Attendance | If `true`, requires mobile clock-in/out to pass GPS coordinate validation. |
| `hrm.attendance.geofence_radius_meters` | `100` | `integer` | Attendance | Distance threshold in meters. Validated via the Haversine formula against the department office coordinate. |
| `hrm.attendance.enable_ip_whitelisting` | `false` | `boolean` | Attendance | If `true`, blocks clock-in/out requests originating from unauthorized network IP addresses. |
| `hrm.attendance.ip_whitelist` | `""` | `string` | Attendance | Comma-separated list of authorized corporate IP addresses or CIDR ranges. |
| `hrm.attendance.auto_clock_out_hours` | `12` | `integer` | Attendance | Auto-closes un-ended attendance log sessions after specified hours during reconciliation. |
| **Payroll & FMS Posting** | | | | |
| `hrm.payroll.monthly_work_hours_standard` | `160` | `integer` | Payroll | Standard work hours per month. Used to compute standard hourly rates for overtime and deductions (`Base Salary / Standard Work Hours`). |
| `hrm.payroll.default_payday` | `25` | `integer` | Payroll | Calendar day of the month for auto-generating and processing draft payroll periods (1-31). |
| `hrm.payroll.fms_posting_enabled` | `true` | `boolean` | Payroll | If `true`, closing a payroll period automatically publishes double-entry accrual transactions to the FMS ledger. |
| `hrm.payroll.account_wages_expense` | `"EXP-WAGES"` | `string` | Payroll | FMS chart of accounts code for matching salary/wage expenses. |
| `hrm.payroll.account_tax_payable` | `"LIA-TAX"` | `string` | Payroll | FMS chart of accounts code for payroll tax withholding liabilities. |
| `hrm.payroll.account_social_security_payable`| `"LIA-NSSF"` | `string` | Payroll | FMS chart of accounts code for social security (NSSF) liabilities. |
| `hrm.payroll.account_wages_payable` | `"LIA-WAGES"` | `string` | Payroll | FMS chart of accounts code for employee net salary payout liabilities. |
| **Performance Appraisals** | | | | |
| `hrm.appraisal.self_evaluation_weight` | `20` | `integer` | Performance | Weight (%) of employee self-review contribution toward the final performance score. |
| `hrm.appraisal.manager_evaluation_weight`| `80` | `integer` | Performance | Weight (%) of direct manager evaluation toward the final performance score. Sum of weights must equal 100%. |

---

### Service Enforcement Contracts

#### 1. Leave Accrual & Working Week Check
When calculating request duration in `LeaveService::calculateDuration(Employee, StartDate, EndDate)`:
```php
$workWeek = app(SettingService::class)->get('hrm.leave.standard_work_week') ?: [1, 2, 3, 4, 5];
// Parse array and filter out dates whose ISO day-of-week is not in $workWeek
```

#### 2. Leave Balance Enforcement
```php
$allowNegative = app(SettingService::class)->get('hrm.leave.allow_negative_balance') ?: false;
if (!$allowNegative && $requestedDays > $availableBalance) {
    throw new DomainException("Insufficient leave balance. Remaining: {$availableBalance} days.", 422);
}
```

#### 3. IP and Geofence Verification
Inside `/attendance/clock-in` controller action:
```php
$settings = app(SettingService::class);
if ($settings->get('hrm.attendance.enable_ip_whitelisting')) {
    $whitelist = array_filter(explode(',', $settings->get('hrm.attendance.ip_whitelist') ?: ''));
    if (!in_array($request->ip(), $whitelist)) {
        throw new DomainException("Unauthorized clock-in IP address.", 403);
    }
}
if ($settings->get('hrm.attendance.enable_geofencing')) {
    $radius = $settings->get('hrm.attendance.geofence_radius_meters') ?: 100;
    // Calculate distance and throw 422 if distance > $radius
}
```

#### 4. FMS Ledger Posting Account Resolver
```php
$settings = app(SettingService::class);
if ($settings->get('hrm.payroll.fms_posting_enabled') ?: true) {
    $accounts = [
        'expense' => $settings->get('hrm.payroll.account_wages_expense') ?: 'EXP-WAGES',
        'tax'     => $settings->get('hrm.payroll.account_tax_payable') ?: 'LIA-TAX',
        'nssf'    => $settings->get('hrm.payroll.account_social_security_payable') ?: 'LIA-NSSF',
        'payable' => $settings->get('hrm.payroll.account_wages_payable') ?: 'LIA-WAGES',
    ];
    // Map totals and dispatch postEntry() via AccountingService
}
```

### Frontend settings Integration
1. Extend the **HRM Settings** sub-section within `frontend/pages/settings/index.vue` (or as a sub-menu under Configurations > App Management > Human Resource).
2. Design custom interactive cards/tabs under the main "HRM Settings" panel:
   - **Recruitment Tab**: Inputs for probation months, revert window, and careers portal toggle. Includes explicit descriptions and guidance indicating that the recruitment pipeline's Kanban columns (stages) are driven by the Workflow Statuses settings page.
   - **Leave & Time Off**: Work week day checkboxes, negative balance toggle, accrual cycle dropdown. Includes links to manage Leave Types (`/settings/apps/hrm/leave-types`).
   - **Attendance Config**: Geofence toggle, radius slider/number input, whitelisting checkboxes.
   - **Payroll Mapping**: Dropdown selects for FMS wage/tax/payable accounts (populated from `/api/v1/fms/accounts`). Includes links to adjust Prefix Codes (`/settings/apps/hrm/prefix-code`).
   - **Performance Weighting**: Percentage sliders for Self/Manager weights ensuring total sums to `100%`.
3. **Manageable Settings Guidance**: Display clear, user-facing callouts/panels detailing what the admin can control directly on this settings page versus related configuration surfaces (e.g. Leave Types, Prefix Codes, Roles Matrix, and Kanban column/workflow statuses). Provide direct cross-links to those pages where applicable to enhance discoverability.

---

## 11. Hierarchical Work Schedules (Working Days & Hours)

To support flexible scheduling, working days and hours are configured at three hierarchical levels (Global default, Department overrides, and Employee overrides).

### A. Database Table (`work_schedules`)
Schedules are stored in a dedicated tenant-scoped table to support performance, relationships, and cascades.

```sql
CREATE TABLE work_schedules (
    id UUID PRIMARY KEY,
    tenant_id VARCHAR(255) NOT NULL,
    target_type VARCHAR(50) NOT NULL,  -- 'global', 'department', 'employee'
    target_id UUID NULL,               -- references departments(id) or employees(id)
    day_of_week INT NOT NULL,          -- 1 (Monday) to 7 (Sunday)
    is_work_day BOOLEAN DEFAULT TRUE,
    intervals JSONB NULL,              -- Array of intervals: [{"start": "08:00", "end": "12:00"}, ...]
    created_at TIMESTAMP NULL,
    updated_at TIMESTAMP NULL
);
CREATE UNIQUE INDEX work_schedules_lookup_uidx ON work_schedules(tenant_id, target_type, target_id, day_of_week);
```

### B. Inheritance Resolution
To resolve the active schedule for an Employee on any date, the system checks schedules in order:
1. **Employee Override**: Row where `target_type = 'employee'` and `target_id = employee_id`.
2. **Department Override**: Row where `target_type = 'department'` and `target_id = department_id` (if assigned).
3. **Global Default**: Row where `target_type = 'global'` and `target_id IS NULL`.

*Note: Whenever a custom override is created for a department or employee, it must persist a full set of 7 rows (one for each day of the week) to ensure consistency and avoid partial day-of-week fallbacks.*

### C. Default Global Schedule (Seeded)
On tenant initialization, the central system seeds the default global schedule:
- **Monday to Friday**: Work days. Intervals: `[{"start": "08:00", "end": "12:00"}, {"start": "13:00", "end": "17:00"}]`
- **Saturday**: Work day. Intervals: `[{"start": "08:00", "end": "12:00"}]`
- **Sunday**: Non-work day. Intervals: `[]`

### D. Service Integrations
1. **Leave Service (`LeaveService`)**: When counting working days to calculate request duration, the system replaces checks against the deprecated flat `hrm.leave.standard_work_week` setting. It queries the resolved schedule's `is_work_day` value day-by-day.
2. **Attendance Service (`AttendanceService`)**: During daily reconciliation and clock-in/out status classification, the resolved schedule for the day determines whether the day is treated as a weekend/holiday (`is_work_day = false`) or a work day where absence tracking applies.

---

## 12. Recruitment Candidate Pipeline Settings

To enable custom hiring workflows, the Recruitment module leverages tenant-configurable workflow statuses (`workflow_statuses` table, `module = 'hrm.application'`) to define the Kanban board columns (stages).

### A. Core Pipeline Configurations (Single-Level)
Administrators with `settings.write` or `hrm.recruitment.write` permission can perform the following actions:
1. **Add Stage**: Clicking "Add Stage" sends a request to create a new `WorkflowStatus` row.
   - `module`: Set to `'hrm.application'`.
   - `key`: Auto-generated unique string slugified from the name (e.g., `'phone_screen'`).
   - `label`: Admin-defined display name.
   - `sequence`: Auto-incremented (current `MAX(sequence) + 1`).
   - `is_initial` and `is_terminal`: Defaults to `false`.
2. **Edit Stage Name**: Administrators can modify the `label`, `color`, and `icon` properties of any stage.
3. **Delete Stage**: Gated by application residency validation.
   - **Validation Block**: If any active `Application` currently has its `status` set to the stage's `key`, the delete action must be rejected with a `422` error detailing the count of active applicants.
   - **Protection on Core Stages**: The core terminal stages (`hired`, `rejected`, `withdrawn`) and the initial stage are protected from deletion to safeguard basic recruitment flow integrity.
4. **Reorder Stages**: Handles drag-and-drop sorting by accepting an ordered array of stage IDs/keys and updating the `sequence` column sequentially.
5. **Set as Default (Initial Stage)**: Set the chosen stage to `is_initial = true` and reset all other `hrm.application` stages to `is_initial = false` within an atomic transaction. Only one default stage is allowed.

### B. Stage Data Configuration (`metadata` JSON)
Each Kanban column's data behavior can be customized by saving configuration options inside the `metadata` JSON field on the `WorkflowStatus` record:

| Metadata Attribute | Type | Default | Description |
|---|---|---|---|
| `order` | `string` | `"asc"` | Sort direction for the applications in this stage (`"asc"` or `"desc"`). |
| `sort_by` | `string` | `"applied_at"` | Field by which applications in the column are sorted (e.g., `"applied_at"`, `"applicant_name"`, `"rating"`). |
| `visible` | `boolean` | `true` | Show or hide the column on the main candidate Kanban board. |
| `convert_to_employee` | `boolean` | `false` | If `true`, candidates placed in this stage are eligible for candidate-to-employee conversion (default `true` only for the `'hired'` stage). |
| `show_fields` | `array` | `["email", "phone"]` | List of field keys to display on the Kanban card. |

### C. Kanban Board Integration (Frontend & API)
- The ATS Kanban API (`GET /api/v1/applications`) and columns resolver must query `WorkflowStatus::forModule('hrm.application')` and read the associated `metadata` to sort and filter applicants within each column dynamically.
- Moving a candidate between columns triggers `PATCH /api/v1/applications/{id}/status`, checking the state machine transition using `WorkflowStatusService::validateTransition`.

---

## 13. Digital Offer & Onboarding Pipeline

This feature manages the digital contract issuance, HR governance gate, and provisioning of new hires.

### A. Canonical end-to-end flow (P0)

```
Applied → Screening → Shortlisted
  → Interview (optional, settings-driven)
  → Technical Assignment (optional, settings-driven — `assessment` for quizzes, `technical_assignment` for take-homes)
  → Final Interview (optional, settings-driven)
  → Job Offer       ← Offer letter is drafted here (NOT at `hired`).
  → Hired           ← Reached automatically when the candidate accepts the offer. HR submits the appointment request here.
  → Onboarding      ← Reached automatically when the appointment request is approved. Employee record + checklist materialise here.
```

Stage-transition authority:
- `applied` → `offer`: recruiter clicks "Advance stage" (validated by `WorkflowStatusService`).
- `offer` → `hired`: event-driven by `OfferService::markAccepted` (webhook or wet-ink). Recruiters cannot manually jump here.
- `hired` → `onboarding`: event-driven by `SyncEmployeeAppointmentFromApproval` when the eApprovals request is approved. Recruiters cannot manually jump here.
- `rejected` / `withdrawn`: terminal alternatives, allowed from any non-terminal stage.

### B. Offer Lifecycle & eSignature (P0)
1. **Status Flow (`hrm.offer`)**:
   - `draft` → `sent` → `accepted` | `declined` | `expired`.
   - Offers are created in the `draft` state (`POST /api/v1/offers`). **The application MUST be in `offer` status — `OfferService::createOffer` throws `DomainException` otherwise.** Only `draft` offers can be updated (`PATCH /api/v1/offers/{id}`) or deleted (`DELETE /api/v1/offers/{id}`).
   - Compensation fields (`base_salary`, `signing_bonus`) use the `EncryptedWithFallback` cast to protect sensitive financial details at rest.
2. **Sending & eSignature Gateways**:
   - Triggered via `POST /api/v1/offers/{id}/send`. Requires choosing an eSignature provider (`mock` or `docusign`).
   - Mock provider generates a temporary signed-document simulation link. DocuSign connects to the DocuSign Envelope REST API.
   - Webhook updates flow through `POST /api/v1/offers/sign-webhook`. Gated by signature verification (`X-Signature` header matching the webhook secret).
3. **Acceptance hand-off (CHANGED — see Phase 8.5 in `.task/hrm/task.md`)**:
   - On accept (webhook or `POST /api/v1/offers/{id}/accept`), `OfferService::markAccepted`:
     - Flips the offer to `accepted` and stamps `signed_at`.
     - Transitions the Application status from `offer` → `hired` (validated through `WorkflowStatusService`).
     - **Does NOT call `convertToEmployee` and does NOT seed the onboarding checklist** — those now run only after HR appointment-request approval (§C).
   - `markAccepted` remains idempotent: a duplicate webhook call returns the existing accepted offer without re-running the application transition.
4. **Decline**:
   - `POST /api/v1/offers/{id}/decline` records a reason. Application status stays at `offer` (the recruiter can draft a replacement offer or withdraw the candidate).

### C. Appointment Request → Employee Conversion (P0)

After offer acceptance the candidate sits in `hired` until HR submits an Employee Appointment request through the eApprovals module (`POST /api/v1/employee-appointments`). On approval:

1. `App\Tenants\Modules\HRM\Listeners\SyncEmployeeAppointmentFromApproval::handle()` fires from the `ApprovalRequestFinalized` event.
2. Inside one DB transaction the listener:
   - Calls `RecruitmentService::convertToEmployee($application, $overrides)` with the appointment's editable fields (name, dept, position, salary, start date, employment type).
   - Links the accepted `Offer` (if any) to the new employee by setting `offers.employee_id`.
   - Seeds the default onboarding checklist via `OnboardingService::seedDefaultChecklist()`.
   - Transitions the Application status from `hired` → `onboarding`.
   - Updates the appointment row with `employee_id`, `status = approved`, and `processed_at`.
3. Listener failures (DomainException) are logged via `Log::warning` so the approval action HTTP response is never 500'd after the decision is recorded.

Rejected appointment requests flip the appointment to `rejected` and leave the application in `hired` (HR can resubmit with corrected payload).

### D. Onboarding Checklist
- Materialised by `OnboardingService::seedDefaultChecklist(Offer)` — the listener loads the application's most recent accepted offer and passes it in. If no offer exists (e.g. an admin appointment without an offer letter), the service should still accept the call via an overload that derives the effective date from the appointment row; until that overload ships, appointments without an offer must be handled manually.
- Default template ships 11 tasks spanning HR / IT / Finance / Manager / Facilities owners with offsets `-7..+30` days relative to the offer's effective date.
- Task progression follows `pending` → `in_progress` → `completed` | `skipped`, driving the parent checklist `progress_percent`.

### E. Frontend UI Integration Specs
1. **Offers Directory (`/hrm/offers`)**: paginated, searchable grid of all offers. Shows reference number (`OFR-YYYYMM-NNN`), candidate, position, effective date, salary (gated by `hrm.payroll.read`), status badge. Action menu: open candidate offer tab, delete draft, manual accept, decline.
2. **Onboarding Workspace (`/hrm/onboarding`)**: master/detail layout. Left rail lists checklists with progress bars; right pane groups the selected checklist's tasks by owner role and exposes complete/skip buttons.
3. **Candidate Profile Offer Tab** (`frontend/pages/hrm/recruitments/candidates/[id].vue`):
   - Gated by `hrm.recruitment.offer`. Auto-selected when the candidate is at `offer` or beyond and no draft exists.
   - `canDraftFromStage` is **`status === 'offer'` only** (the previous `offer | hired` shortcut is removed — drafting at `hired` is no longer possible).
   - `draftOfferShortcut` no longer auto-advances the application to `hired`; the recruiter's only path to `hired` is offer acceptance.
   - At `hired`, the tab surfaces the appointment-request CTA (re-using the existing eApprovals form) and shows the accepted offer's metadata.
   - At `onboarding`, the tab shows the live checklist (overall progress + per-owner-role tiles) plus a "View Employee" link.




