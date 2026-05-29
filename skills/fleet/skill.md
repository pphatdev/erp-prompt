---
name: fleet-management
description: Manage vehicle tracking, maintenance scheduling, and fuel management.
---
# Fleet Management

Use this skill when managing vehicles, tracking maintenance, fuel expenses, and optimizing routes. This module is critical for logistics and service-based tenants.

## Workflows
1. **Vehicle Maintenance**: Automate the scheduling of routine checks based on mileage or time intervals.
2. **Fuel Expense Logging**: Track fuel consumption per vehicle to calculate efficiency and operational costs.
3. **Trip Management**: Monitor active trips and log driver performance and route compliance.

## Guidelines

### 1. Asset Tracking
- **Vehicle Profiles**: Maintain detailed logs for each vehicle (VIN, Model, Year, Registration).
- **Telematics**: Standardize API connectors for GPS tracking and engine diagnostics.

### 2. Maintenance & Operations
- **Schedules**: Implement mileage-based and date-based maintenance alerts.
- **Expenses**: Track fuel receipts and repair costs to calculate Total Cost of Ownership (TCO).

### 3. Route Optimization
- **Geofencing**: Implement alerts when vehicles leave predefined areas.
- **Trip Logs**: Automatically generate trip reports from telematics data.

## Best Practices
- **Map Integration**: Use high-performance map components (Google Maps/Leaflet) for real-time fleet visualization. Load scripts dynamically on the client inside the `onMounted` hook.
- **Scoping & Security**: Ensure driver self-service is strictly scoped to assigned driver IDs on both the frontend and backend.
- **File Uploads**: When drivers upload fuel receipts, validate MIME types server-side, sanitize filenames, and store them securely in the tenant-isolated path using `tenant_path()`. Serve them via short-lived signed URLs. Never expose direct public paths.
- **Flat Layout Structure**: Organize pages under `frontend/pages/fleet/`. Stores (`stores/fleet.ts`) and composables (`composables/useFleet.ts`) must remain flat.
- **State & Fetching**: Always perform reactive data fetching via the flat `useFleet()` composable, which routes requests through `useApi()` to guarantee tenant context (`X-Tenant-Handle`) and Passport header injection.

## Troubleshooting
- **GPS Drift**: Implement filtering logic to handle inaccurate GPS coordinates from telematics providers.
- **Missed Alerts**: Verify the `MaintenanceSchedulerJob` is running daily.
- **Data Sync**: If telematics data is delayed, check the webhook listener logs for external provider errors.
- **Access Denied / Unauthenticated**: Ensure the frontend is calling `useApi()` (which rotates tokens and injects the active tenant handle) rather than raw `$fetch`.

