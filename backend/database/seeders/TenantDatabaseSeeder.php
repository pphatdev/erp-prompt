<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;

class TenantDatabaseSeeder extends Seeder
{
    /**
     * Seed the tenant's database.
     */
    public function run(): void
    {
        $permissions = [
            // IAM
            ['name' => 'Read Tenants', 'slug' => 'iam.tenants.read', 'module' => 'iam', 'feature' => 'tenants', 'action' => 'read'],
            ['name' => 'Write Tenants', 'slug' => 'iam.tenants.write', 'module' => 'iam', 'feature' => 'tenants', 'action' => 'write'],
            ['name' => 'Read Users', 'slug' => 'iam.users.read', 'module' => 'iam', 'feature' => 'users', 'action' => 'read'],
            ['name' => 'Write Users', 'slug' => 'iam.users.write', 'module' => 'iam', 'feature' => 'users', 'action' => 'write'],
            ['name' => 'Delete Users', 'slug' => 'iam.users.delete', 'module' => 'iam', 'feature' => 'users', 'action' => 'delete'],
            ['name' => 'Read Roles', 'slug' => 'iam.roles.read', 'module' => 'iam', 'feature' => 'roles', 'action' => 'read'],
            ['name' => 'Write Roles', 'slug' => 'iam.roles.write', 'module' => 'iam', 'feature' => 'roles', 'action' => 'write'],
            ['name' => 'Delete Roles', 'slug' => 'iam.roles.delete', 'module' => 'iam', 'feature' => 'roles', 'action' => 'delete'],
            ['name' => 'Read Audit', 'slug' => 'iam.audit.read', 'module' => 'iam', 'feature' => 'audit', 'action' => 'read'],

            // Sales & CRM
            ['name' => 'Read CRM', 'slug' => 'sales.crm.read', 'module' => 'sales', 'feature' => 'crm', 'action' => 'read'],
            ['name' => 'Write CRM', 'slug' => 'sales.crm.write', 'module' => 'sales', 'feature' => 'crm', 'action' => 'write'],
            ['name' => 'Read Leads', 'slug' => 'sales.leads.read', 'module' => 'sales', 'feature' => 'leads', 'action' => 'read'],
            ['name' => 'Write Leads', 'slug' => 'sales.leads.write', 'module' => 'sales', 'feature' => 'leads', 'action' => 'write'],
            ['name' => 'Read Orders', 'slug' => 'sales.orders.read', 'module' => 'sales', 'feature' => 'orders', 'action' => 'read'],
            ['name' => 'Write Orders', 'slug' => 'sales.orders.write', 'module' => 'sales', 'feature' => 'orders', 'action' => 'write'],

            // HRM Self Service & Workforce
            ['name' => 'Read Leaves', 'slug' => 'hrm.leave.read', 'module' => 'hrm', 'feature' => 'leave', 'action' => 'read'],
            ['name' => 'Write Leaves', 'slug' => 'hrm.leave.write', 'module' => 'hrm', 'feature' => 'leave', 'action' => 'write'],
            ['name' => 'Read Appraisals', 'slug' => 'hrm.performance.read', 'module' => 'hrm', 'feature' => 'performance', 'action' => 'read'],
            ['name' => 'Read Payroll/Payslips', 'slug' => 'hrm.payroll.read', 'module' => 'hrm', 'feature' => 'payroll', 'action' => 'read'],
        ];

        foreach ($permissions as $permission) {
            Permission::updateOrCreate(['slug' => $permission['slug']], $permission);
        }

        // Create Admin Role
        $adminRole = Role::updateOrCreate(['slug' => 'admin'], [
            'name' => 'Administrator',
            'description' => 'Full access to all system features.',
        ]);

        $adminRole->permissions()->sync(Permission::all());

        // Create Admin User
        $adminUser = \App\Models\Tenant\User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Administrator',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'is_active' => true,
            ]
        );

        // Assign Admin Role
        if (!$adminUser->roles->contains($adminRole->id)) {
            $adminUser->roles()->attach($adminRole->id);
        }

        // Create Employee Role
        $employeeRole = Role::updateOrCreate(['slug' => 'employee'], [
            'name' => 'Employee',
            'description' => 'Standard employee role with access to self-service portals.',
        ]);

        $employeeRole->permissions()->sync(
            Permission::whereIn('slug', [
                'hrm.leave.read',
                'hrm.leave.write',
                'hrm.performance.read',
                'hrm.payroll.read'
            ])->get()
        );

        // Create Employee User
        $employeeUser = \App\Models\Tenant\User::firstOrCreate(
            ['email' => 'role.base@tanent.com'],
            [
                'name' => 'Base Employee User',
                'password' => \Illuminate\Support\Facades\Hash::make('password'),
                'is_active' => true,
            ]
        );

        // Assign Employee Role
        if (!$employeeUser->roles->contains($employeeRole->id)) {
            $employeeUser->roles()->attach($employeeRole->id);
        }

        // Create Employee Record linked to the User
        if (\Illuminate\Support\Facades\Schema::hasTable('employees')) {
            \App\Models\Tenant\Employee::firstOrCreate(
                ['email' => 'role.base@tanent.com'],
                [
                    'employee_id' => 'EMP-001',
                    'first_name' => 'Base',
                    'last_name' => 'Employee',
                    'user_id' => $employeeUser->id,
                    'status' => 'active',
                    'hired_at' => now()->toDateString(),
                ]
            );
        }

        // Seed default workflow statuses for every HRM module
        $this->seedWorkflowStatuses();

        // Create Passport Personal Access Client if it doesn't exist
        if (\Illuminate\Support\Facades\Schema::hasTable('oauth_clients')) {
            $clientExists = \Illuminate\Support\Facades\DB::table('oauth_clients')
                ->where('personal_access_client', true)
                ->exists();

            if (!$clientExists) {
                $clientRepository = app(\Laravel\Passport\ClientRepository::class);
                $clientRepository->createPersonalAccessClient(
                    null, 'Tenant Personal Access Client', 'http://localhost'
                );
            }
        }
    }

    /**
     * Idempotent seed of the canonical status flows. Re-running this is safe
     * because each row is keyed by (tenant_id, module, key) via updateOrCreate.
     */
    private function seedWorkflowStatuses(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('workflow_statuses')) {
            return;
        }

        $modules = [
            'hrm.application' => [
                ['key' => 'applied',              'label' => 'Applied',              'color' => 'secondary', 'icon' => 'ti-send',           'sequence' => 1,  'is_initial' => true,  'is_terminal' => false, 'allowed_next' => ['screening', 'rejected', 'withdrawn']],
                ['key' => 'screening',            'label' => 'Screening',            'color' => 'info',      'icon' => 'ti-eye-search',     'sequence' => 2,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['assessment', 'interview', 'rejected', 'withdrawn']],
                ['key' => 'assessment',           'label' => 'Assessment',           'color' => 'info',      'icon' => 'ti-clipboard-list', 'sequence' => 3,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['assessment_completed', 'rejected', 'withdrawn']],
                ['key' => 'assessment_completed', 'label' => 'Assessment Completed', 'color' => 'info',      'icon' => 'ti-clipboard-check','sequence' => 4,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['interview', 'rejected', 'withdrawn']],
                ['key' => 'interview',            'label' => 'Interview',            'color' => 'warning',   'icon' => 'ti-message-circle', 'sequence' => 5,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['offer', 'rejected', 'withdrawn']],
                ['key' => 'offer',                'label' => 'Offer',                'color' => 'primary',   'icon' => 'ti-mail-share',     'sequence' => 6,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['hired', 'rejected', 'withdrawn']],
                ['key' => 'hired',                'label' => 'Hired',                'color' => 'success',   'icon' => 'ti-circle-check',   'sequence' => 7,  'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
                ['key' => 'rejected',             'label' => 'Rejected',             'color' => 'danger',    'icon' => 'ti-x',              'sequence' => 90, 'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
                ['key' => 'withdrawn',            'label' => 'Withdrawn',            'color' => 'secondary', 'icon' => 'ti-arrow-back-up',  'sequence' => 91, 'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
            ],
            'hrm.leave' => [
                ['key' => 'pending',    'label' => 'Pending',    'color' => 'warning',   'icon' => 'ti-clock',          'sequence' => 1,  'is_initial' => true,  'is_terminal' => false, 'allowed_next' => ['approved', 'rejected']],
                ['key' => 'approved',   'label' => 'Approved',   'color' => 'success',   'icon' => 'ti-circle-check',   'sequence' => 2,  'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
                ['key' => 'rejected',   'label' => 'Rejected',   'color' => 'danger',    'icon' => 'ti-x',              'sequence' => 3,  'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
            ],
            'hrm.appraisal' => [
                ['key' => 'draft',      'label' => 'Draft',      'color' => 'secondary', 'icon' => 'ti-pencil',         'sequence' => 1,  'is_initial' => true,  'is_terminal' => false, 'allowed_next' => ['submitted']],
                ['key' => 'submitted',  'label' => 'Submitted',  'color' => 'info',      'icon' => 'ti-send',           'sequence' => 2,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['reviewed', 'draft']],
                ['key' => 'reviewed',   'label' => 'Reviewed',   'color' => 'warning',   'icon' => 'ti-stars',          'sequence' => 3,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['closed']],
                ['key' => 'closed',     'label' => 'Closed',     'color' => 'success',   'icon' => 'ti-lock',           'sequence' => 4,  'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
            ],
            'hrm.vacancy' => [
                ['key' => 'draft',      'label' => 'Draft',      'color' => 'secondary', 'icon' => 'ti-pencil',         'sequence' => 1,  'is_initial' => true,  'is_terminal' => false, 'allowed_next' => ['open']],
                ['key' => 'open',       'label' => 'Open',       'color' => 'success',   'icon' => 'ti-door-enter',     'sequence' => 2,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['paused', 'closed', 'filled']],
                ['key' => 'paused',     'label' => 'Paused',     'color' => 'warning',   'icon' => 'ti-player-pause',   'sequence' => 3,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['open', 'closed']],
                ['key' => 'closed',     'label' => 'Closed',     'color' => 'danger',    'icon' => 'ti-lock',           'sequence' => 4,  'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
                ['key' => 'filled',     'label' => 'Filled',     'color' => 'primary',   'icon' => 'ti-trophy',         'sequence' => 5,  'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
            ],
            'hrm.employee' => [
                ['key' => 'active',     'label' => 'Active',     'color' => 'success',   'icon' => 'ti-user-check',     'sequence' => 1,  'is_initial' => true,  'is_terminal' => false, 'allowed_next' => ['on_leave', 'terminated']],
                ['key' => 'on_leave',   'label' => 'On Leave',   'color' => 'warning',   'icon' => 'ti-calendar-event', 'sequence' => 2,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['active', 'terminated']],
                ['key' => 'terminated', 'label' => 'Terminated', 'color' => 'danger',    'icon' => 'ti-user-off',       'sequence' => 3,  'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
            ],
            'hrm.payroll_period' => [
                ['key' => 'draft',      'label' => 'Draft',      'color' => 'secondary', 'icon' => 'ti-pencil',         'sequence' => 1,  'is_initial' => true,  'is_terminal' => false, 'allowed_next' => ['processed']],
                ['key' => 'processed',  'label' => 'Processed',  'color' => 'success',   'icon' => 'ti-circle-check',   'sequence' => 2,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['closed']],
                ['key' => 'closed',     'label' => 'Closed',     'color' => 'warning',   'icon' => 'ti-lock',           'sequence' => 3,  'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
            ],
            'hrm.quiz_attempt' => [
                ['key' => 'invited',     'label' => 'Invited',     'color' => 'secondary', 'icon' => 'ti-mail',           'sequence' => 1, 'is_initial' => true,  'is_terminal' => false, 'allowed_next' => ['in_progress', 'expired', 'abandoned']],
                ['key' => 'in_progress', 'label' => 'In Progress', 'color' => 'info',      'icon' => 'ti-progress',       'sequence' => 2, 'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['completed', 'expired', 'abandoned']],
                ['key' => 'completed',   'label' => 'Completed',   'color' => 'success',   'icon' => 'ti-circle-check',   'sequence' => 3, 'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
                ['key' => 'expired',     'label' => 'Expired',     'color' => 'danger',    'icon' => 'ti-clock-cancel',   'sequence' => 4, 'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
                ['key' => 'abandoned',   'label' => 'Abandoned',   'color' => 'secondary', 'icon' => 'ti-door-exit',      'sequence' => 5, 'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
            ],
            'hrm.interview' => [
                ['key' => 'scheduled', 'label' => 'Scheduled', 'color' => 'info',      'icon' => 'ti-calendar-event',    'sequence' => 1, 'is_initial' => true,  'is_terminal' => false, 'allowed_next' => ['completed', 'cancelled', 'no_show']],
                ['key' => 'completed', 'label' => 'Completed', 'color' => 'success',   'icon' => 'ti-circle-check',      'sequence' => 2, 'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
                ['key' => 'cancelled', 'label' => 'Cancelled', 'color' => 'secondary', 'icon' => 'ti-calendar-cancel',   'sequence' => 3, 'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
                ['key' => 'no_show',   'label' => 'No Show',   'color' => 'danger',    'icon' => 'ti-user-x',            'sequence' => 4, 'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
            ],
        ];

        foreach ($modules as $module => $rows) {
            foreach ($rows as $row) {
                \App\Models\Tenant\WorkflowStatus::updateOrCreate(
                    ['module' => $module, 'key' => $row['key']],
                    array_merge($row, ['module' => $module])
                );
            }
        }
    }
}
