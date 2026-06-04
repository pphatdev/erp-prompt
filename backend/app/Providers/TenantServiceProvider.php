<?php

namespace App\Providers;

use App\Models\Tenant\Account;
use App\Models\Tenant\Application;
use App\Models\Tenant\BankAccount;
use App\Models\Tenant\Bill;
use App\Models\Tenant\BillPayment;
use App\Models\Tenant\BankReconSession;
use App\Models\Tenant\Budget;
use App\Models\Tenant\CashAdvance;
use App\Models\Tenant\FiscalPeriod;
use App\Models\Tenant\CashAdvanceSettlement;
use App\Models\Tenant\CreditNote;
use App\Models\Tenant\DebitNote;
use App\Models\Tenant\Expense;
use App\Models\Tenant\Project;
use App\Models\Tenant\Receipt;
use App\Models\Tenant\Task;
use App\Models\Tenant\Timesheet;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\LedgerEntry;
use App\Models\Tenant\Reimbursement;
use App\Models\Tenant\Appraisal;
use App\Models\Tenant\Asset;
use App\Models\Tenant\AssetAuditCampaign;
use App\Models\Tenant\AttendanceLog;
use App\Models\Tenant\CrmActivity;
use App\Models\Tenant\CrmAppointment;
use App\Models\Tenant\CrmContact;
use App\Models\Tenant\Dashboard;
use App\Models\Tenant\Department;
use App\Models\Tenant\Document;
use App\Models\Tenant\EcomCustomer;
use App\Models\Tenant\EcomOrder;
use App\Models\Tenant\EcomRefund;
use App\Models\Tenant\Employee;
use App\Models\Tenant\CalendarEvent;
use App\Models\Tenant\PosOrder;
use App\Models\Tenant\PosShift;
use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\Folder;
use App\Models\Tenant\Interview;
use App\Models\Tenant\JobVacancy;
use App\Models\Tenant\Offer;
use App\Models\Tenant\OnboardingChecklist;
use App\Models\Tenant\OnboardingTask;
use App\Models\Tenant\WorkSchedule;
use App\Models\Tenant\Holiday;
use App\Models\Tenant\Lead;
use App\Models\Tenant\Leave;
use App\Models\Tenant\LeaveType;
use App\Models\Tenant\LowStockAlert;
use App\Models\Tenant\Opportunity;
use App\Models\Tenant\OvertimeRequest;
use App\Models\Tenant\PayrollPeriod;
use App\Models\Tenant\Payslip;
use App\Models\Tenant\Position;
use App\Models\Tenant\PurchaseOrder;
use App\Models\Tenant\Quiz;
use App\Models\Tenant\Role;
use App\Models\Tenant\Shift;
use App\Models\Tenant\StockReservation;
use App\Models\Tenant\StockTransfer;
use App\Models\Tenant\Category;
use App\Models\Tenant\ExchangeRate;
use App\Models\Tenant\FuelLog;
use App\Models\Tenant\MaintenanceLog;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\Tag;
use App\Models\Tenant\User;
use App\Models\Tenant\Vehicle;
use App\Models\Tenant\VehicleModel;
use App\Models\Tenant\Warehouse;
use App\Policies\AccountPolicy;
use App\Policies\ApplicationPolicy;
use App\Policies\BankAccountPolicy;
use App\Policies\BillPolicy;
use App\Policies\BillPaymentPolicy;
use App\Policies\BankReconSessionPolicy;
use App\Policies\BudgetPolicy;
use App\Policies\CashAdvancePolicy;
use App\Policies\FiscalPeriodPolicy;
use App\Policies\ProjectPolicy;
use App\Policies\TaskPolicy;
use App\Policies\TimesheetPolicy;
use App\Policies\CashAdvanceSettlementPolicy;
use App\Policies\CreditNotePolicy;
use App\Policies\DebitNotePolicy;
use App\Policies\ExpensePolicy;
use App\Policies\ReceiptPolicy;
use App\Policies\JournalEntryPolicy;
use App\Policies\LedgerEntryPolicy;
use App\Policies\ReimbursementPolicy;
use App\Policies\AppraisalPolicy;
use App\Policies\AssetAuditCampaignPolicy;
use App\Policies\AssetPolicy;
use App\Policies\AttendanceLogPolicy;
use App\Policies\CrmActivityPolicy;
use App\Policies\CrmAppointmentPolicy;
use App\Policies\CrmContactPolicy;
use App\Policies\DashboardPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\DocumentPolicy;
use App\Policies\DocumentTagPolicy;
use App\Policies\EcomCustomerPolicy;
use App\Policies\EcomOrderPolicy;
use App\Policies\EcomRefundPolicy;
use App\Policies\CalendarEventPolicy;
use App\Policies\PosOrderPolicy;
use App\Policies\PosShiftPolicy;
use App\Policies\PosTerminalPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\FolderPolicy;
use App\Policies\InterviewPolicy;
use App\Policies\JobVacancyPolicy;
use App\Policies\OfferPolicy;
use App\Policies\OnboardingChecklistPolicy;
use App\Policies\OnboardingTaskPolicy;
use App\Policies\WorkSchedulePolicy;
use App\Policies\HolidayPolicy;
use App\Policies\LeadPolicy;
use App\Policies\LeavePolicy;
use App\Policies\LeaveTypePolicy;
use App\Policies\LowStockAlertPolicy;
use App\Policies\OpportunityPolicy;
use App\Policies\OvertimeRequestPolicy;
use App\Policies\PayrollPeriodPolicy;
use App\Policies\PayslipPolicy;
use App\Policies\PositionPolicy;
use App\Policies\PurchaseOrderPolicy;
use App\Policies\QuizPolicy;
use App\Policies\RolePolicy;
use App\Policies\ShiftPolicy;
use App\Policies\StockReservationPolicy;
use App\Policies\StockTransferPolicy;
use App\Policies\CategoryPolicy;
use App\Policies\ExchangeRatePolicy;
use App\Policies\FuelLogPolicy;
use App\Policies\MaintenanceLogPolicy;
use App\Policies\ProductVariantPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UserPolicy;
use App\Policies\VehicleModelPolicy;
use App\Policies\VehiclePolicy;
use App\Policies\WarehousePolicy;
use App\Tenants\Modules\Approvals\Events\ApprovalRequestFinalized;
use App\Tenants\Modules\Crm\Events\LeadQualified;
use App\Tenants\Modules\HRM\Listeners\SyncEmployeeAppointmentFromApproval;
use App\Tenants\Modules\HRM\Listeners\SyncLeaveFromApproval;
use App\Tenants\Modules\Inventory\Events\ProductWentBelowMinimumStock;
use App\Tenants\Modules\Inventory\Listeners\RecordLowStockAlert;
use App\Tenants\Modules\Inventory\Listeners\SyncPurchaseOrderFromApproval;
use App\Tenants\Modules\Sales\Listeners\HandleLeadQualified;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;

class TenantServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Register tenant services (ERP modules binding)
    }

    public function boot(): void
    {
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Role::class, RolePolicy::class);
        Gate::policy(Employee::class, EmployeePolicy::class);
        Gate::policy(Department::class, DepartmentPolicy::class);
        Gate::policy(Position::class, PositionPolicy::class);
        Gate::policy(Leave::class, LeavePolicy::class);
        Gate::policy(LeaveType::class, LeaveTypePolicy::class);
        Gate::policy(Holiday::class, HolidayPolicy::class);
        Gate::policy(PayrollPeriod::class, PayrollPeriodPolicy::class);
        Gate::policy(Payslip::class, PayslipPolicy::class);
        Gate::policy(JobVacancy::class, JobVacancyPolicy::class);
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(Appraisal::class, AppraisalPolicy::class);
        Gate::policy(Quiz::class, QuizPolicy::class);
        Gate::policy(Interview::class, InterviewPolicy::class);
        Gate::policy(Offer::class, OfferPolicy::class);
        Gate::policy(OnboardingChecklist::class, OnboardingChecklistPolicy::class);
        Gate::policy(OnboardingTask::class, OnboardingTaskPolicy::class);
        Gate::policy(WorkSchedule::class, WorkSchedulePolicy::class);
        Gate::policy(Dashboard::class, DashboardPolicy::class);
        Gate::policy(Shift::class, ShiftPolicy::class);
        Gate::policy(AttendanceLog::class, AttendanceLogPolicy::class);
        Gate::policy(OvertimeRequest::class, OvertimeRequestPolicy::class);
        Gate::policy(Lead::class, LeadPolicy::class);
        Gate::policy(Opportunity::class, OpportunityPolicy::class);
        Gate::policy(CrmContact::class, CrmContactPolicy::class);
        Gate::policy(CrmActivity::class, CrmActivityPolicy::class);
        Gate::policy(CrmAppointment::class, CrmAppointmentPolicy::class);
        Gate::policy(Warehouse::class, WarehousePolicy::class);
        Gate::policy(Supplier::class, SupplierPolicy::class);
        Gate::policy(Category::class, CategoryPolicy::class);
        Gate::policy(ProductVariant::class, ProductVariantPolicy::class);
        Gate::policy(ExchangeRate::class, ExchangeRatePolicy::class);
        Gate::policy(Account::class, AccountPolicy::class);
        Gate::policy(JournalEntry::class, JournalEntryPolicy::class);
        Gate::policy(LedgerEntry::class, LedgerEntryPolicy::class);
        Gate::policy(BankAccount::class, BankAccountPolicy::class);
        Gate::policy(Bill::class, BillPolicy::class);
        Gate::policy(BillPayment::class, BillPaymentPolicy::class);
        Gate::policy(Reimbursement::class, ReimbursementPolicy::class);
        Gate::policy(CashAdvance::class, CashAdvancePolicy::class);
        Gate::policy(CashAdvanceSettlement::class, CashAdvanceSettlementPolicy::class);
        Gate::policy(Expense::class, ExpensePolicy::class);
        Gate::policy(Receipt::class, ReceiptPolicy::class);
        Gate::policy(CreditNote::class, CreditNotePolicy::class);
        Gate::policy(DebitNote::class, DebitNotePolicy::class);
        Gate::policy(BankReconSession::class, BankReconSessionPolicy::class);
        Gate::policy(Budget::class, BudgetPolicy::class);
        Gate::policy(FiscalPeriod::class, FiscalPeriodPolicy::class);
        Gate::policy(Project::class, ProjectPolicy::class);
        Gate::policy(Task::class, TaskPolicy::class);
        Gate::policy(Timesheet::class, TimesheetPolicy::class);
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(StockReservation::class, StockReservationPolicy::class);
        Gate::policy(StockTransfer::class, StockTransferPolicy::class);
        Gate::policy(LowStockAlert::class, LowStockAlertPolicy::class);

        // eDocuments
        Gate::policy(Document::class, DocumentPolicy::class);
        Gate::policy(Folder::class, FolderPolicy::class);
        Gate::policy(Tag::class, DocumentTagPolicy::class);

        // Fleet
        Gate::policy(Vehicle::class, VehiclePolicy::class);
        Gate::policy(VehicleModel::class, VehicleModelPolicy::class);
        Gate::policy(MaintenanceLog::class, MaintenanceLogPolicy::class);
        Gate::policy(FuelLog::class, FuelLogPolicy::class);

        // Assets (fixed asset management)
        Gate::policy(Asset::class, AssetPolicy::class);
        Gate::policy(AssetAuditCampaign::class, AssetAuditCampaignPolicy::class);

        // Ecommerce
        Gate::policy(EcomOrder::class, EcomOrderPolicy::class);
        Gate::policy(EcomRefund::class, EcomRefundPolicy::class);
        Gate::policy(EcomCustomer::class, EcomCustomerPolicy::class);

        // POS
        Gate::policy(PosTerminal::class, PosTerminalPolicy::class);
        Gate::policy(PosShift::class, PosShiftPolicy::class);
        Gate::policy(PosOrder::class, PosOrderPolicy::class);

        // Calendar
        Gate::policy(CalendarEvent::class, CalendarEventPolicy::class);

        // Super Admin bypass: users holding the `admin` role short-circuit all
        // policy checks. Must return null (not false) for non-admins so other
        // policies/abilities still apply.
        Gate::before(function (User $user) {
            if ($user->roles()->where('slug', 'admin')->exists()) {
                return true;
            }
            return null;
        });

        Event::listen(ApprovalRequestFinalized::class, SyncLeaveFromApproval::class);
        Event::listen(ApprovalRequestFinalized::class, SyncEmployeeAppointmentFromApproval::class);
        Event::listen(ApprovalRequestFinalized::class, SyncPurchaseOrderFromApproval::class);
        Event::listen(LeadQualified::class, HandleLeadQualified::class);
        Event::listen(ProductWentBelowMinimumStock::class, RecordLowStockAlert::class);

        Passport::enablePasswordGrant();
    }
}
