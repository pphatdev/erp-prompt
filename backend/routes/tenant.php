<?php

declare(strict_types=1);

use App\Tenants\Modules\Approvals\Controllers\ApprovalActionController;
use App\Tenants\Modules\Approvals\Controllers\WorkflowController;
use App\Tenants\Modules\Assets\Controllers\AssetController;
use App\Tenants\Modules\Assets\Controllers\DepreciationController;
use App\Tenants\Modules\Documents\Controllers\CmsDocumentController;
use App\Tenants\Modules\Documents\Controllers\CmsFolderController;
use App\Tenants\Modules\EDocuments\Controllers\DocumentController;
use App\Tenants\Modules\EDocuments\Controllers\FolderController;
use App\Tenants\Modules\EDocuments\Controllers\ShareController as EDocsShareController;
use App\Tenants\Modules\EDocuments\Controllers\TagController as EDocsTagController;
use App\Tenants\Modules\Fleet\Controllers\FuelLogController;
use App\Tenants\Modules\Fleet\Controllers\MaintenanceLogController;
use App\Tenants\Modules\Fleet\Controllers\VehicleController;
use App\Tenants\Modules\FMS\Controllers\AccountController;
use App\Tenants\Modules\FMS\Controllers\ExchangeRateController;
use App\Tenants\Modules\FMS\Controllers\LedgerController;
use App\Tenants\Modules\HRM\Controllers\ApplicationController;
use App\Tenants\Modules\HRM\Controllers\AppraisalController;
use App\Tenants\Modules\HRM\Controllers\EmployeeAppointmentController;
use App\Tenants\Modules\HRM\Controllers\CandidateQuizController;
use App\Tenants\Modules\HRM\Controllers\DepartmentController;
use App\Tenants\Modules\HRM\Controllers\InterviewController;
use App\Tenants\Modules\HRM\Controllers\EmployeeController;
use App\Tenants\Modules\HRM\Controllers\JobVacancyController;
use App\Tenants\Modules\HRM\Controllers\LeaveController;
use App\Tenants\Modules\HRM\Controllers\LeaveTypeController;
use App\Tenants\Modules\HRM\Controllers\PayrollPeriodController;
use App\Tenants\Modules\HRM\Controllers\PayslipController;
use App\Tenants\Modules\HRM\Controllers\AttendanceController;
use App\Tenants\Modules\HRM\Controllers\OvertimeRequestController;
use App\Tenants\Modules\HRM\Controllers\PositionController;
use App\Tenants\Modules\HRM\Controllers\ShiftController;
use App\Tenants\Modules\HRM\Controllers\PublicCareersController;
use App\Tenants\Modules\HRM\Controllers\QuizController;
use App\Tenants\Modules\IAM\Controllers\RoleController;
use App\Tenants\Modules\IAM\Controllers\UserController;
use App\Tenants\Modules\IAM\Controllers\WorkflowStatusController;
use App\Tenants\Modules\Inventory\Controllers\ProductController;
use App\Tenants\Modules\Inventory\Controllers\StockMovementController;
use App\Tenants\Modules\Projects\Controllers\ProjectController;
use App\Tenants\Modules\Projects\Controllers\TaskController;
use App\Tenants\Modules\Projects\Controllers\TimesheetController;
use App\Tenants\Modules\Reporting\Controllers\DashboardController;
use App\Tenants\Modules\Reporting\Controllers\DashboardSummaryController;
use App\Tenants\Modules\Reporting\Controllers\WidgetController;
use App\Tenants\Modules\Sales\Controllers\CustomerController;
use App\Tenants\Modules\Sales\Controllers\InvoiceController;
use App\Tenants\Modules\Sales\Controllers\LeadController;
use App\Tenants\Modules\Sales\Controllers\OrderController;
use App\Tenants\Modules\Sales\Controllers\QuotationController;
use App\Tenants\Modules\Sales\Controllers\SubscriptionController;
use App\Tenants\Modules\Settings\Controllers\ModuleController;
use App\Tenants\Modules\Settings\Controllers\SettingController;
use Illuminate\Support\Facades\Route;
use App\Tenants\Modules\IAM\Controllers\AuthController;
use App\Http\Middleware\InitializeTenancyByHandle;

