# Data Safety & Privacy Rules

## 1. Agent & Developer Safety Rules (Workspace & AI Context)
These rules apply to AI agents and developers working on the repository. They prevent the exposure of credentials, secrets, and private user information.

### 1.1 Credential & Secret Hygiene
- **No Hardcoded Secrets**: Secrets, credentials, salts, private keys, or API tokens must never be written directly into code files. Use `.env` and `config()` files.
- **Gitignore Enforcement**: Maintain robust `.gitignore` rules. Verify that `.env`, `.env.production`, `*.key`, `/vendor`, and `/node_modules` are excluded.
- **Safe Commits**: Always check unstaged changes using `git diff` before committing to ensure no sensitive files or temporary config bypasses are added.

### 1.2 Development Data Rules
- **Mock Data Only**: Use seeders (`database/seeders`) and fake data generators (Faker) for development and testing. Do not use real company or employee information.
- **No Automatic Database Deletion**: The AI agent must never automatically delete, wipe, truncate, or drop databases/tables (e.g., executing `db:wipe`, `migrate:fresh`, or deleting database records/files) without asking the user 2-3 times to verify and explicitly confirm.
- **Sanitized Context**: If logs or code structures containing actual names, emails, or phone numbers are shared in conversations or bug reports, they must be anonymized or redacted first (e.g., `user@example.com`, `John Doe`).
- **Local Isolated Ports**: Local dev servers (like Vite/Nuxt on port `3000` or Laravel on port `8000`) must be bound to localhost to prevent local data exposure on shared networks.

---

## 2. User & Runtime Data Safety Rules (Production ERP Environment)
These rules dictate how the system handles, isolates, and protects active user data inside the application.

### 2.1 Multi-Tenant Isolation (P0 Security)
- **Separate Database Connections**: The active connection must switch dynamically for each request. No cross-database queries between tenants are allowed.
- **Tenant Scope Enforcement**: Every tenant-owned database table and model must be isolated using the `BelongsToTenant` trait. Any model querying tenant data must default to the active tenant scope.
- **Access Authorization**: Enforce strict authorization checks (Laravel Policies, Gates) on all controller endpoints to prevent IDOR (Insecure Direct Object Reference) attacks.

### 2.2 Sensitive Data Encryption
- **Encryption at Rest**: Sensitive client data, including payroll records, compensation details, government identifiers (e.g., SSN, tax numbers), and bank account details, must be encrypted in PostgreSQL using Laravel's native encryption services.
- **Hashing Passwords**: Passwords must always be hashed using secure algorithms (e.g., `Bcrypt` or `Argon2id`) before saving to the database.

### 2.3 System Logging & Audit Trails
- **Sanitized Logging**: Ensure the Laravel log handlers redact sensitive fields such as `password`, `password_confirmation`, `credit_card`, and PII.
- **Safe Audits**: When using the `Auditable` trait on models, track only the meta changes (e.g., "Updated salary", updated fields list) without logging the exact plain-text sensitive values.

### 2.4 Session & Token Security
- **Secure Cookies**: Session and CSRF cookies must be configured with `HttpOnly`, `Secure`, and `SameSite=Lax` or `SameSite=Strict` attributes to mitigate cross-site scripting and request forgery.
- **Immediate Revocation**: Upon user logout, all active OAuth tokens (Laravel Passport `access_token` and `refresh_token`) must be immediately marked as revoked in the database.
