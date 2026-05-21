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

This diagram shows how leave requests interact with the centralized `eApprovals` engine and enforce balance checks.

```mermaid
flowchart TD
    A[Employee Submits Leave Request] --> B{Validate Request Details}
    B -->|Overlap / Invalid Dates| C[Return 422 Error]
    B -->|Valid Dates| D{Check Available Balance}
    
    D -->|Insufficient Balance| C
    D -->|Sufficient Balance| E{Central approval workflow configured for hrm.leave?}
    
    %% Centralized Workflow Path
    E -->|Yes| F[Create Leave with status = 'pending']
    F --> G[Dispatch ApprovalRequest to eApprovals Engine]
    G --> H[Lock Requested Days in available balance calculations]
    H --> I{Approval Processed?}
    
    I -->|Rejected| J[Flip Leave status to 'rejected' via Listener]
    J --> K[Release balance lock]
    I -->|Approved| L[Flip Leave status to 'approved' via Listener]
    L --> M[Deduct days permanently from YTD Allowance]
    
    %% Legacy Fallback Path
    E -->|No| N[Create Leave with status = 'pending']
    N --> O[Wait for direct Admin Approve/Reject call]
    O -->|Admin Direct Action| I
    
    style C fill:#fee2e2,stroke:#ef4444,stroke-width:1px;
    style L fill:#d1fae5,stroke:#10b981,stroke-width:1.5px;
    style J fill:#f3f4f6,stroke:#9ca3af,stroke-width:1px;
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