/*
|--------------------------------------------------------------------------
| Tenant Routes
|--------------------------------------------------------------------------
|
| Here is where you can register tenant routes for your application.
| These routes are loaded by the TenantRouteServiceProvider and all of them
| will be in the 'web' or 'api' middleware group and utilize the
| InitializeTenancyByHandle middleware to resolve the tenant context.
|
*/

Route::middleware([
    'api',
    InitializeTenancyByHandle::class,
])->prefix('api/v1')->group(function () {

    // Auth Routes
    Route::post('/auth/login', [AuthController::class, 'login']);
    Route::post('/auth/refresh', [AuthController::class, 'refresh']);

    // Candidate Assessment Portal — token-authenticated, no Passport session.
    // Anchored on the magic-link token (?token=...). See QuizService.
    Route::get('/candidate/auth', [CandidateQuizController::class, 'auth']);
    Route::post('/candidate/quizzes/{attempt}/start', [CandidateQuizController::class, 'start']);
    Route::post('/candidate/quizzes/{attempt}/submit', [CandidateQuizController::class, 'submit']);

    // Public Careers surface — no Passport. Tenant is still resolved via the
    // X-Tenant-Handle header (InitializeTenancyByHandle), so each tenant's
    // careers page only sees its own vacancies. Public listing is filtered to
    // status=open; closed/draft IDs return 404.
    Route::get('/public/job-vacancies', [PublicCareersController::class, 'listVacancies']);
    Route::get('/public/job-vacancies/{jobVacancy}', [PublicCareersController::class, 'showVacancy']);
    Route::post('/public/applications', [PublicCareersController::class, 'submitApplication']);

    // Public branding (logo, primary color) — used by login & public surfaces.
    Route::get('/settings/public', [SettingController::class, 'public']);

    // Public Catalog — unauthenticated storefront / partner integration surface.
    // Tenant resolved by X-Tenant-Handle header (same as the public careers
    // endpoints above). Returns only sellable fields; no cost/WAC leaks.
    Route::get('/public/catalog', [\App\Tenants\Modules\Inventory\Controllers\PublicCatalogController::class, 'index']);
    Route::get('/public/catalog/{product}', [\App\Tenants\Modules\Inventory\Controllers\PublicCatalogController::class, 'show']);
    Route::get('/public/catalog/{product}/availability', [\App\Tenants\Modules\Inventory\Controllers\PublicCatalogController::class, 'availability']);

    // Public eDocs share links — recipients have a tenant-handle + token URL.
    // ShareLinkService throws 410/403/429 directly; no Passport required.
    Route::get('/public/shares/{token}', [EDocsShareController::class, 'publicShow']);
    Route::get('/public/shares/{token}/download', [EDocsShareController::class, 'publicDownload']);

    Route::middleware('auth:api')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        
        // IAM Management
        Route::post('/users/{user}/reset-password', [UserController::class, 'resetPassword']);
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);

        // Workflow Statuses (per-tenant configurable status flows)
        Route::get('/workflow-statuses/modules', [WorkflowStatusController::class, 'modules']);
        Route::apiResource('workflow-statuses', WorkflowStatusController::class)
            ->parameters(['workflow-statuses' => 'workflowStatus']);

        // Configuration & Tenant Settings (key/value)
        Route::get('/settings', [SettingController::class, 'index']);
        Route::put('/settings', [SettingController::class, 'update']);

        // Modules — sidebar menu items stored in DB, mapped to products
        Route::get('/modules', [ModuleController::class, 'index']);
        Route::get('/modules/slugs', [ModuleController::class, 'slugs']);
        Route::get('/modules/all', [ModuleController::class, 'allForManagement']);
        Route::put('/modules/bulk', [ModuleController::class, 'bulkUpdate']);
        Route::patch('/modules/{module}/toggle', [ModuleController::class, 'toggle']);
        Route::post('/modules/{module}/sync-product', [ModuleController::class, 'syncProduct']);

        // Sales — Customers
        Route::get('/customers/check-handle', [CustomerController::class, 'checkHandle']);
        Route::apiResource('customers', CustomerController::class);

        // CRM — Leads
        Route::apiResource('leads', \App\Tenants\Modules\Crm\Controllers\LeadController::class);
        Route::post('/leads/{lead}/qualify', [\App\Tenants\Modules\Crm\Controllers\LeadController::class, 'qualify']);

        // CRM — Opportunities
        Route::apiResource('opportunities', \App\Tenants\Modules\Crm\Controllers\OpportunityController::class);
        Route::patch('/opportunities/{opportunity}/stage', [\App\Tenants\Modules\Crm\Controllers\OpportunityController::class, 'updateStage']);

        // CRM — B2B Product Schedule (nested under Opportunity)
        Route::get('/opportunities/{opportunity}/product-schedule', [\App\Tenants\Modules\Crm\Controllers\OpportunityProductScheduleController::class, 'index']);
        Route::post('/opportunities/{opportunity}/product-schedule', [\App\Tenants\Modules\Crm\Controllers\OpportunityProductScheduleController::class, 'store']);
        Route::patch('/opportunities/{opportunity}/product-schedule/{line}', [\App\Tenants\Modules\Crm\Controllers\OpportunityProductScheduleController::class, 'update']);
        Route::delete('/opportunities/{opportunity}/product-schedule/{line}', [\App\Tenants\Modules\Crm\Controllers\OpportunityProductScheduleController::class, 'destroy']);

        // CRM — Contacts
        Route::apiResource('crm-contacts', \App\Tenants\Modules\Crm\Controllers\CrmContactController::class)
            ->parameters(['crm-contacts' => 'crmContact']);

        // CRM — Activities
        Route::apiResource('crm-activities', \App\Tenants\Modules\Crm\Controllers\CrmActivityController::class)
            ->parameters(['crm-activities' => 'crmActivity']);
        Route::post('/crm-activities/{crmActivity}/complete', [\App\Tenants\Modules\Crm\Controllers\CrmActivityController::class, 'complete']);

        // CRM — Appointments (calendar / timeline of meetings, demos, follow-ups)
        Route::apiResource('crm-appointments', \App\Tenants\Modules\Crm\Controllers\CrmAppointmentController::class)
            ->parameters(['crm-appointments' => 'crmAppointment']);
        Route::post('/crm-appointments/{crmAppointment}/complete',  [\App\Tenants\Modules\Crm\Controllers\CrmAppointmentController::class, 'complete']);
        Route::post('/crm-appointments/{crmAppointment}/cancel',    [\App\Tenants\Modules\Crm\Controllers\CrmAppointmentController::class, 'cancel']);
        Route::post('/crm-appointments/{crmAppointment}/no-show',   [\App\Tenants\Modules\Crm\Controllers\CrmAppointmentController::class, 'markNoShow']);

        // Hybrid Sales — Quotations (draft → won/lost)
        Route::apiResource('quotations', QuotationController::class)
            ->only(['index', 'store', 'show', 'destroy']);
        Route::post('/quotations/{quotation}/items', [QuotationController::class, 'addItem']);
        Route::post('/quotations/{quotation}/win', [QuotationController::class, 'win']);
        Route::post('/quotations/{quotation}/lose', [QuotationController::class, 'lose']);

        // Hybrid Sales — Sale Orders (draft → confirm/cancel)
        // No /convert-to-order — the SO is auto-created on Quotation::win.
        Route::apiResource('orders', OrderController::class)
            ->only(['index', 'store', 'show']);
        Route::post('/orders/{order}/confirm', [OrderController::class, 'confirm']);
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel']);

        // Hybrid Sales — Invoices (1:1 with Order, AR posted on confirm)
        Route::get('/invoices', [InvoiceController::class, 'index']);
        Route::get('/invoices/{invoice}', [InvoiceController::class, 'show']);
        Route::post('/invoices/{invoice}/confirm', [InvoiceController::class, 'confirm']);
        Route::post('/invoices/{invoice}/cancel', [InvoiceController::class, 'cancel']);

        // Hybrid Sales — Subscriptions (software fulfillment; start as `active`)
        Route::get('/subscriptions', [SubscriptionController::class, 'index']);
        Route::get('/subscriptions/{subscription}', [SubscriptionController::class, 'show']);
        Route::post('/subscriptions/{subscription}/renew', [SubscriptionController::class, 'renew']);
        Route::post('/subscriptions/{subscription}/change-plan', [SubscriptionController::class, 'changePlan']);
        Route::post('/subscriptions/{subscription}/cancel', [SubscriptionController::class, 'cancel']);

        // FMS Module
        Route::apiResource('accounts', AccountController::class);
        Route::apiResource('ledger', LedgerController::class);

        Route::get('/exchange-rates/latest',  [ExchangeRateController::class, 'latest']);
        Route::get('/exchange-rates/convert', [ExchangeRateController::class, 'convert']);
        Route::apiResource('exchange-rates', ExchangeRateController::class)
            ->parameters(['exchange-rates' => 'exchangeRate']);

        // HRM Module — Phase 1: Workforce
        // Self-service profile lookup/edit. Listed before the apiResource so
        // `/employees/me` doesn't get routed through the `{employee}` UUID
        // parameter binding.
        Route::get('/employees/me', [EmployeeController::class, 'me']);
        Route::patch('/employees/me', [EmployeeController::class, 'updateSelf']);
        Route::post('/employees/{employee}/avatar', [EmployeeController::class, 'uploadAvatar']);
        Route::delete('/employees/{employee}/avatar', [EmployeeController::class, 'deleteAvatar']);
        Route::apiResource('employees', EmployeeController::class);
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('positions', PositionController::class);

        // HRM Module — Phase 2: Leave
        Route::apiResource('leave-types', LeaveTypeController::class)->parameters(['leave-types' => 'leaveType']);
        Route::apiResource('leaves', LeaveController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve']);
        Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject']);
        Route::get('/employees/{employee}/leave-balance', [LeaveController::class, 'balance']);

        // Aliases for /hrm/timeoff/leaves used by the frontend listing and action page
        Route::get('/hrm/timeoff/leaves', [LeaveController::class, 'index']);
        Route::post('/hrm/timeoff/leaves', [LeaveController::class, 'store']);
        Route::delete('/hrm/timeoff/leaves/{leave}', [LeaveController::class, 'destroy']);
        Route::post('/hrm/timeoff/leaves/{leave}/approve', [LeaveController::class, 'approve']);
        Route::post('/hrm/timeoff/leaves/{leave}/reject', [LeaveController::class, 'reject']);

        // HRM Module — Time Off & Attendance, Slice 1: Shifts + EmployeeShifts
        Route::apiResource('shifts', ShiftController::class);
        Route::post('/hrm/shifts/{shift}/assignments', [ShiftController::class, 'assign']);
        Route::get('/employees/{employee}/shift-assignments', [ShiftController::class, 'assignmentsForEmployee']);

        // HRM Module — Time Off & Attendance, Slice 2: Attendance logs
        Route::post('/attendance/clock-in', [AttendanceController::class, 'clockIn']);
        Route::post('/attendance/clock-out', [AttendanceController::class, 'clockOut']);
        Route::get('/attendance/logs', [AttendanceController::class, 'index']);
        Route::get('/attendance/logs/{attendanceLog}', [AttendanceController::class, 'show']);
        // Slice 4: manual reconciliation trigger.
        Route::post('/attendance/reconcile', [AttendanceController::class, 'reconcile']);

        // HRM Module — Time Off & Attendance, Slice 3: Overtime
        Route::apiResource('overtime-requests', OvertimeRequestController::class)
            ->parameters(['overtime-requests' => 'overtimeRequest'])
            ->only(['index', 'store', 'show', 'destroy']);
        Route::patch('/hrm/overtime-requests/{overtimeRequest}/process', [OvertimeRequestController::class, 'process']);

        // HRM Module — Phase 3: Payroll
        Route::apiResource('payroll-periods', PayrollPeriodController::class)
            ->parameters(['payroll-periods' => 'payrollPeriod'])
            ->only(['index', 'store', 'show']);
        Route::post('/hrm/payroll-periods/{payrollPeriod}/process', [PayrollPeriodController::class, 'process']);
        Route::post('/hrm/payroll-periods/{payrollPeriod}/close', [PayrollPeriodController::class, 'close']);
        Route::apiResource('payslips', PayslipController::class)->only(['index', 'show']);

        // HRM Module — Phase 4A: Recruitment
        Route::apiResource('job-vacancies', JobVacancyController::class)
            ->parameters(['job-vacancies' => 'jobVacancy']);
        Route::post('/job-vacancies/{jobVacancy}/publish', [JobVacancyController::class, 'publish']);
        Route::post('/job-vacancies/{jobVacancy}/close', [JobVacancyController::class, 'close']);
        Route::apiResource('applications', ApplicationController::class)
            ->only(['index', 'store', 'show', 'update', 'destroy']);
        Route::post('/applications/resumes', [ApplicationController::class, 'storeResume']);
        Route::post('/applications/bulk-delete', [ApplicationController::class, 'bulkDestroy']);
        Route::post('/applications/bulk-convert-to-employee', [ApplicationController::class, 'bulkConvertToEmployees']);
        Route::patch('/applications/{application}/status', [ApplicationController::class, 'transition']);
        Route::post('/applications/{application}/convert-to-employee', [ApplicationController::class, 'convertToEmployee']);
        Route::post('/applications/{application}/revert-employee-conversion', [ApplicationController::class, 'revertEmployeeConversion']);
        Route::post('/applications/{application}/quiz-attempts', [QuizController::class, 'assignToApplication']);

        // HRM Module — Phase 4A: Employee Appointment (post-hire workflow)
        Route::post('/employee-appointments', [EmployeeAppointmentController::class, 'store']);
        Route::get('/employee-appointments/{appointment}', [EmployeeAppointmentController::class, 'show']);

        // HRM Module — Phase 4B: Performance
        Route::apiResource('appraisals', AppraisalController::class);
        Route::post('/appraisals/{appraisal}/submit', [AppraisalController::class, 'submit']);
        Route::post('/appraisals/{appraisal}/review', [AppraisalController::class, 'review']);
        Route::post('/appraisals/{appraisal}/close', [AppraisalController::class, 'close']);

        // HRM Module — Phase 6: Quiz Assessment (admin authoring)
        Route::apiResource('quizzes', QuizController::class);
        Route::post('/quizzes/{quiz}/questions', [QuizController::class, 'addQuestion']);

        // HRM Module — Phase 7: Interviewing & Panel Feedback
        Route::apiResource('interviews', InterviewController::class);
        Route::post('/interviews/{interview}/cancel', [InterviewController::class, 'cancel']);
        Route::post('/interviews/{interview}/complete', [InterviewController::class, 'complete']);
        Route::post('/interviews/{interview}/feedback', [InterviewController::class, 'submitFeedback']);
        Route::get('/interviews/{interview}/scorecard', [InterviewController::class, 'scorecard']);
        Route::get('/interviews/{interview}/invite.ics', [InterviewController::class, 'downloadInvite']);

        // eApprovals Module
        Route::apiResource('approval-workflows', WorkflowController::class);
        Route::get('/approval-requests', [ApprovalActionController::class, 'index']);
        Route::get('/approval-requests/{approvalRequest}', [ApprovalActionController::class, 'show']);
        Route::post('/approval-requests/{approvalRequest}/process', [ApprovalActionController::class, 'process']);

        // eDocuments Module
        // Specific document actions registered BEFORE apiResource so {document}
        // does not capture the static segments below as UUIDs.
        Route::get('/documents/{document}/download', [DocumentController::class, 'download']);
        Route::patch('/documents/{document}/move', [DocumentController::class, 'move']);
        Route::get('/documents/{document}/versions', [DocumentController::class, 'versions']);
        Route::post('/documents/{document}/versions', [DocumentController::class, 'createVersion']);
        Route::post('/documents/{document}/acknowledge', [DocumentController::class, 'acknowledge']);
        Route::get('/documents/{document}/acknowledgements', [DocumentController::class, 'acknowledgementSummary']);
        Route::get('/documents/{document}/shares', [EDocsShareController::class, 'index']);
        Route::post('/documents/{document}/shares', [EDocsShareController::class, 'store']);
        Route::apiResource('documents', DocumentController::class);

        Route::patch('/folders/{folder}/move', [FolderController::class, 'move']);
        Route::apiResource('folders', FolderController::class);

        Route::apiResource('document-tags', EDocsTagController::class)->parameters(['document-tags' => 'tag']);

        Route::delete('/document-shares/{share}', [EDocsShareController::class, 'destroy']);

        // Fleet Module
        // bulk-archive registered BEFORE the apiResource so the {vehicle}
        // parameter doesn't swallow 'bulk-archive' as a UUID lookup.
        Route::post('/vehicles/bulk-archive', [VehicleController::class, 'bulkArchive']);
        Route::apiResource('vehicles', VehicleController::class);
        Route::post('/vehicles/{vehicle}/image', [VehicleController::class, 'uploadImage']);
        Route::delete('/vehicles/{vehicle}/image', [VehicleController::class, 'deleteImage']);
        Route::apiResource('vehicle-models', \App\Tenants\Modules\Fleet\Controllers\VehicleModelController::class)
            ->parameters(['vehicle-models' => 'vehicleModel']);
        Route::apiResource('maintenance-logs', MaintenanceLogController::class)
            ->parameters(['maintenance-logs' => 'maintenanceLog']);
        Route::apiResource('fuel-logs', FuelLogController::class)
            ->parameters(['fuel-logs' => 'fuelLog']);

        // Assets Module
        Route::apiResource('assets', AssetController::class);
        Route::post('/assets/{asset}/depreciate', [DepreciationController::class, 'calculate']);

        // Inventory Module
        Route::apiResource('products', ProductController::class);
        Route::apiResource('stock-movements', StockMovementController::class);
        Route::apiResource('warehouses', \App\Tenants\Modules\Inventory\Controllers\WarehouseController::class);
        Route::apiResource('suppliers', \App\Tenants\Modules\Inventory\Controllers\SupplierController::class);
        Route::apiResource('categories', \App\Tenants\Modules\Inventory\Controllers\CategoryController::class);
        Route::apiResource('products.variants', \App\Tenants\Modules\Inventory\Controllers\ProductVariantController::class)
            ->parameters(['variants' => 'variant'])
            ->shallow();

        // Inventory — Stock Reservations (POS / eCommerce soft-holds, 15-min TTL by default)
        // Availability route declared BEFORE the apiResource so its literal
        // segment isn't captured as a {stockReservation} parameter.
        Route::get('/stock-reservations/availability', [\App\Tenants\Modules\Inventory\Controllers\StockReservationController::class, 'availability']);
        Route::apiResource('stock-reservations', \App\Tenants\Modules\Inventory\Controllers\StockReservationController::class)
            ->only(['index', 'store', 'show'])
            ->parameters(['stock-reservations' => 'stockReservation']);
        Route::post('/stock-reservations/{stockReservation}/commit', [\App\Tenants\Modules\Inventory\Controllers\StockReservationController::class, 'commit']);
        Route::post('/stock-reservations/{stockReservation}/cancel', [\App\Tenants\Modules\Inventory\Controllers\StockReservationController::class, 'cancel']);

        // Inventory — Inter-warehouse Stock Transfers (draft → dispatch → receive | cancel)
        Route::apiResource('stock-transfers', \App\Tenants\Modules\Inventory\Controllers\StockTransferController::class)
            ->only(['index', 'store', 'show'])
            ->parameters(['stock-transfers' => 'stockTransfer']);
        Route::post('/stock-transfers/{stockTransfer}/dispatch', [\App\Tenants\Modules\Inventory\Controllers\StockTransferController::class, 'dispatch_']);
        Route::post('/stock-transfers/{stockTransfer}/receive',  [\App\Tenants\Modules\Inventory\Controllers\StockTransferController::class, 'receive']);
        Route::post('/stock-transfers/{stockTransfer}/cancel',   [\App\Tenants\Modules\Inventory\Controllers\StockTransferController::class, 'cancel']);

        // Inventory — Low-stock Alerts (read-only ledger; manage = ack/resolve)
        Route::apiResource('low-stock-alerts', \App\Tenants\Modules\Inventory\Controllers\LowStockAlertController::class)
            ->only(['index', 'show'])
            ->parameters(['low-stock-alerts' => 'lowStockAlert']);
        Route::post('/low-stock-alerts/{lowStockAlert}/acknowledge', [\App\Tenants\Modules\Inventory\Controllers\LowStockAlertController::class, 'acknowledge']);
        Route::post('/low-stock-alerts/{lowStockAlert}/resolve',     [\App\Tenants\Modules\Inventory\Controllers\LowStockAlertController::class, 'resolve']);

        // Inventory — Purchase Orders (P2P: draft → submit → approve → receive)
        Route::apiResource('purchase-orders', \App\Tenants\Modules\Inventory\Controllers\PurchaseOrderController::class)
            ->only(['index', 'store', 'show', 'destroy'])
            ->parameters(['purchase-orders' => 'purchaseOrder']);
        Route::post('/purchase-orders/{purchaseOrder}/submit',  [\App\Tenants\Modules\Inventory\Controllers\PurchaseOrderController::class, 'submit']);
        Route::post('/purchase-orders/{purchaseOrder}/approve', [\App\Tenants\Modules\Inventory\Controllers\PurchaseOrderController::class, 'approve']);
        Route::post('/purchase-orders/{purchaseOrder}/receive', [\App\Tenants\Modules\Inventory\Controllers\PurchaseOrderController::class, 'receive']);
        Route::post('/purchase-orders/{purchaseOrder}/cancel',  [\App\Tenants\Modules\Inventory\Controllers\PurchaseOrderController::class, 'cancel']);

        // Projects Module
        Route::apiResource('projects', ProjectController::class);
        Route::get('/projects/{project}/budget-status', [ProjectController::class, 'budgetStatus']);
        Route::apiResource('tasks', TaskController::class);
        Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus']);
        Route::apiResource('timesheets', TimesheetController::class);

        // Documents (CMS) Module
        Route::apiResource('cms-folders', CmsFolderController::class);
        Route::apiResource('cms-documents', CmsDocumentController::class);
        Route::post('/cms-documents/{document}/checkout', [CmsDocumentController::class, 'checkout']);
        Route::post('/cms-documents/{document}/checkin', [CmsDocumentController::class, 'checkin']);

        // Reporting & Analytics Module
        Route::get('/dashboard/summary', DashboardSummaryController::class);
        Route::apiResource('dashboards', DashboardController::class);
        Route::get('/dashboards/{dashboard}/export', [DashboardController::class, 'export']);
        Route::apiResource('widgets', WidgetController::class);
    });

});
