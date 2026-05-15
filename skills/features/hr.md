# Feature Rules: Human Resources (HRM)

## Overview
The HRM module manages the complete employee lifecycle, from recruitment and onboarding to payroll, attendance, and performance management. It serves as the single source of truth for all human capital data.

## 1. Functional Modules

### Core HR & Employee Profile
- **Org Management**: Maintain company structure, departments, and positions.
- **Profile Management**: Personal details, emergency contacts, skills, and assets.
- **Employment History**: Versioned tracking of promotions, salary changes, and contracts.

### Attendance & Leave Management
- **Time Tracking**: Biometric integration and mobile check-in with GPS validation.
- **Leave Workflows**: Configurable multi-level approval for vacation, sick leave, and special leave.
- **Holiday Calendar**: Global and location-specific public holidays.

### Payroll & Compensation
- **Salary Engine**: automated calculation based on attendance, overtime, and deductions.
- **Tax & Compliance**: Regional tax rules, social security (NSSF), and pension contributions.
- **Payslips**: Secure generation and digital distribution via the employee portal.

### Recruitment & Onboarding
- **ATS**: Job postings, applicant tracking, and interview scheduling.
- **Digital Onboarding**: Electronic signing of contracts and automated equipment provisioning.

## 2. Technical Requirements

### Database Schema
- **EmployeeModel**: Use `Encrypted` trait for `base_salary`, `bank_account`, and `national_id`.
- **EmploymentContract**: Store as secure binary objects in a tenant-isolated S3 bucket.
- **AttendanceLogs**: Partition tables by month/year for performance at scale.

### Service Layer Logic
- **PayrollService**: Must be idempotent. Partial runs or recalculations must handle previously generated entries.
- **LeaveService**: Real-time balance calculations factoring in accrual rules and carry-overs.

### API Integration
- **Biometric Sync**: Webhook-based or scheduled pull from on-premise hardware via a secure bridge.
- **Bank Export**: Generate standard ISO 20022 or local CSV formats for bulk salary transfers.

## 3. UI/UX Standards
- **Employee Portal**: Mobile-first design for self-service actions (Leave request, Payslip download).
- **Admin Dashboard**: Real-time headcount analytics, turnover rates, and attendance heatmaps.
- **Org Chart**: Interactive hierarchy using PrimeVue `OrganizationChart` with "Breadcrumb" navigation.

## 4. Security & Privacy
- **RBAC**: Strict separation between "HR Admin" (Full access) and "Payroll Admin" (Salary only).
- **Audit Logs**: Mandatory logging for every Read/Write action on sensitive employee data (GDPR/Compliance).
- **Verification**: Session-based secondary OTP/Password for final payroll execution and mass salary exports.
- **Isolation**: Tenant data isolation must be verified at the query level for all HR models.
