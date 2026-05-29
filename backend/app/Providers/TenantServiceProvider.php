<?php

namespace App\Providers;

use App\Models\Tenant\Application;
use App\Models\Tenant\Appraisal;
use App\Models\Tenant\AttendanceLog;
use App\Models\Tenant\CrmActivity;
use App\Models\Tenant\CrmAppointment;
use App\Models\Tenant\CrmContact;
use App\Models\Tenant\Dashboard;
use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Interview;
use App\Models\Tenant\JobVacancy;
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
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\Supplier;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Policies\ApplicationPolicy;
use App\Policies\AppraisalPolicy;
use App\Policies\AttendanceLogPolicy;
use App\Policies\CrmActivityPolicy;
use App\Policies\CrmAppointmentPolicy;
use App\Policies\CrmContactPolicy;
use App\Policies\DashboardPolicy;
use App\Policies\DepartmentPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\InterviewPolicy;
use App\Policies\JobVacancyPolicy;
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
use App\Policies\ProductVariantPolicy;
use App\Policies\SupplierPolicy;
use App\Policies\UserPolicy;
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
        Gate::policy(PayrollPeriod::class, PayrollPeriodPolicy::class);
        Gate::policy(Payslip::class, PayslipPolicy::class);
        Gate::policy(JobVacancy::class, JobVacancyPolicy::class);
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(Appraisal::class, AppraisalPolicy::class);
        Gate::policy(Quiz::class, QuizPolicy::class);
        Gate::policy(Interview::class, InterviewPolicy::class);
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
        Gate::policy(PurchaseOrder::class, PurchaseOrderPolicy::class);
        Gate::policy(StockReservation::class, StockReservationPolicy::class);
        Gate::policy(StockTransfer::class, StockTransferPolicy::class);
        Gate::policy(LowStockAlert::class, LowStockAlertPolicy::class);

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
