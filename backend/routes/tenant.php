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
use App\Tenants\Modules\Fleet\Controllers\FuelLogController;
use App\Tenants\Modules\Fleet\Controllers\MaintenanceLogController;
use App\Tenants\Modules\Fleet\Controllers\VehicleController;
use App\Tenants\Modules\FMS\Controllers\AccountController;
use App\Tenants\Modules\FMS\Controllers\LedgerController;
use App\Tenants\Modules\HRM\Controllers\ApplicationController;
use App\Tenants\Modules\HRM\Controllers\AppraisalController;
use App\Tenants\Modules\HRM\Controllers\CandidateQuizController;
use App\Tenants\Modules\HRM\Controllers\DepartmentController;
use App\Tenants\Modules\HRM\Controllers\InterviewController;
use App\Tenants\Modules\HRM\Controllers\EmployeeController;
use App\Tenants\Modules\HRM\Controllers\JobVacancyController;
use App\Tenants\Modules\HRM\Controllers\LeaveController;
use App\Tenants\Modules\HRM\Controllers\LeaveTypeController;
use App\Tenants\Modules\HRM\Controllers\PayrollPeriodController;
use App\Tenants\Modules\HRM\Controllers\PayslipController;
use App\Tenants\Modules\HRM\Controllers\PositionController;
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
use App\Tenants\Modules\Reporting\Controllers\WidgetController;
use App\Tenants\Modules\Sales\Controllers\CustomerController;
use App\Tenants\Modules\Sales\Controllers\LeadController;
use App\Tenants\Modules\Sales\Controllers\OrderController;
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

    Route::middleware('auth:api')->group(function () {
        Route::get('/auth/me', [AuthController::class, 'me']);
        Route::post('/auth/logout', [AuthController::class, 'logout']);
        
        // IAM Management
        Route::apiResource('users', UserController::class);
        Route::apiResource('roles', RoleController::class);

        // Workflow Statuses (per-tenant configurable status flows)
        Route::get('/workflow-statuses/modules', [WorkflowStatusController::class, 'modules']);
        Route::apiResource('workflow-statuses', WorkflowStatusController::class)
            ->parameters(['workflow-statuses' => 'workflowStatus']);

        // Sales & CRM Module
        Route::apiResource('customers', CustomerController::class);
        Route::apiResource('leads', LeadController::class);
        Route::post('/leads/{lead}/win', [LeadController::class, 'win']);
        
        Route::apiResource('orders', OrderController::class);
        Route::post('/orders/{order}/confirm', [OrderController::class, 'confirm']);

        // FMS Module
        Route::apiResource('accounts', AccountController::class);
        Route::apiResource('ledger', LedgerController::class);

        // HRM Module — Phase 1: Workforce
        Route::apiResource('employees', EmployeeController::class);
        Route::apiResource('departments', DepartmentController::class);
        Route::apiResource('positions', PositionController::class);

        // HRM Module — Phase 2: Leave
        Route::apiResource('leave-types', LeaveTypeController::class)->parameters(['leave-types' => 'leaveType']);
        Route::apiResource('leaves', LeaveController::class)->only(['index', 'store', 'show', 'destroy']);
        Route::post('/leaves/{leave}/approve', [LeaveController::class, 'approve']);
        Route::post('/leaves/{leave}/reject', [LeaveController::class, 'reject']);
        Route::get('/employees/{employee}/leave-balance', [LeaveController::class, 'balance']);

        // HRM Module — Phase 3: Payroll
        Route::apiResource('payroll-periods', PayrollPeriodController::class)
            ->parameters(['payroll-periods' => 'payrollPeriod'])
            ->only(['index', 'store', 'show']);
        Route::post('/payroll-periods/{payrollPeriod}/process', [PayrollPeriodController::class, 'process']);
        Route::post('/payroll-periods/{payrollPeriod}/close', [PayrollPeriodController::class, 'close']);
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
        Route::post('/approval-requests/{approvalRequest}/process', [ApprovalActionController::class, 'process']);

        // eDocuments Module
        Route::apiResource('folders', FolderController::class);
        Route::apiResource('documents', DocumentController::class);
        Route::get('/documents/{document}/download', [DocumentController::class, 'download']);

        // Fleet Module
        Route::apiResource('vehicles', VehicleController::class);
        Route::apiResource('maintenance-logs', MaintenanceLogController::class);
        Route::apiResource('fuel-logs', FuelLogController::class);

        // Assets Module
        Route::apiResource('assets', AssetController::class);
        Route::post('/assets/{asset}/depreciate', [DepreciationController::class, 'calculate']);

        // Inventory Module
        Route::apiResource('products', ProductController::class);
        Route::apiResource('stock-movements', StockMovementController::class);

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
        Route::apiResource('dashboards', DashboardController::class);
        Route::apiResource('widgets', WidgetController::class);
    });

});
