# IAM Module Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md).

### Permission Keys:
- **Module**: `iam`
- **Actions**: `read`, `write`, `delete`, `export`

### Feature Matrix:
| Feature | Read | Write | Delete | Export |
|---------|------|-------|--------|--------|
| `tenants` | `iam.tenants.read` | `iam.tenants.write` | `iam.tenants.delete` | `iam.tenants.export` |
| `users` | `iam.users.read` | `iam.users.write` | `iam.users.delete` | `iam.users.export` |
| `roles` | `iam.roles.read` | `iam.roles.write` | `iam.roles.delete` | `iam.roles.export` |
| `permissions` | `iam.permissions.read` | `iam.permissions.write` | - | `iam.permissions.export` |
| `audit` | `iam.audit.read` | - | - | `iam.audit.export` |

### Reporting Permissions (used by the `dashboard_viewer` role)
| Feature | Read | Write | Delete | Export |
|---|---|---|---|---|
| `dashboard` | `reporting.dashboard.read` | `reporting.dashboard.write` | `reporting.dashboard.delete` | `reporting.dashboard.export` |

### Seeded System Roles
| Slug | Name | Purpose | Permissions |
|---|---|---|---|
| `admin` | Administrator | Full access; short-circuits all policy checks via `Gate::before` in `TenantServiceProvider`. | All seeded permissions |
| `employee` | Employee | Standard self-service portal. Cannot read other employees' rows. | `hrm.*.read.self` / `hrm.*.write.self` set — see [`hrm/rules.md`](../hrm/rules.md) |
| `dashboard_viewer` | Dashboard Viewer | Stakeholder/auditor visibility into analytics. Read-only with export capability — cannot create, edit, or delete dashboards/widgets. | `reporting.dashboard.read`, `reporting.dashboard.export` |

### Permission Scoping (Admin vs. `.self`)

Permissions follow `module.feature.action[.scope]`. The base form is the **admin** grant — it unlocks any row in the tenant. A `.self` suffix scopes the grant to rows that belong to the authenticated caller, enforced by the matching Policy.

| Form | Example | Semantics |
|---|---|---|
| `module.feature.action`      | `hrm.leave.read`      | Admin — read any leave row |
| `module.feature.action.self` | `hrm.leave.read.self` | Self — read only own leave rows |

**Convention rules** (apply consistently across every module):

1. **Policies check both**. The pattern is `if (ownsRow($user, $row) && $user->hasPermission('feature.action.self')) return true; return $user->hasPermission('feature.action');`. The order matters — `.self` is the short-circuit, the admin grant is the fallthrough.
2. **`.self` is granted to the seeded `employee` role**; admin grants live on `admin`, `hr_manager`, `finance_manager`, etc.
3. **`.self` never substitutes for admin on directory/listing endpoints**. Controllers serving both audiences must force-filter the query by caller's owning id when the caller only has `.self`. See `PayslipController::index` and `LeaveController::index` for the canonical pattern.
4. **Wildcards are not supported**. `User::hasPermission()` does a literal slug match — every permission must be seeded explicitly. Adding `hrm.foo.read` does NOT automatically grant `hrm.foo.read.self`.
5. **Write-side `.self` should pair with a restricted Request class** so a self-service endpoint can never widen the payload to sensitive fields. See `UpdateEmployeeSelfRequest` — it whitelists 3 fields and blocks the rest at validation time, regardless of policy outcome.

## 2. Implementation Standards

### Core Authentication Flow
1. **Credentials Verification**: Authenticate via Passport.
2. **MFA Challenge**: Require OTP for admin/finance roles.
3. **Tenant Context**: Set global `tenant_id` scope for all subsequent queries.
4. **Authorization**: Check `module.feature.action` before execution.
5. **Audit**: Log activity details (actor, timestamp, payload).

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\IAM`
- **Service Layer**: Use `TenantService.php`, `UserService.php`, `RoleService.php`.
- **Security**: Critical permission changes MUST require OTP verification.
- **Logging**: All changes to roles/permissions MUST be recorded in `audit_logs`.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/iam/`
- **Directives**: Use `v-can` to restrict access to the Admin Dashboard.
- **Security**: Sensitive tokens must be stored in HttpOnly cookies.
