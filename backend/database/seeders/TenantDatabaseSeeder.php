<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
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

            // HRM — Employees / Workforce (admin scope)
            ['name' => 'Read Employees',   'slug' => 'hrm.employee.read',   'module' => 'hrm', 'feature' => 'employee',   'action' => 'read'],
            ['name' => 'Write Employees',  'slug' => 'hrm.employee.write',  'module' => 'hrm', 'feature' => 'employee',   'action' => 'write'],
            ['name' => 'Delete Employees', 'slug' => 'hrm.employee.delete', 'module' => 'hrm', 'feature' => 'employee',   'action' => 'delete'],

            // HRM — Leave (admin scope)
            ['name' => 'Read Leaves',      'slug' => 'hrm.leave.read',      'module' => 'hrm', 'feature' => 'leave',      'action' => 'read'],
            ['name' => 'Write Leaves',     'slug' => 'hrm.leave.write',     'module' => 'hrm', 'feature' => 'leave',      'action' => 'write'],
            ['name' => 'Delete Leaves',    'slug' => 'hrm.leave.delete',    'module' => 'hrm', 'feature' => 'leave',      'action' => 'delete'],

            // HRM — Performance / Appraisals (admin scope)
            ['name' => 'Read Appraisals',  'slug' => 'hrm.performance.read',  'module' => 'hrm', 'feature' => 'performance', 'action' => 'read'],
            ['name' => 'Write Appraisals', 'slug' => 'hrm.performance.write', 'module' => 'hrm', 'feature' => 'performance', 'action' => 'write'],

            // HRM — Payroll & Payslips (admin scope)
            ['name' => 'Read Payroll/Payslips',  'slug' => 'hrm.payroll.read',  'module' => 'hrm', 'feature' => 'payroll', 'action' => 'read'],
            ['name' => 'Write Payroll Periods',  'slug' => 'hrm.payroll.write', 'module' => 'hrm', 'feature' => 'payroll', 'action' => 'write'],

            // HRM — Recruitment (admin scope)
            ['name' => 'Read Recruitment',   'slug' => 'hrm.recruitment.read',   'module' => 'hrm', 'feature' => 'recruitment', 'action' => 'read'],
            ['name' => 'Write Recruitment',  'slug' => 'hrm.recruitment.write',  'module' => 'hrm', 'feature' => 'recruitment', 'action' => 'write'],
            ['name' => 'Delete Recruitment', 'slug' => 'hrm.recruitment.delete', 'module' => 'hrm', 'feature' => 'recruitment', 'action' => 'delete'],

            // HRM — Quiz Authoring (admin scope)
            ['name' => 'Read Quizzes',   'slug' => 'hrm.quiz.read',   'module' => 'hrm', 'feature' => 'quiz', 'action' => 'read'],
            ['name' => 'Write Quizzes',  'slug' => 'hrm.quiz.write',  'module' => 'hrm', 'feature' => 'quiz', 'action' => 'write'],
            ['name' => 'Delete Quizzes', 'slug' => 'hrm.quiz.delete', 'module' => 'hrm', 'feature' => 'quiz', 'action' => 'delete'],

            // Reporting — Dashboards & Widgets
            ['name' => 'Read Dashboards',   'slug' => 'reporting.dashboard.read',   'module' => 'reporting', 'feature' => 'dashboard', 'action' => 'read'],
            ['name' => 'Write Dashboards',  'slug' => 'reporting.dashboard.write',  'module' => 'reporting', 'feature' => 'dashboard', 'action' => 'write'],
            ['name' => 'Delete Dashboards', 'slug' => 'reporting.dashboard.delete', 'module' => 'reporting', 'feature' => 'dashboard', 'action' => 'delete'],
            ['name' => 'Export Dashboards', 'slug' => 'reporting.dashboard.export', 'module' => 'reporting', 'feature' => 'dashboard', 'action' => 'export'],

            // Settings — Tenant configuration (branding, locale, security, etc.)
            ['name' => 'Read Settings',  'slug' => 'settings.read',  'module' => 'settings', 'feature' => 'settings', 'action' => 'read'],
            ['name' => 'Write Settings', 'slug' => 'settings.write', 'module' => 'settings', 'feature' => 'settings', 'action' => 'write'],

            // HRM — Self-Service (`.self` scope). Granted to the standard
            // `employee` role; each pairs with the matching admin permission so
            // policies can gate "own row + .self" OR "any row + admin".
            ['name' => 'Read Own Employee Profile',   'slug' => 'hrm.employee.read.self',     'module' => 'hrm', 'feature' => 'employee',    'action' => 'read.self'],
            ['name' => 'Update Own Employee Profile', 'slug' => 'hrm.employee.write.self',    'module' => 'hrm', 'feature' => 'employee',    'action' => 'write.self'],
            ['name' => 'Read Own Leaves',             'slug' => 'hrm.leave.read.self',        'module' => 'hrm', 'feature' => 'leave',       'action' => 'read.self'],
            ['name' => 'Submit Own Leaves',           'slug' => 'hrm.leave.write.self',       'module' => 'hrm', 'feature' => 'leave',       'action' => 'write.self'],
            ['name' => 'Read Own Payslips',           'slug' => 'hrm.payslip.read.self',      'module' => 'hrm', 'feature' => 'payslip',     'action' => 'read.self'],
            ['name' => 'Read Own Appraisals',         'slug' => 'hrm.performance.read.self',  'module' => 'hrm', 'feature' => 'performance', 'action' => 'read.self'],
            ['name' => 'Submit Own Self-Assessment',  'slug' => 'hrm.performance.submit.self','module' => 'hrm', 'feature' => 'performance', 'action' => 'submit.self'],
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

        // Create Admin User — pass plaintext; the User model's 'hashed' cast hashes exactly once
        $adminUser = \App\Models\Tenant\User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'System Administrator',
                'password' => 'password',
                'is_active' => true,
            ]
        );

        // Self-heal rows that were double-hashed by a previous run of this seeder
        if (!Hash::check('password', $adminUser->getAuthPassword())) {
            $adminUser->forceFill(['password' => 'password'])->save();
        }

        // Assign Admin Role
        if (!$adminUser->roles->contains($adminRole->id)) {
            $adminUser->roles()->attach($adminRole->id);
        }

        // Create Employee Role
        $employeeRole = Role::updateOrCreate(['slug' => 'employee'], [
            'name' => 'Employee',
            'description' => 'Standard employee role with access to self-service portals.',
        ]);

        // Employee role is strictly self-service. NEVER grant the broad
        // `hrm.*.read` admin permissions here — those expose every employee's
        // payslip/leave/appraisal to anyone with the role. The `.self`
        // permissions pair with policy ownership checks so a regular employee
        // can only read/write rows that belong to them.
        $employeeRole->permissions()->sync(
            Permission::whereIn('slug', [
                'hrm.employee.read.self',
                'hrm.employee.write.self',
                'hrm.leave.read.self',
                'hrm.leave.write.self',
                'hrm.payslip.read.self',
                'hrm.performance.read.self',
                'hrm.performance.submit.self',
            ])->get()
        );

        // Create Dashboard Viewer Role — read-only access to dashboards plus
        // permission to export their data. Stakeholders/auditors get this
        // grant when they need analytics visibility without the authority to
        // create, edit, or delete dashboards/widgets.
        $dashboardViewerRole = Role::updateOrCreate(['slug' => 'dashboard_viewer'], [
            'name' => 'Dashboard Viewer',
            'description' => 'Read-only access to dashboards with export capability. Cannot create, edit, or delete dashboards/widgets.',
        ]);

        $dashboardViewerRole->permissions()->sync(
            Permission::whereIn('slug', [
                'reporting.dashboard.read',
                'reporting.dashboard.export',
            ])->get()
        );

        // Create Employee User — pass plaintext; the User model's 'hashed' cast hashes exactly once
        $employeeUser = \App\Models\Tenant\User::firstOrCreate(
            ['email' => 'role.base@tanent.com'],
            [
                'name' => 'Base Employee User',
                'password' => 'password',
                'is_active' => true,
            ]
        );

        // Self-heal rows that were double-hashed by a previous run of this seeder
        if (!Hash::check('password', $employeeUser->getAuthPassword())) {
            $employeeUser->forceFill(['password' => 'password'])->save();
        }

        // Assign Employee Role
        if (!$employeeUser->roles->contains($employeeRole->id)) {
            $employeeUser->roles()->attach($employeeRole->id);
        }

        // Create Employee Record linked to the User
        if (\Illuminate\Support\Facades\Schema::hasTable('employees')) {
            \App\Models\Tenant\Employee::firstOrCreate(
                ['email' => 'role.base@tanent.com'],
                [
                    'employee_id' => 'TT-0001',
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

        // Seed the minimal Chart of Accounts required for FMS operations
        $this->seedChartOfAccounts();

        // Seed all sidebar menu items as Module rows (idempotent)
        $this->call(ModuleSeeder::class);

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
            
            // Seed Deterministic Password Grant Client
            \Illuminate\Support\Facades\DB::table('oauth_clients')->updateOrInsert(
                ['id' => 33], // Deterministic ID required by AGENTS.md
                [
                    'user_id'                => null,
                    'name'                   => 'Tenant Password Client',
                    'secret'                 => 'b3x5ItVFBU46N3oJljIKrbibQLR0CT0LKlzKddG7',
                    'provider'               => 'users',
                    'redirect'               => 'http://localhost',
                    'personal_access_client' => false,
                    'password_client'        => true,
                    'revoked'                => false,
                    'created_at'             => now(),
                    'updated_at'             => now(),
                ]
            );
        }
    }

    /**
     * Idempotent seed of a minimal Chart of Accounts. Every tenant needs at
     * least the three accounts referenced by InvoiceService::postArJournal():
     *   1200 — Accounts Receivable (asset)
     *   4000 — Sales Revenue       (revenue)
     *   2150 — Sales Tax Payable   (liability)
     *
     * The full standard chart is seeded here so that other FMS features
     * (AP, payroll journals, etc.) also have the accounts they expect without
     * extra configuration.  All rows are upserted on `code` so re-running
     * this seeder is safe.
     */
    private function seedChartOfAccounts(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('accounts')) {
            return;
        }

        $accounts = [
            // ── Assets (1xxx) ──────────────────────────────────────────────
            ['code' => '1000', 'name' => 'Cash & Bank',            'type' => 'asset'],
            ['code' => '1100', 'name' => 'Petty Cash',             'type' => 'asset'],
            ['code' => '1200', 'name' => 'Accounts Receivable',    'type' => 'asset'],   // AR — invoice confirm
            ['code' => '1300', 'name' => 'Prepaid Expenses',       'type' => 'asset'],
            ['code' => '1400', 'name' => 'Inventory',              'type' => 'asset'],

            // ── Liabilities (2xxx) ─────────────────────────────────────────
            ['code' => '2100', 'name' => 'Accounts Payable',       'type' => 'liability'],
            ['code' => '2150', 'name' => 'Sales Tax Payable',      'type' => 'liability'], // tax — invoice confirm
            ['code' => '2200', 'name' => 'Accrued Liabilities',    'type' => 'liability'],
            ['code' => '2300', 'name' => 'Salaries Payable',       'type' => 'liability'],

            // ── Equity (3xxx) ──────────────────────────────────────────────
            ['code' => '3000', 'name' => 'Retained Earnings',      'type' => 'equity'],
            ['code' => '3100', 'name' => 'Owner\'s Equity',        'type' => 'equity'],

            // ── Revenue (4xxx) ─────────────────────────────────────────────
            ['code' => '4000', 'name' => 'Sales Revenue',          'type' => 'revenue'],  // revenue — invoice confirm
            ['code' => '4100', 'name' => 'Service Revenue',        'type' => 'revenue'],
            ['code' => '4200', 'name' => 'Other Income',           'type' => 'revenue'],

            // ── Expenses (5xxx) ────────────────────────────────────────────
            ['code' => '5000', 'name' => 'Cost of Goods Sold',     'type' => 'expense'],
            ['code' => '5100', 'name' => 'Salaries & Wages',       'type' => 'expense'],
            ['code' => '5200', 'name' => 'Rent & Utilities',       'type' => 'expense'],
            ['code' => '5300', 'name' => 'General & Administrative','type' => 'expense'],
            ['code' => '5400', 'name' => 'Depreciation',           'type' => 'expense'],
        ];

        foreach ($accounts as $account) {
            \App\Models\Tenant\Account::updateOrCreate(
                ['code' => $account['code']],
                array_merge($account, ['balance' => 0])
            );
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
