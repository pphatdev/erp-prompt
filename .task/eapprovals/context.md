# Context: eApprovals

## Overview
A centralized workflow engine that manages approval processes for all modules, ensuring compliance and transparency. Supports dynamic multi-stage paths, atomic transitions, and real-time notifications.

## Key Concepts
- **Workflow Engine**: Manages rules and steps (e.g., Manager -> HR -> Finance) dynamically.
- **State Machine**: Transitions must be atomic (Pending -> Approved/Rejected) and trigger side-effects in origin modules.
- **IAM Integration**: Follows `module.feature.action` (e.g., `approvals.requests.read`).
- **Audit & Compliance**: Every step must log actor handle and timestamp. Use `Auditable` trait.
- **Tenancy**: Strictly isolated per tenant. All APIs must validate tenant context.

## Implementation Phases
### Phase 1: Workflow Engine Schema
- Create migrations for `approval_workflows`, `approval_levels`, `approval_requests`, and `approval_history`.
- Implement models with `BelongsToTenant` and `Auditable`.
- Support polymorphic relationships to link requests to any module (HRM, Sales, etc.).

### Phase 2: Core Approval Engine
- Implement `ApprovalService` using a State Machine pattern.
- Create logic for dynamic approval chains based on requester hierarchy or request value.
- Implement notification triggers for approvers.

### Phase 3: Action & Decision Logic
- Implement `ApprovalActionController` for Approve/Reject/Send Back actions.
- Enforce `approvals.actions.execute` permission checks.
- Ensure all decisions include mandatory comments and timestamps.

### Phase 4: Integration API
- Create `ApprovalRequestResource` with a detailed history timeline.
- Implement webhooks or internal events to notify originating modules of status changes.
- Create `WorkflowController` for managing approval chain definitions.

### Phase 5: QA & Workflow Integrity Testing
- P0 Sequential Approval tests (Assert that Level 1 must approve before Level 2).
- P0 Security tests (Assert that non-approvers cannot execute actions).
- P1 Polymorphic link verification tests.

## References
- `skills/eapprovals/overview.md`
- `skills/eapprovals/flow.md`
- `skills/eapprovals/rules.md`
- `skills/eapprovals/skill.md`
- `skills/eapprovals/testing.md`
