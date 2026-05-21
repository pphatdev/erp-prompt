# Employee Role — Authentication & Self-Service Flow

## 1. Role-Mail Login Flow

```mermaid
sequenceDiagram
    participant E as Employee Browser
    participant FE as Nuxt Frontend
    participant MW as Tenant Middleware
    participant AC as AuthController
    participant PP as Laravel Passport
    participant DB as Tenant DB

    E->>FE: Navigate to login page
    FE->>E: Render login form (email + password)
    E->>FE: Submit employee.01@acme.com / tt@126$Kh#
    FE->>MW: POST /api/v1/auth/login<br/>X-Tenant-Handle: acme
    MW->>DB: Switch DB connection → tenant_acme
    MW->>AC: Forward validated request
    AC->>DB: SELECT * FROM users WHERE email = 'employee.01@acme.com'
    DB-->>AC: User row (is_active=true)
    AC->>PP: Grant password_grant token (client_id=33)
    PP-->>AC: {access_token, refresh_token, expires_in}
    AC->>DB: Eager-load roles + permissions
    AC-->>FE: 200 {user, roles:[employee], access_token, ...}
    FE->>FE: Store token in HttpOnly cookie / secure memory
    FE->>E: Redirect → /self-service/dashboard
```

## 2. Self-Service Resource Access Flow

```mermaid
flowchart TD
    A([Employee Makes Request]) --> B{Token Valid?}
    B -- No --> C[401 → Redirect to Login]
    B -- Yes --> D{Tenant Context Set?<br/>X-Tenant-Handle present?}
    D -- No --> E[400 → Missing tenant header]
    D -- Yes --> F{Permission Check<br/>module.feature.action}
    F -- Denied --> G[403 Forbidden]
    F -- Allowed --> H{Ownership Check<br/>Policy: user.employee_id == resource.employee_id}
    H -- Mismatch --> I[403 Forbidden — Not Your Record]
    H -- Match --> J[Execute Action]
    J --> K[Write Audit Log Entry]
    K --> L[Return JSON Response]
```

## 3. Ownership Scoping Decision Tree

```mermaid
flowchart LR
    REQ([API Request: GET /employees/uuid]) --> A{Has permission<br/>hrm.employee.read?}
    A -- Yes --> B[Return requested employee record]
    A -- No --> C{uuid == logged-in user's<br/>employee_id?}
    C -- Yes --> D[Return own profile]
    C -- No --> E[403 Forbidden]
```

## 4. Role Mail Email Derivation

```mermaid
flowchart LR
    P[Position Slug<br/>e.g. frontend, backend] --> E["frontend.01 @ gmail .com"]
    S[Sequence Number<br/>e.g. 01] --> E
    T[Tenant / Mail Domain<br/>e.g. gmail.com] --> E
```
