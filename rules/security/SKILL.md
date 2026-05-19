---
name: data-safety-and-privacy
description: Enforce strict data safety, security protocols, and PII protection for both developers/AI agents and end-users.
---
# Skill: Data Safety & Privacy

## Context
Use this skill when handling database connections, configuring logging, managing PII (Personally Identifiable Information), or implementing encryption and tenant isolation. It ensures that the system maintains absolute data privacy, complies with global data protection regulations (GDPR, CCPA), and protects against unauthorized access.

## Guidelines

### 1. Workspace & AI Agent Safety (Local Dev)
- **Zero Real Data Policy**: Under no circumstances should real production data, customer databases, or actual tenant files be loaded into development environments or shared in AI agent prompt contexts.
- **No Auto-Delete**: The AI agent must avoid automatic deletion of databases, tables, files, or records. The agent must verify and confirm with the user 2-3 times before doing any database deletion or destructive commands.
- **Credential Protection**: Never commit environment files (`.env`), API keys, OAuth secrets, private keys, or passwords. Verify they are excluded via `.gitignore` before committing.
- **Data Anonymization**: When testing features locally, use database seeders to generate fake, mock data. If debugging a production issue, sanitize all sensitive information first.

### 2. Multi-Tenant Data Isolation (P0)
- **Database Switching**: Verify that all tenant-facing models use the `BelongsToTenant` trait to enforce scoped database connections.
- **Domain/Header Verification**: Ensure the tenant check middleware validates the `X-Tenant-Handle` header or subdomain before returning database records.
- **Cross-Tenant Prevention**: Run tenancy isolation tests to ensure Tenant A cannot access Tenant B's data via URL parameter modifications (IDOR).

### 3. PII & Sensitive Information Protection
- **Field-Level Encryption**: Encrypt fields containing sensitive user information (e.g., national IDs, tax numbers, salaries, passwords) at rest in the database.
- **Log Sanitization**: Implement log filtering to automatically mask passwords, credit cards, and PII in system logs (`storage/logs/laravel.log`).
- **Audit Ledger Integrity**: Audit logs must record action history (who, when, what changed) without containing the actual raw passwords or sensitive credentials.

### 4. Client Session & Token Security
- **Secure Storage**: Cookies storing session IDs or authentication states must use `HttpOnly`, `Secure`, and `SameSite=Lax/Strict` attributes to protect against XSS and CSRF.
- **Token Expiry & Revocation**: Ensure OAuth access tokens are short-lived. Always revoke active access/refresh tokens upon session logout or tenant deactivation.

## Best Practices
- **Mocking**: Use Pest factories and Faker for test case datasets instead of actual system entries.
- **Data Retention Policies**: Implement automatic cleanups for expired uploads or soft-deleted records based on the tenant's compliance rules.
- **Access Logs**: Log all failed login attempts, unauthorized endpoint access, and cross-tenant attempts.

## Troubleshooting
- **Data Leakage Detected**: Immediately suspend the compromised tenant connection, check model traits, and review database queries for missing tenant scopes.
- **Leaked Secrets**: Revoke the leaked key immediately, update all active instances, and regenerate credentials in a secure manner.
