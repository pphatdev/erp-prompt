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

            // FMS — Chart of Accounts
            ['name' => 'Read Accounts',   'slug' => 'fms.accounts.read',   'module' => 'fms', 'feature' => 'accounts', 'action' => 'read'],
            ['name' => 'Write Accounts',  'slug' => 'fms.accounts.write',  'module' => 'fms', 'feature' => 'accounts', 'action' => 'write'],
            ['name' => 'Delete Accounts', 'slug' => 'fms.accounts.delete', 'module' => 'fms', 'feature' => 'accounts', 'action' => 'delete'],

            // FMS — General Ledger (no delete: ledger postings are immutable, reverse via offsetting entry)
            ['name' => 'Read Ledger',  'slug' => 'fms.ledger.read',  'module' => 'fms', 'feature' => 'ledger', 'action' => 'read'],
            ['name' => 'Write Ledger', 'slug' => 'fms.ledger.write', 'module' => 'fms', 'feature' => 'ledger', 'action' => 'write'],

            // FMS — Bank Accounts (specialized asset accounts; foundation for AR/AP cash flows)
            ['name' => 'Read Bank Accounts',   'slug' => 'fms.bank_accounts.read',   'module' => 'fms', 'feature' => 'bank_accounts', 'action' => 'read'],
            ['name' => 'Write Bank Accounts',  'slug' => 'fms.bank_accounts.write',  'module' => 'fms', 'feature' => 'bank_accounts', 'action' => 'write'],
            ['name' => 'Delete Bank Accounts', 'slug' => 'fms.bank_accounts.delete', 'module' => 'fms', 'feature' => 'bank_accounts', 'action' => 'delete'],

            // FMS — Bills (AP). approve / cancel are gated by `write`; once a bill is posted it becomes immutable.
            ['name' => 'Read Bills',   'slug' => 'fms.bills.read',   'module' => 'fms', 'feature' => 'bills', 'action' => 'read'],
            ['name' => 'Write Bills',  'slug' => 'fms.bills.write',  'module' => 'fms', 'feature' => 'bills', 'action' => 'write'],
            ['name' => 'Delete Bills', 'slug' => 'fms.bills.delete', 'module' => 'fms', 'feature' => 'bills', 'action' => 'delete'],

            // FMS — Bill Payments (Pay Bill). No `delete` — payments are immutable once recorded; cancel via reverse JE.
            ['name' => 'Read Bill Payments',  'slug' => 'fms.bill_payments.read',  'module' => 'fms', 'feature' => 'bill_payments', 'action' => 'read'],
            ['name' => 'Write Bill Payments', 'slug' => 'fms.bill_payments.write', 'module' => 'fms', 'feature' => 'bill_payments', 'action' => 'write'],

            // FMS — Reimbursements (employee out-of-pocket). Same immutability rule as bill_payments.
            ['name' => 'Read Reimbursements',  'slug' => 'fms.reimbursements.read',  'module' => 'fms', 'feature' => 'reimbursements', 'action' => 'read'],
            ['name' => 'Write Reimbursements', 'slug' => 'fms.reimbursements.write', 'module' => 'fms', 'feature' => 'reimbursements', 'action' => 'write'],

            // FMS — Cash Advances. `settle` perm gates the Advance Settlement entity which
            // posts actuals against an open advance (DR Expense + DR Cash on returns / CR Receivable).
            ['name' => 'Read Cash Advances',    'slug' => 'fms.cash_advances.read',   'module' => 'fms', 'feature' => 'cash_advances', 'action' => 'read'],
            ['name' => 'Write Cash Advances',   'slug' => 'fms.cash_advances.write',  'module' => 'fms', 'feature' => 'cash_advances', 'action' => 'write'],
            ['name' => 'Settle Cash Advances',  'slug' => 'fms.cash_advances.settle', 'module' => 'fms', 'feature' => 'cash_advances', 'action' => 'settle'],

            // FMS — Expenses (non-AP, pay-as-you-go). Same immutability rule as bill_payments;
            // no `delete` perm — cancellation posts a reversing JE.
            ['name' => 'Read Expenses',  'slug' => 'fms.expenses.read',  'module' => 'fms', 'feature' => 'expenses', 'action' => 'read'],
            ['name' => 'Write Expenses', 'slug' => 'fms.expenses.write', 'module' => 'fms', 'feature' => 'expenses', 'action' => 'write'],

            // FMS — Receipts (AR cycle). AR-side mirror of bill_payments. Same immutability rule;
            // no `delete` perm — cancellation posts a reversing JE.
            ['name' => 'Read Receipts',  'slug' => 'fms.receipts.read',  'module' => 'fms', 'feature' => 'receipts', 'action' => 'read'],
            ['name' => 'Write Receipts', 'slug' => 'fms.receipts.write', 'module' => 'fms', 'feature' => 'receipts', 'action' => 'write'],

            // FMS — Credit Notes (AR adjustment). DR Sales Returns / CR AR.
            // Immutable once issued; no `delete` — cancellation posts a reversing JE.
            ['name' => 'Read Credit Notes',  'slug' => 'fms.credit_notes.read',  'module' => 'fms', 'feature' => 'credit_notes', 'action' => 'read'],
            ['name' => 'Write Credit Notes', 'slug' => 'fms.credit_notes.write', 'module' => 'fms', 'feature' => 'credit_notes', 'action' => 'write'],

            // FMS — Debit Notes (AR adjustment, opposite of Credit). DR AR / CR Revenue.
            // Immutable once issued; no `delete` — cancellation posts a reversing JE.
            ['name' => 'Read Debit Notes',  'slug' => 'fms.debit_notes.read',  'module' => 'fms', 'feature' => 'debit_notes', 'action' => 'read'],
            ['name' => 'Write Debit Notes', 'slug' => 'fms.debit_notes.write', 'module' => 'fms', 'feature' => 'debit_notes', 'action' => 'write'],

            // FMS — Bank Reconciliation. Sessions immutable once closed; `reopen` is gated separately
            // so that closing a session is a real lock by default.
            ['name' => 'Read Bank Reconciliation',   'slug' => 'fms.bank_recon.read',   'module' => 'fms', 'feature' => 'bank_recon', 'action' => 'read'],
            ['name' => 'Write Bank Reconciliation',  'slug' => 'fms.bank_recon.write',  'module' => 'fms', 'feature' => 'bank_recon', 'action' => 'write'],
            ['name' => 'Reopen Bank Reconciliation', 'slug' => 'fms.bank_recon.reopen', 'module' => 'fms', 'feature' => 'bank_recon', 'action' => 'reopen'],

            // FMS - Budgets. Drafts editable, active/archived locked.
            ['name' => 'Read Budgets',   'slug' => 'fms.budgets.read',   'module' => 'fms', 'feature' => 'budgets', 'action' => 'read'],
            ['name' => 'Write Budgets',  'slug' => 'fms.budgets.write',  'module' => 'fms', 'feature' => 'budgets', 'action' => 'write'],
            ['name' => 'Delete Budgets', 'slug' => 'fms.budgets.delete', 'module' => 'fms', 'feature' => 'budgets', 'action' => 'delete'],

            // FMS - Fiscal Periods. Locked periods refuse new JE posts. close and reopen
            // are gated separately from write because the lifecycle transitions are
            // significantly higher impact than editing period metadata.
            ['name' => 'Read Fiscal Periods',   'slug' => 'fms.fiscal_periods.read',   'module' => 'fms', 'feature' => 'fiscal_periods', 'action' => 'read'],
            ['name' => 'Write Fiscal Periods',  'slug' => 'fms.fiscal_periods.write',  'module' => 'fms', 'feature' => 'fiscal_periods', 'action' => 'write'],
            ['name' => 'Close Fiscal Periods',  'slug' => 'fms.fiscal_periods.close',  'module' => 'fms', 'feature' => 'fiscal_periods', 'action' => 'close'],
            ['name' => 'Reopen Fiscal Periods', 'slug' => 'fms.fiscal_periods.reopen', 'module' => 'fms', 'feature' => 'fiscal_periods', 'action' => 'reopen'],

            // Projects (Project Management module).
            ['name' => 'Read Projects',     'slug' => 'projects.project.read',     'module' => 'projects', 'feature' => 'project',   'action' => 'read'],
            ['name' => 'Write Projects',    'slug' => 'projects.project.write',    'module' => 'projects', 'feature' => 'project',   'action' => 'write'],
            ['name' => 'Delete Projects',   'slug' => 'projects.project.delete',   'module' => 'projects', 'feature' => 'project',   'action' => 'delete'],
            ['name' => 'Read Tasks',        'slug' => 'projects.task.read',        'module' => 'projects', 'feature' => 'task',      'action' => 'read'],
            ['name' => 'Write Tasks',       'slug' => 'projects.task.write',       'module' => 'projects', 'feature' => 'task',      'action' => 'write'],
            ['name' => 'Delete Tasks',      'slug' => 'projects.task.delete',      'module' => 'projects', 'feature' => 'task',      'action' => 'delete'],
            ['name' => 'Read Timesheets',   'slug' => 'projects.timesheet.read',   'module' => 'projects', 'feature' => 'timesheet', 'action' => 'read'],
            ['name' => 'Write Timesheets',  'slug' => 'projects.timesheet.write',  'module' => 'projects', 'feature' => 'timesheet', 'action' => 'write'],
            ['name' => 'Delete Timesheets', 'slug' => 'projects.timesheet.delete', 'module' => 'projects', 'feature' => 'timesheet', 'action' => 'delete'],

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

        // Seed workforce taxonomies (departments + positions) before employees so
        // future seed iterations can link admin/base to one of them if desired.
        $this->seedDepartments();
        $this->seedPositions();
        // Shift templates + vacancies don't need employees, but vacancies do need
        // departments/positions — so they slot in here, after seedPositions.
        $this->seedShifts();
        $this->seedVacancies();

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

        // Applications + appraisals depend on the employees rows just created.
        $this->seedApplications();
        $this->seedAppraisalCycles();

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

        // Backfill the fleet.* permission catalogue + .self grants. Safe to
        // re-run because it syncWithoutDetaching's onto the admin/employee
        // roles after upserting each permission row by slug.
        $this->call(FleetPermissionSeeder::class);

        // Backfill the edocs.* permission catalogue. Idempotent — admin role
        // gets all eDocs perms via syncWithoutDetaching.
        $this->call(EDocsPermissionSeeder::class);

        // Backfill the assets.* permission catalogue + custodian .self grants.
        // Idempotent — slugs upsert by `slug` and roles syncWithoutDetaching.
        $this->call(AssetsPermissionSeeder::class);

        // Demo fixed-asset register: 12 assets across categories with depreciation
        // history, one revaluation surplus, one scrap disposal, and an active
        // audit campaign with partial scans. Idempotent on asset_code.
        $this->call(AssetsDemoSeeder::class);

        // Fleet demo data: vehicles + maintenance + fuel history. Keyed on
        // natural columns (registration_number / vehicle+date+type) so a
        // re-run is a no-op against existing rows.
        $this->call(FleetSeeder::class);

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
     * Idempotent seed of starter Departments. Keyed on `code` (unique within
     * the tenant DB). Uses withTrashed()+restore() because Department uses
     * SoftDeletes and the unique index does NOT include deleted_at — a row
     * trashed by an earlier run would otherwise collide on re-insert.
     */
    private function seedDepartments(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('departments')) {
            return;
        }

        $departments = [
            ['code' => 'ENG', 'name' => 'Engineering'],
            ['code' => 'SLS', 'name' => 'Sales'],
            ['code' => 'MKT', 'name' => 'Marketing'],
            ['code' => 'HR',  'name' => 'Human Resources'],
            ['code' => 'FIN', 'name' => 'Finance'],
            ['code' => 'OPS', 'name' => 'Operations'],
        ];

        foreach ($departments as $row) {
            $dept = \App\Models\Tenant\Department::withTrashed()
                ->updateOrCreate(['code' => $row['code']], $row);
            if ($dept->trashed()) {
                $dept->restore();
            }
        }
    }

    /**
     * Idempotent seed of starter Positions. Keyed on `title` (scoped per-tenant
     * via BelongsToTenant); no SoftDeletes so a plain updateOrCreate suffices.
     */
    private function seedPositions(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('positions')) {
            return;
        }

        $positions = [
            ['title' => 'Intern',                 'level' => 'L1'],
            ['title' => 'Junior Engineer',        'level' => 'L2'],
            ['title' => 'Engineer',               'level' => 'L3'],
            ['title' => 'Senior Engineer',        'level' => 'L4'],
            ['title' => 'Team Lead',              'level' => 'L5'],
            ['title' => 'Engineering Manager',    'level' => 'L6'],
            ['title' => 'Account Executive',      'level' => 'L3'],
            ['title' => 'HR Business Partner',    'level' => 'L4'],
        ];

        foreach ($positions as $row) {
            \App\Models\Tenant\Position::updateOrCreate(['title' => $row['title']], $row);
        }
    }

    /**
     * Three canonical shift templates. SoftDeletes on Shift means a row trashed
     * by an earlier wipe still occupies the name; withTrashed+restore keeps
     * re-runs collision-free.
     */
    private function seedShifts(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('shifts')) {
            return;
        }

        $shifts = [
            ['name' => 'Morning', 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'grace_period_minutes' => 15, 'half_day_threshold_minutes' => 240],
            ['name' => 'Evening', 'start_time' => '14:00:00', 'end_time' => '22:00:00', 'grace_period_minutes' => 15, 'half_day_threshold_minutes' => 240],
            // Night shift crosses midnight — AttendanceService handles wrap-around.
            ['name' => 'Night',   'start_time' => '22:00:00', 'end_time' => '06:00:00', 'grace_period_minutes' => 15, 'half_day_threshold_minutes' => 240],
        ];

        foreach ($shifts as $row) {
            $shift = \App\Models\Tenant\Shift::withTrashed()->firstOrNew(['name' => $row['name']]);
            if (!$shift->exists) {
                $shift->fill($row)->save();
            } elseif ($shift->trashed()) {
                $shift->restore();
            }
        }
    }

    /**
     * Five starter open vacancies wired to the seeded departments + positions.
     * Uses firstOrNew (rather than updateOrCreate) so manually-edited vacancies
     * aren't trampled on every seeder re-run.
     */
    private function seedVacancies(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('job_vacancies')) {
            return;
        }

        // Pre-resolve lookups; if departments/positions haven't been seeded the
        // helper degrades gracefully — the rows still create, just without FKs.
        $dept = fn (string $code) => \App\Models\Tenant\Department::where('code', $code)->value('id');
        $pos  = fn (string $title) => \App\Models\Tenant\Position::where('title', $title)->value('id');

        $vacancies = [
            ['title' => 'Senior Backend Engineer', 'department_id' => $dept('ENG'), 'position_id' => $pos('Senior Engineer'),     'employment_type' => 'full_time', 'experience_min_years' => 4, 'experience_max_years' => 8,  'salary_min' => 4500, 'salary_max' => 7500, 'vacancies_count' => 2],
            ['title' => 'Sales Account Executive', 'department_id' => $dept('SLS'), 'position_id' => $pos('Account Executive'),   'employment_type' => 'full_time', 'experience_min_years' => 2, 'experience_max_years' => 5,  'salary_min' => 2500, 'salary_max' => 4500, 'vacancies_count' => 3],
            ['title' => 'Marketing Manager',       'department_id' => $dept('MKT'), 'position_id' => $pos('Engineering Manager'), 'employment_type' => 'full_time', 'experience_min_years' => 5, 'experience_max_years' => 10, 'salary_min' => 5000, 'salary_max' => 8500, 'vacancies_count' => 1],
            ['title' => 'HR Business Partner',     'department_id' => $dept('HR'),  'position_id' => $pos('HR Business Partner'), 'employment_type' => 'full_time', 'experience_min_years' => 3, 'experience_max_years' => 7,  'salary_min' => 3500, 'salary_max' => 6000, 'vacancies_count' => 1],
            ['title' => 'Operations Intern',       'department_id' => $dept('OPS'), 'position_id' => $pos('Intern'),              'employment_type' => 'intern',    'experience_min_years' => 0, 'experience_max_years' => 1,  'salary_min' => 600,  'salary_max' => 900,  'vacancies_count' => 4],
        ];

        foreach ($vacancies as $row) {
            $row['status']    = 'open';
            $row['posted_at'] = now()->subDays(14)->toDateString();
            $row['closes_at'] = now()->addDays(45)->toDateString();

            $vacancy = \App\Models\Tenant\JobVacancy::withTrashed()
                ->firstOrNew(['title' => $row['title']]);
            if (!$vacancy->exists) {
                $vacancy->fill($row)->save();
            } elseif ($vacancy->trashed()) {
                $vacancy->restore();
            }
        }
    }

    /**
     * Ten demo applications spread across the canonical recruitment pipeline.
     * Keyed on (job_vacancy_id, applicant_email) so re-running is a no-op. The
     * Application model auto-generates candidate_code on creating().
     */
    private function seedApplications(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('applications')) {
            return;
        }

        // Anchor every application to whichever vacancy comes back first if
        // the title-lookup misses — keeps the seeder resilient if vacancies
        // were renamed by hand.
        $vacancyByTitle = function (string $title): ?string {
            return \App\Models\Tenant\JobVacancy::withTrashed()
                ->where('title', $title)
                ->value('id');
        };
        $fallback = \App\Models\Tenant\JobVacancy::withTrashed()->value('id');
        if (!$fallback) {
            return; // No vacancies → nothing to attach to.
        }

        // Pin candidate_code explicitly so seed rows are deterministic and never
        // collide with auto-generated CAN-YYYYMM-NNN codes from real applications
        // (the auto-generator scans by month — using a non-numeric SEED token
        // keeps the seed rows in their own namespace).
        $applications = [
            ['code' => 'CAN-SEED-001', 'vacancy' => 'Senior Backend Engineer', 'applicant_name' => 'Avery Singh',   'applicant_email' => 'avery.singh@example.com',   'status' => 'hired',                'days_ago' => 60],
            ['code' => 'CAN-SEED-002', 'vacancy' => 'Senior Backend Engineer', 'applicant_name' => 'Linh Tran',     'applicant_email' => 'linh.tran@example.com',     'status' => 'offer',                'days_ago' => 40],
            ['code' => 'CAN-SEED-003', 'vacancy' => 'Senior Backend Engineer', 'applicant_name' => 'Pavel Ivanov',  'applicant_email' => 'pavel.ivanov@example.com',  'status' => 'interview',            'days_ago' => 22],
            ['code' => 'CAN-SEED-004', 'vacancy' => 'Sales Account Executive', 'applicant_name' => 'Maria Garcia',  'applicant_email' => 'maria.garcia@example.com',  'status' => 'shortlisted',          'days_ago' => 18],
            ['code' => 'CAN-SEED-005', 'vacancy' => 'Sales Account Executive', 'applicant_name' => 'Tom Becker',    'applicant_email' => 'tom.becker@example.com',    'status' => 'screening',            'days_ago' => 12],
            ['code' => 'CAN-SEED-006', 'vacancy' => 'Marketing Manager',       'applicant_name' => 'Chloe Park',    'applicant_email' => 'chloe.park@example.com',    'status' => 'assessment',           'days_ago' => 9],
            ['code' => 'CAN-SEED-007', 'vacancy' => 'Marketing Manager',       'applicant_name' => 'Daniel Wright', 'applicant_email' => 'daniel.wright@example.com', 'status' => 'assessment_completed', 'days_ago' => 7],
            ['code' => 'CAN-SEED-008', 'vacancy' => 'HR Business Partner',     'applicant_name' => 'Sofia Rossi',   'applicant_email' => 'sofia.rossi@example.com',   'status' => 'applied',              'days_ago' => 5],
            ['code' => 'CAN-SEED-009', 'vacancy' => 'Operations Intern',       'applicant_name' => 'Jake Holloway', 'applicant_email' => 'jake.holloway@example.com', 'status' => 'applied',              'days_ago' => 3],
            ['code' => 'CAN-SEED-010', 'vacancy' => 'Operations Intern',       'applicant_name' => 'Mira Patel',    'applicant_email' => 'mira.patel@example.com',    'status' => 'withdrawn',            'days_ago' => 28],
        ];

        foreach ($applications as $row) {
            $vacancyId = $vacancyByTitle($row['vacancy']) ?? $fallback;
            // Match by candidate_code first (stable) — falls back to (vacancy, email)
            // for legacy seeds created before code-pinning landed.
            $existing = \App\Models\Tenant\Application::withTrashed()
                ->where(function ($q) use ($row, $vacancyId) {
                    $q->where('candidate_code', $row['code'])
                      ->orWhere(function ($q2) use ($row, $vacancyId) {
                          $q2->where('job_vacancy_id', $vacancyId)
                             ->where('applicant_email', $row['applicant_email']);
                      });
                })
                ->first();

            if ($existing) {
                if ($existing->trashed()) {
                    $existing->restore();
                }
                // Heal stale auto-generated codes from earlier seed runs so the
                // seed namespace converges on CAN-SEED-NNN.
                if ($existing->candidate_code !== $row['code']) {
                    $existing->forceFill(['candidate_code' => $row['code']])->save();
                }
                continue;
            }

            \App\Models\Tenant\Application::create([
                'job_vacancy_id'  => $vacancyId,
                'candidate_code'  => $row['code'],
                'applicant_name'  => $row['applicant_name'],
                'applicant_email' => $row['applicant_email'],
                'status'          => $row['status'],
                'applied_at'      => now()->subDays($row['days_ago']),
            ]);
        }
    }

    /**
     * Two draft appraisal cycles (current-year Q1, Q2) for every seeded
     * employee. Keyed on (employee_id, cycle) per the migration's compound
     * index. Lets the Performance Appraisals page have non-empty state.
     */
    private function seedAppraisalCycles(): void
    {
        if (!\Illuminate\Support\Facades\Schema::hasTable('appraisals')) {
            return;
        }

        $year = (int) now()->year;
        $cycles = [
            ['cycle' => "{$year}-Q1", 'period_start' => "{$year}-01-01", 'period_end' => "{$year}-03-31"],
            ['cycle' => "{$year}-Q2", 'period_start' => "{$year}-04-01", 'period_end' => "{$year}-06-30"],
        ];

        $employees = \App\Models\Tenant\Employee::withTrashed()->pluck('id');
        if ($employees->isEmpty()) {
            return;
        }

        foreach ($employees as $employeeId) {
            foreach ($cycles as $row) {
                $appraisal = \App\Models\Tenant\Appraisal::withTrashed()
                    ->where('employee_id', $employeeId)
                    ->where('cycle', $row['cycle'])
                    ->first();

                if ($appraisal) {
                    if ($appraisal->trashed()) {
                        $appraisal->restore();
                    }
                    continue;
                }

                \App\Models\Tenant\Appraisal::create([
                    'employee_id'  => $employeeId,
                    'cycle'        => $row['cycle'],
                    'period_start' => $row['period_start'],
                    'period_end'   => $row['period_end'],
                    'status'       => 'draft',
                ]);
            }
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
            ['code' => '1500', 'name' => 'Accumulated Depreciation','type' => 'asset'],  // contra-asset — fixed asset depreciation
            ['code' => '1700', 'name' => 'Fixed Assets',           'type' => 'asset'],   // fixed asset cost account

            // ── Liabilities (2xxx) ─────────────────────────────────────────
            ['code' => '2100', 'name' => 'Accounts Payable',       'type' => 'liability'],
            ['code' => '2150', 'name' => 'Sales Tax Payable',      'type' => 'liability'], // tax — invoice confirm
            ['code' => '2200', 'name' => 'Accrued Liabilities',    'type' => 'liability'],
            ['code' => '2300', 'name' => 'Salaries Payable',       'type' => 'liability'],

            // ── Equity (3xxx) ──────────────────────────────────────────────
            ['code' => '3000', 'name' => 'Retained Earnings',      'type' => 'equity'],
            ['code' => '3100', 'name' => 'Owner\'s Equity',        'type' => 'equity'],
            ['code' => '3200', 'name' => 'Revaluation Reserve',    'type' => 'equity'],   // fixed asset surplus

            // ── Revenue (4xxx) ─────────────────────────────────────────────
            ['code' => '4000', 'name' => 'Sales Revenue',          'type' => 'revenue'],  // revenue — invoice confirm
            ['code' => '4100', 'name' => 'Service Revenue',        'type' => 'revenue'],
            ['code' => '4200', 'name' => 'Other Income',           'type' => 'revenue'],
            ['code' => '4300', 'name' => 'Gain/Loss on Disposal',  'type' => 'revenue'],  // fixed asset disposal P&L

            // ── Expenses (5xxx) ────────────────────────────────────────────
            ['code' => '5000', 'name' => 'Cost of Goods Sold',     'type' => 'expense'],
            ['code' => '5100', 'name' => 'Salaries & Wages',       'type' => 'expense'],
            ['code' => '5200', 'name' => 'Rent & Utilities',       'type' => 'expense'],
            ['code' => '5300', 'name' => 'General & Administrative','type' => 'expense'],
            ['code' => '5400', 'name' => 'Depreciation',           'type' => 'expense'],
            ['code' => '5500', 'name' => 'Revaluation Loss',       'type' => 'expense'],  // fixed asset revaluation loss
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
                ['key' => 'screening',            'label' => 'Screening',            'color' => 'info',      'icon' => 'ti-eye-search',     'sequence' => 2,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['shortlisted', 'assessment', 'interview', 'rejected', 'withdrawn']],
                ['key' => 'shortlisted',          'label' => 'Shortlisted',          'color' => 'primary',   'icon' => 'ti-list-check',     'sequence' => 3,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['assessment', 'interview', 'rejected', 'withdrawn']],
                ['key' => 'assessment',           'label' => 'Assessment',           'color' => 'info',      'icon' => 'ti-clipboard-list', 'sequence' => 4,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['assessment_completed', 'rejected', 'withdrawn']],
                ['key' => 'assessment_completed', 'label' => 'Assessment Completed', 'color' => 'info',      'icon' => 'ti-clipboard-check','sequence' => 5,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['interview', 'rejected', 'withdrawn']],
                ['key' => 'interview',            'label' => 'Interview',            'color' => 'warning',   'icon' => 'ti-message-circle', 'sequence' => 6,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['offer', 'rejected', 'withdrawn']],
                ['key' => 'offer',                'label' => 'Offer',                'color' => 'primary',   'icon' => 'ti-mail-share',     'sequence' => 7,  'is_initial' => false, 'is_terminal' => false, 'allowed_next' => ['hired', 'rejected', 'withdrawn']],
                ['key' => 'hired',                'label' => 'Hired',                'color' => 'success',   'icon' => 'ti-circle-check',   'sequence' => 8,  'is_initial' => false, 'is_terminal' => true,  'allowed_next' => []],
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
