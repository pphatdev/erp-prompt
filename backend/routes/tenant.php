<?php

declare(strict_types=1);

use App\Tenants\Modules\Approvals\Controllers\ApprovalActionController;
use App\Tenants\Modules\Approvals\Controllers\WorkflowController;
use App\Tenants\Modules\Assets\Controllers\AssetAuditCampaignController;
use App\Tenants\Modules\Assets\Controllers\AssetController;
use App\Tenants\Modules\Assets\Controllers\AssetVerificationController;
use App\Tenants\Modules\Assets\Controllers\DepreciationController;
use App\Tenants\Modules\Assets\Controllers\DisposalController;
use App\Tenants\Modules\Assets\Controllers\RevaluationController;
use App\Tenants\Modules\Documents\Controllers\CmsDocumentController;
use App\Tenants\Modules\Documents\Controllers\CmsFolderController;
use App\Tenants\Modules\Ecommerce\Controllers\CartController as ShopCartController;
use App\Tenants\Modules\Ecommerce\Controllers\CheckoutController as ShopCheckoutController;
use App\Tenants\Modules\Ecommerce\Controllers\EcommerceCustomerController;
use App\Tenants\Modules\Ecommerce\Controllers\EcommerceOrderController;
use App\Tenants\Modules\Ecommerce\Controllers\EcommerceRefundController;
use App\Tenants\Modules\Ecommerce\Controllers\ShopperAddressController;
use App\Tenants\Modules\Ecommerce\Controllers\ShopperAuthController;
use App\Tenants\Modules\Ecommerce\Controllers\ShopperOrderController;
use App\Tenants\Modules\Ecommerce\Controllers\WebhookController as EcomWebhookController;
use App\Tenants\Modules\EDocuments\Controllers\DocumentController;
use App\Tenants\Modules\EDocuments\Controllers\FolderController;
use App\Tenants\Modules\EDocuments\Controllers\ShareController as EDocsShareController;
use App\Tenants\Modules\EDocuments\Controllers\TagController as EDocsTagController;
use App\Tenants\Modules\Fleet\Controllers\FuelLogController;
use App\Tenants\Modules\Fleet\Controllers\MaintenanceLogController;
use App\Tenants\Modules\Fleet\Controllers\VehicleController;
use App\Tenants\Modules\FMS\Controllers\AccountController;
use App\Tenants\Modules\FMS\Controllers\BankAccountController;
use App\Tenants\Modules\FMS\Controllers\BillController;
use App\Tenants\Modules\FMS\Controllers\BillPaymentController;
use App\Tenants\Modules\FMS\Controllers\CashAdvanceController;
use App\Tenants\Modules\FMS\Controllers\CashAdvanceSettlementController;
use App\Tenants\Modules\FMS\Controllers\BankReconController;
use App\Tenants\Modules\FMS\Controllers\BudgetController;
use App\Tenants\Modules\FMS\Controllers\CreditNoteController;
use App\Tenants\Modules\FMS\Controllers\DebitNoteController;
use App\Tenants\Modules\FMS\Controllers\ExpenseController;
use App\Tenants\Modules\FMS\Controllers\FiscalPeriodController;
use App\Tenants\Modules\FMS\Controllers\ReceiptController;
use App\Tenants\Modules\FMS\Controllers\ExchangeRateController;
use App\Tenants\Modules\FMS\Controllers\LedgerController;
use App\Tenants\Modules\FMS\Controllers\ReimbursementController;
use App\Tenants\Modules\HRM\Controllers\ApplicationController;
use App\Tenants\Modules\HRM\Controllers\AppraisalController;
use App\Tenants\Modules\HRM\Controllers\AppraisalPeerFeedbackController;
use App\Tenants\Modules\HRM\Controllers\EmployeeAppointmentController;
use App\Tenants\Modules\HRM\Controllers\CandidateQuizController;
use App\Tenants\Modules\HRM\Controllers\DepartmentController;
use App\Tenants\Modules\HRM\Controllers\InterviewController;
use App\Tenants\Modules\HRM\Controllers\EmployeeController;
use App\Tenants\Modules\HRM\Controllers\JobVacancyController;
use App\Tenants\Modules\HRM\Controllers\OfferController;
use App\Tenants\Modules\HRM\Controllers\OnboardingTaskController;
use App\Tenants\Modules\HRM\Controllers\WorkScheduleController;
use App\Tenants\Modules\HRM\Controllers\HolidayController;
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
use App\Tenants\Modules\Calendar\Controllers\CalendarEventController;
use App\Tenants\Modules\POS\Controllers\PosOrderController;
use App\Tenants\Modules\POS\Controllers\PosShiftController;
use App\Tenants\Modules\POS\Controllers\PosTerminalController;
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

    // HRM Phase 8 — eSignature provider webhook. Lives outside auth:api so
    // DocuSign / Adobe Sign can post directly; tenant still resolves via
    // X-Tenant-Handle and the request is signature-verified by
    // ESignatureService::verifySignature() before any state changes.
    Route::post('/offers/sign-webhook', [OfferController::class, 'webhook']);

    // Public Catalog — unauthenticated storefront / partner integration surface.
    // Tenant resolved by X-Tenant-Handle header (same as the public careers
    // endpoints above). Returns only sellable fields; no cost/WAC leaks.
    Route::get('/public/catalog', [\App\Tenants\Modules\Inventory\Controllers\PublicCatalogController::class, 'index']);
    Route::get('/public/catalog/categories', [\App\Tenants\Modules\Inventory\Controllers\PublicCatalogController::class, 'categories']);
    Route::get('/public/catalog/{product}', [\App\Tenants\Modules\Inventory\Controllers\PublicCatalogController::class, 'show']);
    Route::get('/public/catalog/{product}/availability', [\App\Tenants\Modules\Inventory\Controllers\PublicCatalogController::class, 'availability']);

    // Public eDocs share links — recipients have a tenant-handle + token URL.
    // ShareLinkService throws 410/403/429 directly; no Passport required.
    Route::get('/public/shares/{token}', [EDocsShareController::class, 'publicShow']);
    Route::get('/public/shares/{token}/download', [EDocsShareController::class, 'publicDownload']);

    // Ecommerce — public storefront (cart + register + login + checkout).
    // Tenant resolved via X-Tenant-Handle header. Guest carts use the
    // X-Cart-Session header / `session_token` body field.
    Route::post('/shop/auth/register', [ShopperAuthController::class, 'register']);
    Route::post('/shop/auth/login', [ShopperAuthController::class, 'login']);

    // Cart routes work for both authenticated shoppers and guests (resolution
    // happens inside the controller). Guests must send X-Cart-Session.
    Route::get('/shop/cart', [ShopCartController::class, 'show']);
    Route::post('/shop/cart/items', [ShopCartController::class, 'addItem']);
    Route::put('/shop/cart/items/{item}', [ShopCartController::class, 'updateItem']);
    Route::delete('/shop/cart/items/{item}', [ShopCartController::class, 'removeItem']);

    // Checkout — initiate works for guests + shoppers; confirmDirect and cancel
    // need the actor to own the order (controller asserts).
    Route::post('/shop/checkout/initiate', [ShopCheckoutController::class, 'initiate']);
    Route::post('/shop/orders/{order}/confirm-direct', [ShopCheckoutController::class, 'confirmDirect']);
    Route::post('/shop/orders/{order}/cancel', [ShopCheckoutController::class, 'cancel']);

    // Payment gateway webhooks. No auth — provider signs the body and we
    // verify in the controller. Tenant must still be resolved via header.
    Route::post('/ecom/webhooks/{provider}', [EcomWebhookController::class, 'handle']);

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
        Route::post('/customers/{customer}/provision', [CustomerController::class, 'provision']);

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

        // Ledger: immutable postings. apiResource is intentionally NOT used so
        // PUT/PATCH/DELETE on /ledger/{journal} never reach a handler — the
        // policy refuses them, but the route surface mirrors that contract.
        // Corrections go through POST /ledger/{journal}/reverse.
        Route::get('/ledger',                       [LedgerController::class, 'index']);
        Route::post('/ledger',                      [LedgerController::class, 'store']);
        Route::get('/ledger/{journal}',             [LedgerController::class, 'show']);
        Route::post('/ledger/{journal}/reverse',    [LedgerController::class, 'reverse']);

        Route::get('/exchange-rates/latest',  [ExchangeRateController::class, 'latest']);
        Route::get('/exchange-rates/convert', [ExchangeRateController::class, 'convert']);
        Route::apiResource('exchange-rates', ExchangeRateController::class)
            ->parameters(['exchange-rates' => 'exchangeRate']);

        // Accounting — Bank Accounts (foundation for Pay Bill / Receipt / Reimbursement / Expense).
        Route::apiResource('bank-accounts', BankAccountController::class)
            ->parameters(['bank-accounts' => 'bankAccount']);

        // Accounting — Bills (AP cycle). Custom actions before the apiResource
        // so /bills/{bill}/approve doesn't get routed through {bill} only.
        Route::post('/bills/{bill}/approve', [BillController::class, 'approve']);
        Route::post('/bills/{bill}/cancel',  [BillController::class, 'cancel']);
        Route::apiResource('bills', BillController::class);

        // Accounting — Pay Bill. Payments are immutable once recorded; only
        // index/store/show/cancel are surfaced. Update/destroy intentionally
        // omitted so PUT/PATCH/DELETE never reach a handler (matches policy).
        Route::post('/bill-payments/{billPayment}/cancel', [BillPaymentController::class, 'cancel']);
        Route::get('/bill-payments',                       [BillPaymentController::class, 'index']);
        Route::post('/bill-payments',                      [BillPaymentController::class, 'store']);
        Route::get('/bill-payments/{billPayment}',         [BillPaymentController::class, 'show']);

        // Accounting — Reimbursements. Same immutable shape as bill_payments.
        Route::post('/reimbursements/{reimbursement}/cancel', [ReimbursementController::class, 'cancel']);
        Route::get('/reimbursements',                         [ReimbursementController::class, 'index']);
        Route::post('/reimbursements',                        [ReimbursementController::class, 'store']);
        Route::get('/reimbursements/{reimbursement}',         [ReimbursementController::class, 'show']);

        // Accounting — Cash Advances. Issuance posts DR Receivable / CR Cash.
        // Cancellation only allowed before any settlement is applied; otherwise
        // reverse the settlement(s) first (settlement is a separate phase).
        Route::post('/cash-advances/{cashAdvance}/cancel', [CashAdvanceController::class, 'cancel']);
        Route::get('/cash-advances',                       [CashAdvanceController::class, 'index']);
        Route::post('/cash-advances',                      [CashAdvanceController::class, 'store']);
        Route::get('/cash-advances/{cashAdvance}',         [CashAdvanceController::class, 'show']);

        // Accounting — Advance Settlements. Settles actuals against an open
        // cash advance; rolls advance.settled_amount and status forward
        // (open -> partially_settled -> closed). Immutable once posted.
        Route::post('/cash-advance-settlements/{cashAdvanceSettlement}/cancel', [CashAdvanceSettlementController::class, 'cancel']);
        Route::get('/cash-advance-settlements',                                 [CashAdvanceSettlementController::class, 'index']);
        Route::post('/cash-advance-settlements',                                [CashAdvanceSettlementController::class, 'store']);
        Route::get('/cash-advance-settlements/{cashAdvanceSettlement}',         [CashAdvanceSettlementController::class, 'show']);

        // Accounting — Expenses (non-AP). Pay-as-you-go spend that does not
        // route through Bills: DR Expense (per line) / CR Cash. Immutable once
        // posted (matches the bill_payments / reimbursements / cash_advances shape).
        Route::post('/expenses/{expense}/cancel', [ExpenseController::class, 'cancel']);
        Route::get('/expenses',                   [ExpenseController::class, 'index']);
        Route::post('/expenses',                  [ExpenseController::class, 'store']);
        Route::get('/expenses/{expense}',         [ExpenseController::class, 'show']);

        // Accounting — Receipts (AR cycle continuation). AR-side mirror of
        // bill_payments: DR Bank's GL / CR AR (per applied invoice). Immutable
        // once posted; the open-invoices helper powers the picker on the UI.
        Route::get('/receipts/open-invoices/{customer}', [ReceiptController::class, 'openInvoicesForCustomer']);
        Route::post('/receipts/{receipt}/cancel',        [ReceiptController::class, 'cancel']);
        Route::get('/receipts',                          [ReceiptController::class, 'index']);
        Route::post('/receipts',                         [ReceiptController::class, 'store']);
        Route::get('/receipts/{receipt}',                [ReceiptController::class, 'show']);

        // Accounting — Credit Notes (AR adjustment). DR Sales Returns / CR AR.
        // Optional invoice link rolls into invoice.paid_amount alongside receipts.
        // Immutable once issued.
        Route::post('/credit-notes/{creditNote}/cancel', [CreditNoteController::class, 'cancel']);
        Route::get('/credit-notes',                      [CreditNoteController::class, 'index']);
        Route::post('/credit-notes',                     [CreditNoteController::class, 'store']);
        Route::get('/credit-notes/{creditNote}',         [CreditNoteController::class, 'show']);

        // Accounting — Debit Notes (AR adjustment, opposite of Credit). DR AR / CR Revenue.
        // Optional invoice link is traceability only — does NOT modify invoice.paid_amount.
        // The debit-note balance stands as its own AR and is settled by a future Receipt.
        // Immutable once issued.
        Route::post('/debit-notes/{debitNote}/cancel', [DebitNoteController::class, 'cancel']);
        Route::get('/debit-notes',                     [DebitNoteController::class, 'index']);
        Route::post('/debit-notes',                    [DebitNoteController::class, 'store']);
        Route::get('/debit-notes/{debitNote}',         [DebitNoteController::class, 'show']);

        // Accounting — Bank Reconciliation. Sessions pair a bank statement period
        // with posted ledger_entries on the bank's GL. Immutable once closed
        // (reopen requires a separate `fms.bank_recon.reopen` perm).
        Route::get('/bank-reconciliations',                                  [BankReconController::class, 'index']);
        Route::post('/bank-reconciliations',                                 [BankReconController::class, 'store']);
        Route::get('/bank-reconciliations/{bankReconciliation}',             [BankReconController::class, 'show']);
        Route::post('/bank-reconciliations/{bankReconciliation}/close',      [BankReconController::class, 'close']);
        Route::post('/bank-reconciliations/{bankReconciliation}/reopen',     [BankReconController::class, 'reopen']);
        Route::post('/bank-reconciliations/{bankReconciliation}/statement-lines', [BankReconController::class, 'addLine']);
        Route::get('/bank-reconciliations/{bankReconciliation}/period-ledger-entries', [BankReconController::class, 'periodLedgerEntries']);
        Route::delete('/bank-reconciliation-statement-lines/{line}',         [BankReconController::class, 'removeLine']);
        Route::post('/bank-reconciliation-statement-lines/{line}/match',     [BankReconController::class, 'matchLine']);
        Route::post('/bank-reconciliation-statement-lines/{line}/unmatch',   [BankReconController::class, 'unmatchLine']);

        // Accounting - Budgets. Drafts are mutable, active/archived are locked.
        // Variance is computed at read time against posted ledger_entries.
        Route::post('/budgets/{budget}/activate', [BudgetController::class, 'activate']);
        Route::post('/budgets/{budget}/archive',  [BudgetController::class, 'archive']);
        Route::get('/budgets/{budget}/variance',  [BudgetController::class, 'variance']);
        Route::post('/budgets/{budget}/lines',    [BudgetController::class, 'addLine']);
        Route::patch('/budget-lines/{line}',      [BudgetController::class, 'updateLine']);
        Route::delete('/budget-lines/{line}',     [BudgetController::class, 'removeLine']);
        Route::apiResource('budgets', BudgetController::class);

        // Accounting - Fiscal Periods. Locked periods refuse new JE posts via the
        // write-block in AccountingService::postEntry. Close posts the rollover JE
        // (DR revenues, CR expenses, RE for the net) before flipping the lock.
        Route::get('/fiscal-periods/{fiscalPeriod}/closing-preview', [FiscalPeriodController::class, 'closingPreview']);
        Route::post('/fiscal-periods/{fiscalPeriod}/close',          [FiscalPeriodController::class, 'close']);
        Route::post('/fiscal-periods/{fiscalPeriod}/reopen',         [FiscalPeriodController::class, 'reopen']);
        Route::apiResource('fiscal-periods', FiscalPeriodController::class)
            ->parameters(['fiscal-periods' => 'fiscalPeriod']);

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

        // HRM Module - Holidays + Calendar feed (combined holidays + leaves).
        Route::get('/hrm/calendar', [HolidayController::class, 'calendar']);
        Route::get('/me/calendar',  [HolidayController::class, 'myCalendar']);
        Route::apiResource('holidays', HolidayController::class);

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
        // ESS portal — caller's own payslips. Force-scoped to the linked
        // employee_id; no admin permission required.
        Route::get('/me/payslips', [PayslipController::class, 'mine']);
        // Server-rendered PDF (DomPDF). Gated by PayslipPolicy::view —
        // owners + `hrm.payroll.read` admins.
        Route::get('/payslips/{payslip}/pdf', [PayslipController::class, 'pdf']);

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

        // HRM Module — Phase 8: Digital Offer & Onboarding Pipeline
        Route::apiResource('offers', OfferController::class);
        Route::post('/offers/{offer}/send', [OfferController::class, 'send']);
        Route::post('/offers/{offer}/accept', [OfferController::class, 'accept']);
        Route::post('/offers/{offer}/decline', [OfferController::class, 'decline']);

        Route::get('/onboarding-checklists', [OnboardingTaskController::class, 'indexChecklists']);
        Route::get('/onboarding-checklists/{checklist}', [OnboardingTaskController::class, 'showChecklist']);
        Route::get('/onboarding-tasks', [OnboardingTaskController::class, 'indexTasks']);
        Route::patch('/onboarding-tasks/{task}/status', [OnboardingTaskController::class, 'transition']);

        // HRM Module — Phase 10: Hierarchical Working Days/Hours
        Route::get('/work-schedules/snapshot', [WorkScheduleController::class, 'snapshot']);
        Route::get('/work-schedules', [WorkScheduleController::class, 'index']);
        Route::put('/work-schedules', [WorkScheduleController::class, 'upsert']);
        Route::delete('/work-schedules', [WorkScheduleController::class, 'destroy']);

        // HRM Module — Phase 4A: Employee Appointment (post-hire workflow)
        Route::post('/employee-appointments', [EmployeeAppointmentController::class, 'store']);
        Route::get('/employee-appointments/{appointment}', [EmployeeAppointmentController::class, 'show']);

        // HRM Module — Phase 4B: Performance
        Route::apiResource('appraisals', AppraisalController::class);
        Route::post('/appraisals/{appraisal}/submit', [AppraisalController::class, 'submit']);
        Route::post('/appraisals/{appraisal}/review', [AppraisalController::class, 'review']);
        Route::post('/appraisals/{appraisal}/close', [AppraisalController::class, 'close']);

        // HRM Phase 4 — 360-degree peer feedback on appraisals.
        Route::get('/appraisals/{appraisal}/peer-feedback', [AppraisalPeerFeedbackController::class, 'index']);
        Route::post('/appraisals/{appraisal}/peer-feedback/invite', [AppraisalPeerFeedbackController::class, 'invite']);
        Route::post('/appraisals/{appraisal}/peer-feedback/submit', [AppraisalPeerFeedbackController::class, 'submit']);

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

        // Assets Module — Fixed Asset Management
        //
        // Depreciation / revaluation / disposal logs live at /assets/* prefixes
        // that overlap with the {asset} param on apiResource. Declare them
        // BEFORE Route::apiResource so Laravel doesn't capture `depreciation`
        // as an {asset} UUID. The single-asset action routes use `/assets/{asset}/...`
        // which is unambiguous regardless of order.
        Route::get('/assets/depreciation', [DepreciationController::class, 'index']);
        Route::get('/assets/revaluations', [RevaluationController::class, 'index']);
        Route::get('/assets/disposals',    [DisposalController::class, 'index']);

        // Verification logs index — must come before the apiResource so
        // `/assets/verifications` isn't captured as `{asset}`.
        Route::get('/assets/verifications', [AssetVerificationController::class, 'index']);

        Route::apiResource('assets', AssetController::class);
        Route::get('/assets/{asset}/depreciation/preview', [DepreciationController::class, 'preview']);
        Route::post('/assets/{asset}/depreciate',          [DepreciationController::class, 'calculate']);
        Route::post('/assets/{asset}/revaluations',        [RevaluationController::class, 'store']);
        Route::post('/assets/{asset}/disposals',           [DisposalController::class, 'store']);

        // QR scan resolver — front-end calls this after the camera decodes
        // the QR. Returns the asset profile + active-campaign context.
        Route::get('/assets/{asset}/profile',       [AssetVerificationController::class, 'profile']);
        Route::post('/assets/{asset}/verifications',[AssetVerificationController::class, 'store']);

        // Audit campaigns (verification cycles).
        Route::apiResource('asset-audit-campaigns', AssetAuditCampaignController::class)
            ->parameters(['asset-audit-campaigns' => 'campaign']);
        Route::post('/asset-audit-campaigns/{campaign}/start',    [AssetAuditCampaignController::class, 'start']);
        Route::post('/asset-audit-campaigns/{campaign}/complete', [AssetAuditCampaignController::class, 'complete']);
        Route::get('/asset-audit-campaigns/{campaign}/reconciliation', [AssetAuditCampaignController::class, 'reconciliation']);

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
        Route::get('/projects/kpis', [ProjectController::class, 'kpis']);
        Route::get('/projects/{project}/budget-status', [ProjectController::class, 'budgetStatus']);
        Route::apiResource('projects', ProjectController::class);
        Route::patch('/tasks/{task}/status', [TaskController::class, 'updateStatus']);
        Route::apiResource('tasks', TaskController::class);
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

        // Ecommerce — admin surface (orders, refunds, customers).
        // Permission-gated via EcomOrderPolicy / EcomRefundPolicy / EcomCustomerPolicy
        // inside each controller; routes themselves require any admin login.
        Route::get('/ecommerce/orders', [EcommerceOrderController::class, 'index']);
        Route::get('/ecommerce/orders/{order}', [EcommerceOrderController::class, 'show']);
        Route::post('/ecommerce/orders/{order}/fulfilling', [EcommerceOrderController::class, 'markFulfilling']);
        Route::post('/ecommerce/orders/{order}/ship', [EcommerceOrderController::class, 'ship']);
        Route::post('/ecommerce/orders/{order}/delivered', [EcommerceOrderController::class, 'markDelivered']);
        Route::post('/ecommerce/orders/{order}/cancel', [EcommerceOrderController::class, 'cancel']);

        Route::get('/ecommerce/refunds', [EcommerceRefundController::class, 'index']);
        Route::get('/ecommerce/refunds/{refund}', [EcommerceRefundController::class, 'show']);
        Route::post('/ecommerce/refunds', [EcommerceRefundController::class, 'store']);
        Route::post('/ecommerce/refunds/{refund}/approve', [EcommerceRefundController::class, 'approve']);
        Route::post('/ecommerce/refunds/{refund}/reject', [EcommerceRefundController::class, 'reject']);

        Route::get('/ecommerce/customers', [EcommerceCustomerController::class, 'index']);
        Route::get('/ecommerce/customers/{customer}', [EcommerceCustomerController::class, 'show']);

        // POS — admin + cashier surface. Per-row policy gates inside controllers
        // (PosTerminalPolicy / PosShiftPolicy / PosOrderPolicy). Cashiers reach
        // the operational endpoints with the `cashier` role; admin/manager
        // unlocks reconciliation + void + terminal CRUD.
        Route::get('/pos/terminals', [PosTerminalController::class, 'index']);
        Route::post('/pos/terminals', [PosTerminalController::class, 'store']);
        Route::get('/pos/terminals/{terminal}', [PosTerminalController::class, 'show']);
        Route::put('/pos/terminals/{terminal}', [PosTerminalController::class, 'update']);
        Route::delete('/pos/terminals/{terminal}', [PosTerminalController::class, 'destroy']);

        Route::get('/pos/shifts', [PosShiftController::class, 'index']);
        Route::get('/pos/shifts/me', [PosShiftController::class, 'me']);
        Route::post('/pos/shifts/open', [PosShiftController::class, 'open']);
        Route::get('/pos/shifts/{shift}', [PosShiftController::class, 'show']);
        Route::post('/pos/shifts/{shift}/close', [PosShiftController::class, 'close']);
        Route::post('/pos/shifts/{shift}/reconcile', [PosShiftController::class, 'reconcile']);

        Route::get('/pos/orders', [PosOrderController::class, 'index']);
        Route::post('/pos/orders', [PosOrderController::class, 'store']);
        Route::get('/pos/orders/{order}', [PosOrderController::class, 'show']);
        Route::post('/pos/orders/{order}/void', [PosOrderController::class, 'void']);

        // Calendar - unified events feed + custom event CRUD. Holiday CRUD
        // stays on the existing HRM endpoints (`/holidays`); this is purely
        // the per-employee calendar view.
        Route::get('/calendar/events', [CalendarEventController::class, 'index']);
        Route::post('/calendar/events', [CalendarEventController::class, 'store']);
        Route::get('/calendar/events/{event}', [CalendarEventController::class, 'show']);
        Route::put('/calendar/events/{event}', [CalendarEventController::class, 'update']);
        Route::delete('/calendar/events/{event}', [CalendarEventController::class, 'destroy']);
    });

    // Ecommerce — shopper-authenticated surface (requires the `shop` Passport
    // guard, which is backed by the EcomCustomer model). Separate group from
    // `auth:api` so a leaked shopper token can't act on admin endpoints.
    Route::middleware('auth:shop')->group(function () {
        Route::get('/shop/auth/me', [ShopperAuthController::class, 'me']);
        Route::post('/shop/auth/logout', [ShopperAuthController::class, 'logout']);

        Route::get('/shop/addresses', [ShopperAddressController::class, 'index']);
        Route::post('/shop/addresses', [ShopperAddressController::class, 'store']);
        Route::put('/shop/addresses/{address}', [ShopperAddressController::class, 'update']);
        Route::delete('/shop/addresses/{address}', [ShopperAddressController::class, 'destroy']);

        Route::get('/shop/orders', [ShopperOrderController::class, 'index']);
        Route::get('/shop/orders/{order}', [ShopperOrderController::class, 'show']);
    });
});