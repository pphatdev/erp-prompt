# HRM Time Off & Attendance: Workflows

This document visualizes the runtime workflows for clock-in geofencing, leave request approval routing, and daily attendance reconciliation.

---

## 1. Clock-In & Geofence Verification Flow

This workflow illustrates how the system validates a check-in request against IP Whitelists and GPS boundaries.

```mermaid
sequenceDiagram
    autonumber
    actor Employee as Staff (Client)
    participant API as API Gateway (v1/attendance/clock-in)
    participant DB as Tenant Database
    participant Geo as Geofence Service

    Employee->>API: POST clock-in (lat, lon, IP)
    API->>DB: Query Tenant Settings & Shift Details
    DB-->>API: Return Whitelisted IPs, Office Coordinates, Active Shift

    alt IP Whitelisting Enabled
        API->>API: Verify Client IP against Whitelisted Ranges
        alt IP Unauthorized
            API-->>Employee: Response 422 (Unauthorized Network)
        end
    end

    alt GPS Geofencing Enabled
        API->>Geo: Calculate distance (Client Lat/Lon vs. Office Lat/Lon)
        Geo-->>API: Return Distance in meters (d)
        alt Distance (d) > Threshold (e.g., 100m)
            API-->>Employee: Response 422 (Out of Geofence bounds)
        end
    end

    API->>API: Calculate Clock Status (Clock Time vs. Shift Start Time)
    alt Time <= Shift Start + Grace Period
        Note over API: Status = "present"
    else Time <= Shift Start + Half-Day Threshold
        Note over API: Status = "late"
    else Time > Shift Start + Half-Day Threshold
        Note over API: Status = "half_day"
    end

    API->>DB: Insert/Update attendance_logs (check_in, status, audit info)
    DB-->>API: Row Confirmed
    API-->>Employee: Response 201 (Clocked In successfully, status)
```

---

## 2. Leave Request & eApprovals Integration Flow

This diagram shows how leave requests calculate duration via the employee's work schedule, verify entitlement balances in `employee_leave_allocations`, and lock pending days during approval.

```mermaid
flowchart TD
    A[Employee Submits Leave Request] --> B{Verify Dates & Allocation}
    B -->|Overlap / No Allocation| C[Return 422 Error]
    B -->|Active Allocation Exists| D[Calculate Request Duration via Work Schedule]
    
    D --> E[Iterate Date Range & Sum Daily Scheduled Hours / Standard Hours]
    E --> F{Check Available Balance}
    
    F -->|Insufficient Balance| C
    F -->|Sufficient Balance| G{Central approval workflow configured for hrm.leave?}
    
    %% Centralized Workflow Path
    G -->|Yes| H[Create Leave with status = 'pending']
    H --> I[Dispatch ApprovalRequest to eApprovals Engine]
    I --> J[Lock Days: Add duration to allocation.pending_days]
    J --> K{Approval Processed?}
    
    K -->|Rejected| L[Flip status to 'rejected' via Listener]
    L --> M[Release Lock: Subtract duration from allocation.pending_days]
    K -->|Approved| N[Flip status to 'approved' via Listener]
    N --> O[Deduct: Subtract duration from pending_days & add to used_days]
    
    %% Legacy Fallback Path
    G -->|No| P[Create Leave with status = 'pending']
    P --> Q[Wait for direct Admin Action]
    Q -->|Admin Direct Action| K
    
    style C fill:#fee2e2,stroke:#ef4444,stroke-width:1px;
    style N fill:#d1fae5,stroke:#10b981,stroke-width:1.5px;
    style L fill:#f3f4f6,stroke:#9ca3af,stroke-width:1px;
```

---

## 3. Daily Reconciliation & Payroll Integration

This flowchart illustrates the daily processing of work schedules and how the result affects monthly payroll calculations.

```mermaid
flowchart TD
    %% Reconciliation Phase
    Cron[Reconciliation Job: Everyday at 01:00 AM] --> ActiveStaff[Query all active Employees]
    ActiveStaff --> Iterate[Iterate Employee]
    Iterate --> Shift[Lookup Assigned Shift for date]
    Shift --> Logs{Check Clock-in Logs?}
    
    Logs -->|Yes| CheckTimes[Reconcile check-in/out vs. Shift hours]
    CheckTimes --> WriteStatus[Save resolved status: present, late, early_out, half_day]
    
    Logs -->|No| CheckLeave{Check Approved Leaves?}
    CheckLeave -->|Yes| LeaveStatus[Save status: paid_leave or unpaid_leave]
    CheckLeave -->|No| CheckHoliday{Check Holiday / Weekend?}
    CheckHoliday -->|Yes| HolidayStatus[Save status: weekend or holiday]
    HolidayStatus --> Iterate
    CheckHoliday -->|No| AbsentStatus[Save status: absent]
    
    WriteStatus & LeaveStatus & AbsentStatus --> Iterate
    
    %% Payroll Run Phase
    Iterate -->|All Processed| Done[Reconciliation Complete]
    Done --> PayrollRun[Payroll Period Closed by HR Manager]
    PayrollRun --> PayrollService[Query Attendance Summary for Period]
    
    PayrollService --> CalcDeduction[Apply Deductions: absent & unpaid_leave days]
    PayrollService --> CalcOT[Apply Earnings: approved overtime_requests]
    CalcDeduction & CalcOT --> Payslip[Generate Payslip & post Balanced General Ledger entry]
```
