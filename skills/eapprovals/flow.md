# eApprovals Workflow Flow

## 1. Forms
*(Show all forms for users)*
This section allows users to view and select dynamic forms based on the module (e.g., Leave Request, Overtime Request, Expense, Petty Cash, Personal). The forms are dynamic and adapt to the requested type.

```mermaid
graph TD
    A[User accesses Forms Portal] --> B[Select Form Type]
    B --> C[Fill Form Data]
    C --> D[Submit Request]
```

## 2. Requests
*(For User)*
This section allows users to track the status of their submitted requests, view history, and handle any returned/sent back requests.

```mermaid
graph TD
    A[View My Requests] --> B{Status?}
    B -- Pending --> C[Wait for Approval]
    B -- Approved --> D[View Final Status & Side-Effects]
    B -- Rejected --> E[View Rejection Comments]
    B -- Sent Back --> F[Edit and Resubmit]
```

## 3. Approvals
*(For Manager: has permission to approve, reject)*
This section is for managers (or designated approvers) to review pending requests, view details, and make decisions.

```mermaid
graph TD
    A[Review Portal: Pending Approvals] --> B[Open Request Details]
    B --> C{Manager Decision?}
    C -- Approve --> D[Check Multi-level Policy]
    C -- Reject --> E[Require Comments & Reject]
    C -- Send Back --> F[Require Comments & Return to User]
    
    D -- Next Level Exists --> G[Notify Next Approver]
    D -- Final Level --> H[Trigger Module Side-Effects & Finalize]
```

## Overall System Integration Flow

```mermaid
graph TD
    A[User: Submit Form] --> B[System: Identify Workflow Policy]
    B --> C[System: Notify Level 1 Approver]
    C --> D{Approver: Decision}
    D -- Reject --> E[System: Notify Requester Rejected]
    D -- Send Back --> F[System: Notify Requester for Edit]
    D -- Approve --> G{Multi-level?}
    G -- Yes --> H[System: Notify Next Level Approver]
    H --> D
    G -- No --> I[System: Final Approval]
    I --> J[System: Trigger Module Side-Effects]
```
