# Feature: Unified Calendar and Holiday Management

## Overview

The Unified Calendar and Holiday Management module is the centralized scheduling hub for the multi-tenant ERP system. It serves as the primary system of record for public, regional, and company-wide holidays, while dynamically aggregating and visualizing operational schedules across the entire workforce. The calendar provides a consolidated timeline view that displays company holidays, employee leave blocks (days off), assigned shifts, and crm appointments.

Crucially, the module resolves a primary gap in workforce operations by introducing the public holiday calendar, which feeds directly into the attendance reconciler, overtime rating calculations, and payroll accrual engines.

---

## Module Taxonomy

The Calendar menu surface (top-level sidebar group "calendar") divides scheduling and compliance operations into four main areas:

### 1. Company Holidays Registry
The policy and compliance control center.
- **Holidays Master List**: Administrative dashboard to register national, religious, or company-specific holidays, specifying whether they are recurring or single-occurrence dates.
- **Overtime Rate Multipliers**: Configuration to define custom rate multipliers for employees working on holidays (default: 3.0x pay as mandated by labor regulations).
- **Regional Groupings**: Ability to associate holidays with specific branches, departments, or geographical locations (critical for local vs. national holiday compliance).

### 2. Employee Days Off and Leaves Preview
The workforce availability lens.
- **Approved Leaves Aggregator**: Real-time integration that queries approved leave requests (annual, sick, parental, unpaid) and maps them visually as time blocks on the calendar.
- **Privacy Masking**: Ensures that sensitive medical/sick leave details are masked (rendered as "Leave - Confirmed") to general employees, exposing detailed reasons only to HR managers.
- **Self-Service Availability**: Personal dashboard for regular employees to preview their teammates' upcoming days off to optimize collaboration and shift coverages.

### 3. Work Shifts and Roster Previews
The operational schedule lens.
- **Shift Roster Timeline**: Chronological view displaying assigned shifts, roster patterns, and active work hours per employee.
- **Schedule Swaps Tracker**: Affordance displaying shift transfers, temporary schedule overrides, and roster adjustments.
- **Grace Period Indicators**: Visual indicators showing standard check-in thresholds and shifts boundaries.

### 4. Interactive Unified Calendar (Unified Dashboard)
The high-performance scheduling interface.
- **Multi-Layout Calendar Grid**: Responsive grid supporting monthly, weekly, daily, and agenda layouts, optimized for drag-and-drop actions using PrimeVue calendar widgets.
- **Cross-Module Filter Bar**: Dynamic controls to toggle visibility of specific schedule layers (Holidays, Leaves, Shifts, CRM Appointments).
- **Entity Side Drawer**: Clicking any calendar event pulls out a side panel displaying detailed metadata, linked documents, and custodian details without a page reload.

---

## Cross-Module Integration Contract

The Calendar module coordinates calculations and schedules across several enterprise systems:

1. **HRM Module (Workforce & Leave)**:
   - Queries the `leaves` database table to display approved leaves.
   - Updates the `attendance_logs` logic: the daily attendance reconciler job queries active holidays; if an employee is absent on a holiday, the status resolves to "holiday" instead of "absent".

2. **Payroll & Overtime Engines (FMS Integration)**:
   - The `OvertimeService` resolves rate calculations using the holiday registry. If an overtime log lands on a registered holiday, the multiplier escalates to the configured rate (e.g. 3.0x) instead of the standard weekend rate (2.0x).
   - The `PayrollService` deducts holidays from standard monthly workday counts during period close.

3. **CRM Module (Client Schedules)**:
   - Cross-references `crm_appointments` and displays client meetings and sales schedules in the unified calendar view.
