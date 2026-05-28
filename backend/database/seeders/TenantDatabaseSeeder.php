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

            // Sales
            ['name' => 'Read Orders', 'slug' => 'sales.orders.read', 'module' => 'sales', 'feature' => 'orders', 'action' => 'read'],
            ['name' => 'Write Orders', 'slug' => 'sales.orders.write', 'module' => 'sales', 'feature' => 'orders', 'action' => 'write'],

            // CRM — Leads, Opportunities, Contacts, Activities
            ['name' => 'Read Leads',          'slug' => 'crm.leads.read',          'module' => 'crm', 'feature' => 'leads',         'action' => 'read'],
            ['name' => 'Write Leads',         'slug' => 'crm.leads.write',         'module' => 'crm', 'feature' => 'leads',         'action' => 'write'],
            ['name' => 'Delete Leads',        'slug' => 'crm.leads.delete',        'module' => 'crm', 'feature' => 'leads',         'action' => 'delete'],
            ['name' => 'Read Opportunities',  'slug' => 'crm.opportunities.read',  'module' => 'crm', 'feature' => 'opportunities', 'action' => 'read'],
            ['name' => 'Write Opportunities', 'slug' => 'crm.opportunities.write', 'module' => 'crm', 'feature' => 'opportunities', 'action' => 'write'],
            ['name' => 'Delete Opportunities','slug' => 'crm.opportunities.delete','module' => 'crm', 'feature' => 'opportunities', 'action' => 'delete'],
            ['name' => 'Read CRM Contacts',   'slug' => 'crm.contacts.read',       'module' => 'crm', 'feature' => 'contacts',      'action' => 'read'],
            ['name' => 'Write CRM Contacts',  'slug' => 'crm.contacts.write',      'module' => 'crm', 'feature' => 'contacts',      'action' => 'write'],
            ['name' => 'Delete CRM Contacts', 'slug' => 'crm.contacts.delete',     'module' => 'crm', 'feature' => 'contacts',      'action' => 'delete'],
            ['name' => 'Read CRM Activities', 'slug' => 'crm.activities.read',     'module' => 'crm', 'feature' => 'activities',    'action' => 'read'],
            ['name' => 'Write CRM Activities','slug' => 'crm.activities.write',    'module' => 'crm', 'feature' => 'activities',    'action' => 'write'],
            ['name' => 'Delete CRM Activities','slug'=> 'crm.activities.delete',   'module' => 'crm', 'feature' => 'activities',    'action' => 'delete'],
            ['name' => 'Read CRM Appointments', 'slug' => 'crm.appointments.read',  'module' => 'crm', 'feature' => 'appointments', 'action' => 'read'],
            ['name' => 'Write CRM Appointments','slug' => 'crm.appointments.write', 'module' => 'crm', 'feature' => 'appointments', 'action' => 'write'],
            ['name' => 'Delete CRM Appointments','slug'=> 'crm.appointments.delete','module' => 'crm', 'feature' => 'appointments', 'action' => 'delete'],

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

            // Inventory & SCM
            ['name' => 'Read Warehouses',    'slug' => 'inventory.warehouse.read',   'module' => 'inventory', 'feature' => 'warehouse',   'action' => 'read'],
            ['name' => 'Write Warehouses',   'slug' => 'inventory.warehouse.write',  'module' => 'inventory', 'feature' => 'warehouse',   'action' => 'write'],
            ['name' => 'Delete Warehouses',  'slug' => 'inventory.warehouse.delete', 'module' => 'inventory', 'feature' => 'warehouse',   'action' => 'delete'],
            ['name' => 'Read Catalog',       'slug' => 'inventory.product.read',     'module' => 'inventory', 'feature' => 'product',     'action' => 'read'],
            ['name' => 'Write Catalog',      'slug' => 'inventory.product.write',    'module' => 'inventory', 'feature' => 'product',     'action' => 'write'],
            ['name' => 'Delete Catalog',     'slug' => 'inventory.product.delete',   'module' => 'inventory', 'feature' => 'product',     'action' => 'delete'],
            ['name' => 'Export Catalog',     'slug' => 'inventory.product.export',   'module' => 'inventory', 'feature' => 'product',     'action' => 'export'],
            ['name' => 'Read Stock Ledger',  'slug' => 'inventory.stock.read',       'module' => 'inventory', 'feature' => 'stock',       'action' => 'read'],
            ['name' => 'Write Stock Ledger', 'slug' => 'inventory.stock.write',      'module' => 'inventory', 'feature' => 'stock',       'action' => 'write'],
            ['name' => 'Adjust Stock Ledger','slug' => 'inventory.stock.adjust',     'module' => 'inventory', 'feature' => 'stock',       'action' => 'adjust'],
            ['name' => 'Read Suppliers',     'slug' => 'inventory.suppliers.read',   'module' => 'inventory', 'feature' => 'suppliers',   'action' => 'read'],
            ['name' => 'Write Suppliers',    'slug' => 'inventory.suppliers.write',  'module' => 'inventory', 'feature' => 'suppliers',   'action' => 'write'],
            ['name' => 'Delete Suppliers',   'slug' => 'inventory.suppliers.delete', 'module' => 'inventory', 'feature' => 'suppliers',   'action' => 'delete'],
            ['name' => 'Read Procurement',   'slug' => 'inventory.procurement.read', 'module' => 'inventory', 'feature' => 'procurement', 'action' => 'read'],
            ['name' => 'Write Procurement',  'slug' => 'inventory.procurement.write','module' => 'inventory', 'feature' => 'procurement', 'action' => 'write'],
            ['name' => 'Delete Procurement', 'slug' => 'inventory.procurement.delete','module' => 'inventory', 'feature' => 'procurement', 'action' => 'delete'],
            ['name' => 'Approve Procurement','slug' => 'inventory.procurement.approve','module' => 'inventory', 'feature' => 'procurement', 'action' => 'approve'],
            ['name' => 'Read Reservations',  'slug' => 'inventory.reservations.read',  'module' => 'inventory', 'feature' => 'reservations','action' => 'read'],
            ['name' => 'Write Reservations', 'slug' => 'inventory.reservations.write', 'module' => 'inventory', 'feature' => 'reservations','action' => 'write'],
            ['name' => 'Commit Reservations','slug' => 'inventory.reservations.commit','module' => 'inventory', 'feature' => 'reservations','action' => 'commit'],
            ['name' => 'Read eCom Sync',     'slug' => 'inventory.ecommerce.read',   'module' => 'inventory', 'feature' => 'ecommerce',   'action' => 'read'],
            ['name' => 'Write eCom Sync',    'slug' => 'inventory.ecommerce.write',  'module' => 'inventory', 'feature' => 'ecommerce',   'action' => 'write'],
            ['name' => 'Reserve eCom Stock', 'slug' => 'inventory.ecommerce.reserve','module' => 'inventory', 'feature' => 'ecommerce',   'action' => 'reserve'],
            ['name' => 'View Low-stock Alerts',   'slug' => 'inventory.alerts.view',   'module' => 'inventory', 'feature' => 'alerts', 'action' => 'view'],
            ['name' => 'Manage Low-stock Alerts', 'slug' => 'inventory.alerts.manage', 'module' => 'inventory', 'feature' => 'alerts', 'action' => 'manage'],
            ['name' => 'Read Stock Transfers',  'slug' => 'inventory.transfer.read',  'module' => 'inventory', 'feature' => 'transfer', 'action' => 'read'],
            ['name' => 'Write Stock Transfers', 'slug' => 'inventory.transfer.write', 'module' => 'inventory', 'feature' => 'transfer', 'action' => 'write'],
            ['name' => 'Read Categories',   'slug' => 'inventory.category.read',   'module' => 'inventory', 'feature' => 'category', 'action' => 'read'],
            ['name' => 'Write Categories',  'slug' => 'inventory.category.write',  'module' => 'inventory', 'feature' => 'category', 'action' => 'write'],
            ['name' => 'Delete Categories', 'slug' => 'inventory.category.delete', 'module' => 'inventory', 'feature' => 'category', 'action' => 'delete'],

            ['name' => 'Read Exchange Rates',   'slug' => 'fms.exchange_rate.read',   'module' => 'fms', 'feature' => 'exchange_rate', 'action' => 'read'],
            ['name' => 'Write Exchange Rates',  'slug' => 'fms.exchange_rate.write',  'module' => 'fms', 'feature' => 'exchange_rate', 'action' => 'write'],
            ['name' => 'Delete Exchange Rates', 'slug' => 'fms.exchange_rate.delete', 'module' => 'fms', 'feature' => 'exchange_rate', 'action' => 'delete'],

            // eApprovals
            ['name' => 'Read Approval Requests',   'slug' => 'approvals.requests.read',   'module' => 'approvals', 'feature' => 'requests', 'action' => 'read'],
            ['name' => 'Write Approval Requests',  'slug' => 'approvals.requests.write',  'module' => 'approvals', 'feature' => 'requests', 'action' => 'write'],
            ['name' => 'Export Approval Requests', 'slug' => 'approvals.requests.export', 'module' => 'approvals', 'feature' => 'requests', 'action' => 'export'],
            ['name' => 'Read Approval Actions',    'slug' => 'approvals.actions.read',    'module' => 'approvals', 'feature' => 'actions',  'action' => 'read'],
            ['name' => 'Execute Approval Actions', 'slug' => 'approvals.actions.execute', 'module' => 'approvals', 'feature' => 'actions',  'action' => 'execute'],
            ['name' => 'Read Approval Workflows',  'slug' => 'approvals.workflows.read',  'module' => 'approvals', 'feature' => 'workflows','action' => 'read'],
            ['name' => 'Write Approval Workflows', 'slug' => 'approvals.workflows.write', 'module' => 'approvals', 'feature' => 'workflows','action' => 'write'],
            ['name' => 'Delete Approval Workflows','slug' => 'approvals.workflows.delete','module' => 'approvals', 'feature' => 'workflows','action' => 'delete'],
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

        // Tenant key is `handle`, not `id` — `tenant('id')` would return null.
        $tenantKey = tenant()->getTenantKey();

        $adminUser = \App\Models\Tenant\User::where('email', 'admin@example.com')
            ->first();

        if (!$adminUser) {
            $adminUser = \App\Models\Tenant\User::create([
                'email'     => 'admin@example.com',
                'name'      => 'System Administrator',
                'password'  => 'password',
                'is_active' => true,
            ]);
        }

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

        // Same lookup pattern as the admin user — bypass the tenant scope for
        // the email check so a stale-tenant_id row doesn't trip the unique index.
        $employeeUser = \App\Models\Tenant\User::where('email', 'role.base@tanent.com')
            ->first();

        if (!$employeeUser) {
            $employeeUser = \App\Models\Tenant\User::create([
                'email'     => 'role.base@tanent.com',
                'name'      => 'Base Employee User',
                'password'  => 'password',
                'is_active' => true,
            ]);
        }

        // Self-heal rows that were double-hashed by a previous run of this seeder
        if (!Hash::check('password', $employeeUser->getAuthPassword())) {
            $employeeUser->forceFill(['password' => 'password'])->save();
        }

        // Assign Employee Role
        if (!$employeeUser->roles->contains($employeeRole->id)) {
            $employeeUser->roles()->attach($employeeRole->id);
        }

        // Seed default tenant configuration settings (including numbering prefixes)
        // Must run BEFORE creating the base employee so generateNextEmployeeId() reads the right prefix.
        app(\App\Tenants\Modules\Settings\Services\SettingService::class)->ensureDefaults();

        if (\Illuminate\Support\Facades\Schema::hasTable('employees')) {
            // Seed Admin Employee
            $adminEmployee = \App\Models\Tenant\Employee::where('email', 'admin@example.com')
                ->first();

            if (!$adminEmployee) {
                $employeeId = app(\App\Tenants\Modules\HRM\Services\RecruitmentService::class)
                    ->generateNextEmployeeId();

                \App\Models\Tenant\Employee::create([
                    'email'       => 'admin@example.com',
                    'employee_id' => $employeeId,
                    'first_name'  => 'System',
                    'last_name'   => 'Administrator',
                    'user_id'     => $adminUser->id,
                    'status'      => 'active',
                    'hired_at'    => now()->subYears(2)->toDateString(), // Hired 2 years ago for plenty of leave balance
                ]);
            }

            // Seed Base Employee
            $employee = \App\Models\Tenant\Employee::where('email', 'role.base@tanent.com')
                ->first();

            if (!$employee) {
                // Use the configured prefix from settings rather than a hardcoded value.
                $employeeId = app(\App\Tenants\Modules\HRM\Services\RecruitmentService::class)
                    ->generateNextEmployeeId();

                \App\Models\Tenant\Employee::create([
                    'email'       => 'role.base@tanent.com',
                    'employee_id' => $employeeId,
                    'first_name'  => 'Base',
                    'last_name'   => 'Employee',
                    'user_id'     => $employeeUser->id,
                    'status'      => 'active',
                    'hired_at'    => now()->subYears(1)->toDateString(), // Hired 1 year ago for plenty of leave balance
                ]);
            }
        }

        // Seed default Leave Types
        $defaultLeaveTypes = [
            ['name' => 'Annual Leave', 'annual_allowance' => 12],
            ['name' => 'Sick Leave',   'annual_allowance' => 14],
            ['name' => 'Unpaid Leave', 'annual_allowance' => 30],
        ];

        foreach ($defaultLeaveTypes as $type) {
            \App\Models\Tenant\LeaveType::updateOrCreate(
                ['name' => $type['name']],
                $type
            );
        }

        // Seed default workflow statuses for every HRM module
        $this->seedWorkflowStatuses();

        // Seed default approval workflows and levels
        $this->seedApprovalWorkflows();

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

    /**
     * Idempotent seed of default approval workflows and levels.
     */
    private function seedApprovalWorkflows(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('approval_workflows')) {
            return;
        }

        // 1. Leave Approval Workflow
        $leaveWorkflow = \App\Models\Tenant\ApprovalWorkflow::updateOrCreate([
            'module' => 'hrm',
            'type' => 'leave',
        ], [
            'name' => 'Leave Approval Workflow',
            'module' => 'hrm',
            'type' => 'leave',
        ]);

        $leaveWorkflow->levels()->updateOrCreate([
            'sequence' => 1,
        ], [
            'sequence' => 1,
            'approver_role' => 'admin',
        ]);

        // 2. Purchase Order Approval Workflow
        $poWorkflow = \App\Models\Tenant\ApprovalWorkflow::updateOrCreate([
            'module' => 'inventory',
            'type' => 'purchase_order',
        ], [
            'name' => 'PO Approval Workflow',
            'module' => 'inventory',
            'type' => 'purchase_order',
        ]);

        $poWorkflow->levels()->updateOrCreate([
            'sequence' => 1,
        ], [
            'sequence' => 1,
            'approver_role' => 'admin',
        ]);

        // Back-heal existing pending leaves that have no ApprovalRequest
        if (\Illuminate\Support\Facades\Schema::hasTable('leaves') && \Illuminate\Support\Facades\Schema::hasTable('approval_requests')) {
            $pendingLeaves = \App\Models\Tenant\Leave::where('status', 'pending')->get();
            $adminUser = \App\Models\Tenant\User::where('email', 'admin@example.com')->first();
            
            foreach ($pendingLeaves as $leave) {
                $hasRequest = \App\Models\Tenant\ApprovalRequest::where('requestable_type', \App\Models\Tenant\Leave::class)
                    ->where('requestable_id', $leave->id)
                    ->exists();
                    
                if (!$hasRequest) {
                    // Find the user associated with this leave's employee
                    $requesterId = $leave->employee?->user_id ?? $adminUser?->id;
                    if ($requesterId) {
                        try {
                            app(\App\Tenants\Modules\Approvals\Services\ApprovalService::class)->submitRequest(
                                workflowId: $leaveWorkflow->id,
                                requesterId: (string) $requesterId,
                                requestableType: \App\Models\Tenant\Leave::class,
                                requestableId: (string) $leave->id,
                            );
                        } catch (\Exception $e) {
                            // Suppress exceptions during seeding
                        }
                    }
                }
            }
        }
    }
}
