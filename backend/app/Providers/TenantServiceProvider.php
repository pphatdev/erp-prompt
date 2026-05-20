<?php

namespace App\Providers;

use App\Models\Tenant\Application;
use App\Models\Tenant\Appraisal;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Interview;
use App\Models\Tenant\JobVacancy;
use App\Models\Tenant\Leave;
use App\Models\Tenant\LeaveType;
use App\Models\Tenant\Quiz;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use App\Policies\ApplicationPolicy;
use App\Policies\AppraisalPolicy;
use App\Policies\EmployeePolicy;
use App\Policies\InterviewPolicy;
use App\Policies\JobVacancyPolicy;
use App\Policies\LeavePolicy;
use App\Policies\LeaveTypePolicy;
use App\Policies\QuizPolicy;
use App\Policies\RolePolicy;
use App\Policies\UserPolicy;
use App\Tenants\Modules\Approvals\Events\ApprovalRequestFinalized;
use App\Tenants\Modules\HRM\Listeners\SyncLeaveFromApproval;
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
        Gate::policy(Leave::class, LeavePolicy::class);
        Gate::policy(LeaveType::class, LeaveTypePolicy::class);
        Gate::policy(JobVacancy::class, JobVacancyPolicy::class);
        Gate::policy(Application::class, ApplicationPolicy::class);
        Gate::policy(Appraisal::class, AppraisalPolicy::class);
        Gate::policy(Quiz::class, QuizPolicy::class);
        Gate::policy(Interview::class, InterviewPolicy::class);

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

        Passport::enablePasswordGrant();
    }
}
