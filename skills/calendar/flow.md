# Unified Calendar and Holiday Workflows

This document maps the operational cashier lifecycles, real-time sync systems, and financial posting pipelines of the Unified Calendar and Holiday Management module using visual Mermaid diagrams.

---

## 1. Unified Event Compilation Flow

This flowchart describes how the `CalendarEventService` aggregates and compiles scheduling data across different databases and modules into a unified, filtered feed for the frontend.

```mermaid
graph TD
    Start([GET /api/v1/calendar/events]) --> Auth{Validate session<br/>and tenant handle}
    Auth -- No --> Err401[Return 401 Unauthorized]
    Auth -- Yes --> CheckParams{Validate start_date<br/>and end_date?}
    
    CheckParams -- No --> Err422[Return 422: Date range required<br/>and max limit is 90 days]
    CheckParams -- Yes --> QueryHolidays[Query holidays in date range]
    QueryHolidays --> QueryLeaves[Query approved leaves in range]
    QueryLeaves --> QueryShifts[Query active shifts in range]
    QueryShifts --> QueryCRM[Query CRM appointments in range]
    
    QueryCRM --> CompileEvents[Aggregate into flat array]
    CompileEvents --> FilterLayers{Filter out layers<br/>based on query parameters?}
    FilterLayers -- Yes --> ApplyFilters[Strip unselected layers]
    FilterLayers -- No --> MaskPrivacy[Apply Privacy Masking:<br/>Mask leaves if user lacks permission]
    
    ApplyFilters --> MaskPrivacy
    MaskPrivacy --> FormatResource[Serialize into camelCase CalendarEventResource]
    FormatResource --> Return200([Return 200 OK with compiled array])
```

---

## 2. Compensatory Day Resolution Flow

This flowchart traces the logic executed when a registered holiday lands on a weekend date, showing how a compensatory day is dynamically provisioned.

```mermaid
graph TD
    StartComp([Holiday Registration]) --> CheckWeekend{Does holiday date<br/>land on Sat or Sun?}
    CheckWeekend -- No --> SaveHoliday[Save Holiday Model]
    
    CheckWeekend -- Yes --> CheckSetting{Is compensatory_day setting<br/>enabled for tenant?}
    CheckSetting -- No --> SaveHoliday
    
    CheckSetting -- Yes --> FindMonday[Calculate adjacent Monday date]
    FindMonday --> CheckOverlap{Does Monday already have<br/>a registered holiday?}
    CheckOverlap -- Yes --> FindTuesday[Shift compensatory day to Tuesday]
    CheckOverlap -- No --> CreateVirtual[Create compensatory Holiday Model]
    
    FindTuesday --> CreateVirtual
    CreateVirtual --> SaveHoliday
    SaveHoliday --> Complete([Holiday Setup Complete])
```

---

## 3. Daily Attendance Reconciler Holiday Override Flow

This diagram maps the background daily reconciliation logic, verifying how absences are overridden on registered holiday dates.

```mermaid
graph TD
    StartRecon([Daily Attendance Reconciler Job]) --> GetActiveEmployees[Fetch all active employees]
    
    subgraph Reconciliation Loop
        CheckLogs{Does employee have<br/>clock-in/out logs for date?}
        CheckLogs -- Yes --> ProcessAttendance[Reconcile standard check-in hours]
        
        CheckLogs -- No --> CheckApprovedLeave{Is employee on approved<br/>leave for date?}
        CheckApprovedLeave -- Yes --> SetLeaveStatus[Set status = 'on_leave']
        
        CheckApprovedLeave -- No --> CheckIsHoliday{Is active date a<br/>registered holiday?}
        CheckIsHoliday -- Yes --> SetHolidayStatus[Set status = 'holiday']
        CheckIsHoliday -- No --> SetAbsentStatus[Set status = 'absent']
    end
    
    ReconciliationLoop --> Complete([Daily Reconciliation Complete])
```
