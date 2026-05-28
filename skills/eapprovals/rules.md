# eApprovals Workflow Rules

## 1. Permissions (IAM Integration)
Permissions follow the standard `module.feature.action` pattern defined in [iam.md](../iam.md), with extended granular controls for individual forms.

### Granular Form Permissions
eApprovals forms must have their own specific permission controls. These permissions must be controllable by an Admin or a specifically assigned Employee (allowing selection of users from the Employee directory). Each form must explicitly define:
- **who can request** (Initiate the form)
- **who can see** (View the form and its data)
- **who can verify** (Review and validate the request before final approval)
- **who can reject** (Decline the request)
- **who can approve** (Finalize and accept the request)

### Permission Keys:
- **Module**: `approvals`
- **Actions**: `read` (see), `write` (request), `delete`, `execute` (verify/approve/reject)

### Feature Matrix:
| Feature | Read | Write | Execute | Export |
|---------|------|-------|---------|--------|
| `requests` | `approvals.requests.read` | `approvals.requests.write` | - | `approvals.requests.export` |
| `actions` | `approvals.actions.read` | - | `approvals.actions.execute` | - |
| `workflows` | `approvals.workflows.read` | `approvals.workflows.write` | `approvals.workflows.delete` | - |

## 2. Implementation Standards

### Approval Engine Flow
1. **Submission**: Requester submits module-specific data.
2. **Policy Match**: Identify approvers based on tenant rules.
3. **Notification**: Send real-time alerts to current level approvers.
4. **Decision**: Process Approve/Reject with comments.
5. **Progression**: Advance to next level or finalize.
6. **Side-Effect**: Notify origin module and requester of final status.

### Backend (Laravel)
- **Namespace**: `App\Tenants\Modules\Approvals`
- **State Machine**: Use a state machine pattern for approval flows (Pending -> Approved/Rejected).
- **Notifications**: Trigger WebSocket and Email notifications on status change.
- **Audit**: Every approval step must be logged with timestamp and user handle.

### Frontend (Nuxt/PrimeVue)
- **Path**: `src/modules/approvals/`
- **Components**: Timeline component for visualizing approval history.
- **UI**: Badge indicators for pending approvals count.
