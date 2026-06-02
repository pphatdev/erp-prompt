<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\Module;
use Illuminate\Database\Seeder;

/**
 * Seeds every sidebar menu item as a Module row.
 *
 * Auto-runs on every tenant `db:seed` because TenantDatabaseSeeder::run()
 * calls $this->call(ModuleSeeder::class). Idempotent (updateOrCreate keyed
 * on slug), so safe to re-run for backfilling new entries on existing
 * tenant databases:
 *
 *   php artisan tenants:run db:seed \
 *     --option="class=Database\Seeders\ModuleSeeder" \
 *     --option="force=true"
 *
 * Or to backfill everything (modules + perms + CoA + workflow statuses):
 *
 *   php artisan tenants:run db:seed --option="force=true"
 *
 * prefix conventions (uppercase, ≤ 8 chars):
 *   DASH / TASK         → main group
 *   MY*                 → self-service group
 *   ECO* / SLS* / CRM* / FMS* / INV* / HRM* → app modules with children
 *   FLT / PROJ / EAPP / EDOC / RPT → standalone app modules
 *   SET*                → settings (core, always active)
 *
 * Multi-level nesting is supported (e.g. crm › sales-pipeline › opportunities).
 * The run() method does iterative topological resolution — each pass inserts
 * every definition whose parent is already in the slug→id map. Throws on a
 * definition whose parent_slug is missing from the catalogue.
 */
class ModuleSeeder extends Seeder
{
    private array $definitions = [
        // Main
        ['slug' => 'dashboard',    'prefix' => 'DASH',   'name' => 'Dashboard',          'icon' => 'ti-layout-dashboard', 'route' => '/dashboard', 'group' => 'main', 'sort_order' => 1, 'is_core' => true],

        // Self-service
        ['slug' => 'my-profile',    'prefix' => 'MYPR', 'name' => 'My Profile',    'icon' => 'ti-user-circle',    'route' => '#',                       'group' => 'self-service', 'sort_order' => 1, 'is_core' => true],
        ['slug' => 'my-leaves',     'prefix' => 'MYLS', 'name' => 'My Leaves',     'icon' => 'ti-calendar-event', 'route' => '/hrm/timeoff/leaves',     'group' => 'self-service', 'sort_order' => 2],
        ['slug' => 'my-payslips',   'prefix' => 'MYPS', 'name' => 'My Payslips',   'icon' => 'ti-cash',           'route' => '#',                       'group' => 'self-service', 'sort_order' => 3],
        ['slug' => 'my-appraisals', 'prefix' => 'MYAP', 'name' => 'My Appraisals', 'icon' => 'ti-clipboard-list', 'route' => '/hrm/appraisals',         'group' => 'self-service', 'sort_order' => 4],

        // Apps: Ecommerce
        ['slug' => 'ecommerce',      'prefix' => 'ECO',  'name' => 'Ecommerce',  'icon' => 'ti-shopping-cart',  'route' => null, 'group' => 'apps', 'sort_order' => 1],
        ['slug' => 'ecom-orders',    'prefix' => 'ECOO', 'name' => 'Orders',     'icon' => 'ti-receipt',        'route' => '#',         'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'ecommerce'],
        ['slug' => 'ecom-refunds',   'prefix' => 'ECOR', 'name' => 'Refunds',    'icon' => 'ti-receipt-refund', 'route' => '#',         'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'ecommerce'],

        // Apps: CRM
        // Sales Pipeline is a single Kanban page with all stages as columns —
        // no per-stage sub-nav. Stage filtering happens on the page itself.
        ['slug' => 'crm',           'prefix' => 'CRM',  'name' => 'CRM',                  'icon' => 'ti-users',          'route' => null,                 'group' => 'apps', 'sort_order' => 2],
        ['slug' => 'crm-leads',     'prefix' => 'CRML', 'name' => 'Leads',                'icon' => 'ti-address-book',   'route' => '/crm/leads',         'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'crm'],
        ['slug' => 'crm-pipeline',  'prefix' => 'CRMP', 'name' => 'Sales Pipeline',       'icon' => 'ti-layout-kanban',  'route' => '/crm/opportunities', 'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'crm'],
        ['slug' => 'crm-schedules', 'prefix' => 'CRMS', 'name' => 'Schedules',            'icon' => 'ti-calendar-event', 'route' => '/crm/schedules',     'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'crm'],
        ['slug' => 'crm-timeline',  'prefix' => 'CRMT', 'name' => 'Interaction Timeline', 'icon' => 'ti-notes',          'route' => '/crm/activities',    'group' => 'apps', 'sort_order' => 4, 'parent_slug' => 'crm'],
        ['slug' => 'crm-contacts',  'prefix' => 'CRMC', 'name' => 'B2B Contacts',         'icon' => 'ti-users-group',    'route' => '/crm/contacts',      'group' => 'apps', 'sort_order' => 5, 'parent_slug' => 'crm'],

        // Apps: Sales
        ['slug' => 'sales',               'prefix' => 'SLS',  'name' => 'Sales',         'icon' => 'ti-address-book', 'route' => null,                'group' => 'apps', 'sort_order' => 3],
        ['slug' => 'sales-customers',     'prefix' => 'SLSC', 'name' => 'Customers',     'icon' => 'ti-users',        'route' => '/sales/customers',  'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'sales'],
        ['slug' => 'sales-quotations',    'prefix' => 'SLSQ', 'name' => 'Quotations',    'icon' => 'ti-file-text',    'route' => '/sales/quotations', 'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'sales'],
        ['slug' => 'sales-orders',        'prefix' => 'SLSO', 'name' => 'Sales Orders',  'icon' => 'ti-shopping-cart','route' => '/sales/orders',     'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'sales'],

        // Apps: Finance
        // Invoices/Subscriptions are mirrored from Sales — backend routes unchanged.
        ['slug' => 'fms',               'prefix' => 'FMS',  'name' => 'Finance',       'icon' => 'ti-coin',         'route' => null,                'group' => 'apps', 'sort_order' => 4],
        ['slug' => 'fms-invoices',      'prefix' => 'FMSI', 'name' => 'Invoices',      'icon' => 'ti-receipt',      'route' => '/sales/invoices',     'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'fms'],
        ['slug' => 'fms-subscriptions', 'prefix' => 'FMSS', 'name' => 'Subscriptions', 'icon' => 'ti-cloud',        'route' => '/sales/subscriptions','group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'fms'],
        ['slug' => 'fms-payments',      'prefix' => 'FMSP', 'name' => 'Payments',      'icon' => 'ti-cash',            'route' => '/finance/payments',         'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'fms'],
        ['slug' => 'fms-estimates',     'prefix' => 'FMSE', 'name' => 'Estimates',     'icon' => 'ti-file-invoice',    'route' => '/finance/estimates',        'group' => 'apps', 'sort_order' => 4, 'parent_slug' => 'fms'],
        ['slug' => 'fms-exchange-rates','prefix' => 'FMSX', 'name' => 'Exchange Rates','icon' => 'ti-currency-dollar', 'route' => '/finance/exchange-rates',   'group' => 'apps', 'sort_order' => 5, 'parent_slug' => 'fms'],

        // Apps: Accounting (general ledger surface — split from Finance for ICP/audit clarity)
        ['slug' => 'accounting',          'prefix' => 'ACC',  'name' => 'Accounting',        'icon' => 'ti-book-2',       'route' => null,                  'group' => 'apps', 'sort_order' => 4, 'parent_slug' => null],
        ['slug' => 'accounting-accounts', 'prefix' => 'ACCA', 'name' => 'Chart of Accounts', 'icon' => 'ti-tree',         'route' => '/accounting/accounts', 'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'accounting'],
        ['slug' => 'accounting-journals', 'prefix' => 'ACCJ', 'name' => 'Journals',          'icon' => 'ti-book',         'route' => '/accounting/journals', 'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'accounting'],
        ['slug' => 'accounting-bank',                'prefix' => 'ACCB',  'name' => 'Bank',         'icon' => 'ti-building-bank',  'route' => '/accounting/bank',                  'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'accounting'],
        // Disbursement is a nested grouping (no route of its own). Vendor cross-links to the Supplier extension; Bills + others (Pay Bill, Reimbursement, Cash Advance, Settlement, Expense) live here as they ship.
        ['slug' => 'accounting-disbursement',        'prefix' => 'ACCD',  'name' => 'Disbursement', 'icon' => 'ti-cash-banknote',  'route' => null,                                'group' => 'apps', 'sort_order' => 4, 'parent_slug' => 'accounting'],
        ['slug' => 'accounting-disbursement-vendor', 'prefix' => 'ACCDV', 'name' => 'Vendor',       'icon' => 'ti-truck-delivery', 'route' => '/inventory/suppliers?vendor_only=1','group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'accounting-disbursement'],
        // Bills slug kept stable (`accounting-bills`) — only `parent_slug`, `sort_order`, and `route` moved when
        // the Disbursement IA was introduced. Any code referencing the slug string keeps working.
        ['slug' => 'accounting-bills',                  'prefix' => 'ACCBL', 'name' => 'Bills',     'icon' => 'ti-file-invoice', 'route' => '/accounting/disbursement/bills',     'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'accounting-disbursement'],
        ['slug' => 'accounting-disbursement-pay-bills',      'prefix' => 'ACCPB',  'name' => 'Pay Bill',      'icon' => 'ti-cash-register', 'route' => '/accounting/disbursement/pay-bills',      'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'accounting-disbursement'],
        ['slug' => 'accounting-disbursement-reimbursements', 'prefix' => 'ACCRM',  'name' => 'Reimbursement', 'icon' => 'ti-receipt-2',     'route' => '/accounting/disbursement/reimbursements', 'group' => 'apps', 'sort_order' => 4, 'parent_slug' => 'accounting-disbursement'],
        ['slug' => 'accounting-disbursement-cash-advances',  'prefix' => 'ACCCA',  'name' => 'Cash Advance',  'icon' => 'ti-wallet',        'route' => '/accounting/disbursement/cash-advances',  'group' => 'apps', 'sort_order' => 5, 'parent_slug' => 'accounting-disbursement'],
        ['slug' => 'accounting-disbursement-advance-settlements', 'prefix' => 'ACCAS',  'name' => 'Advance Settlement', 'icon' => 'ti-receipt-refund', 'route' => '/accounting/disbursement/advance-settlements', 'group' => 'apps', 'sort_order' => 6, 'parent_slug' => 'accounting-disbursement'],
        ['slug' => 'accounting-disbursement-expenses',            'prefix' => 'ACCEX',  'name' => 'Expense',            'icon' => 'ti-receipt-tax',    'route' => '/accounting/disbursement/expenses',            'group' => 'apps', 'sort_order' => 7, 'parent_slug' => 'accounting-disbursement'],

        // Receivable is a nested grouping mirroring Disbursement. Customers cross-links to the Sales/Customers page;
        // Receipts + (planned) Credit/Debit Notes live here as they ship.
        ['slug' => 'accounting-receivable',           'prefix' => 'ACCR',  'name' => 'Receivable', 'icon' => 'ti-arrow-down-right', 'route' => null,                   'group' => 'apps', 'sort_order' => 5, 'parent_slug' => 'accounting'],
        ['slug' => 'accounting-receivable-customer',  'prefix' => 'ACCRC', 'name' => 'Customer',   'icon' => 'ti-users',            'route' => '/sales/customers',     'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'accounting-receivable'],
        ['slug' => 'accounting-receivable-receipts',     'prefix' => 'ACCRR',  'name' => 'Receipts',     'icon' => 'ti-cash',           'route' => '/accounting/receivable/receipts',     'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'accounting-receivable'],
        ['slug' => 'accounting-receivable-credit-notes', 'prefix' => 'ACCRCN', 'name' => 'Credit Notes', 'icon' => 'ti-file-arrow-left', 'route' => '/accounting/receivable/credit-notes', 'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'accounting-receivable'],
        ['slug' => 'accounting-receivable-debit-notes',  'prefix' => 'ACCRDN', 'name' => 'Debit Notes',  'icon' => 'ti-file-arrow-right','route' => '/accounting/receivable/debit-notes',  'group' => 'apps', 'sort_order' => 4, 'parent_slug' => 'accounting-receivable'],

        // Bank Reconciliation sits as a sibling of Bank (rather than nested under it) to avoid
        // restructuring the existing /accounting/bank route. Future IA pass may collapse them
        // under a Bank subgroup; the slug is stable as `accounting-bank-reconciliation`.
        ['slug' => 'accounting-bank-reconciliation', 'prefix' => 'ACCBR', 'name' => 'Bank Reconciliation', 'icon' => 'ti-checks', 'route' => '/accounting/bank-reconciliation', 'group' => 'apps', 'sort_order' => 6, 'parent_slug' => 'accounting'],
        ['slug' => 'accounting-budgets',             'prefix' => 'ACCBG', 'name' => 'Budgets',             'icon' => 'ti-target','route' => '/accounting/budgets',             'group' => 'apps', 'sort_order' => 7, 'parent_slug' => 'accounting'],
        ['slug' => 'accounting-fiscal-periods',      'prefix' => 'ACCFP', 'name' => 'Fiscal Periods',      'icon' => 'ti-calendar-check','route' => '/accounting/fiscal-periods', 'group' => 'apps', 'sort_order' => 8, 'parent_slug' => 'accounting'],

        // Apps: Inventory & SCM
        // `ecom-products` slug retained (re-parented to `inventory`) — `product_modules` FKs reference it.
        ['slug' => 'inventory',            'prefix' => 'INV',  'name' => 'Inventory',       'icon' => 'ti-building-warehouse', 'route' => null,                           'group' => 'apps', 'sort_order' => 5],
        ['slug' => 'ecom-products',        'prefix' => 'ECOP', 'name' => 'Products',        'icon' => 'ti-package',            'route' => '/inventory/products',          'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'inventory'],
        ['slug' => 'inv-categories',       'prefix' => 'INVC', 'name' => 'Categories',      'icon' => 'ti-category',           'route' => '/inventory/categories',        'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'inventory'],
        ['slug' => 'inv-warehouses',       'prefix' => 'INVW', 'name' => 'Warehouses',      'icon' => 'ti-building-warehouse', 'route' => '/inventory/warehouses',        'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'inventory'],
        ['slug' => 'inv-suppliers',        'prefix' => 'INVS', 'name' => 'Suppliers',       'icon' => 'ti-truck-delivery',     'route' => '/inventory/suppliers',         'group' => 'apps', 'sort_order' => 4, 'parent_slug' => 'inventory'],
        ['slug' => 'inv-purchase-orders',  'prefix' => 'INVP', 'name' => 'Purchase Orders', 'icon' => 'ti-shopping-bag',       'route' => '/inventory/purchase-orders',   'group' => 'apps', 'sort_order' => 5, 'parent_slug' => 'inventory'],

        // Apps: HRM
        // Slugs are kept stable across label revisions so module pivots / product_modules FKs
        // and any code referencing slug strings keep working — only display fields change.
        ['slug' => 'hrm',              'prefix' => 'HRM',    'name' => 'Human Resources', 'icon' => 'ti-users',          'route' => null,           'group' => 'apps', 'sort_order' => 6],

        // Apps: Calendar (cross-cutting time view, personal + admin)
        ['slug' => 'calendar',          'prefix' => 'CAL',   'name' => 'Calendar',          'icon' => 'ti-calendar',        'route' => null,                     'group' => 'apps', 'sort_order' => 7],
        ['slug' => 'calendar-personal', 'prefix' => 'CALP',  'name' => 'My Calendar',       'icon' => 'ti-calendar-user',   'route' => '/calendar',              'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'calendar', 'is_core' => true],
        ['slug' => 'calendar-team',     'prefix' => 'CALT',  'name' => 'Team Calendar',     'icon' => 'ti-calendar-month',  'route' => '/hrm/timeoff/calendar',  'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'calendar'],
        ['slug' => 'calendar-holidays', 'prefix' => 'CALH',  'name' => 'Holidays',          'icon' => 'ti-confetti',        'route' => '/hrm/timeoff/holidays',  'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'calendar'],

        // HRM > Talent Acquisition (slug kept as hrm-recruitments)
        ['slug' => 'hrm-recruitments',              'prefix' => 'HRMR',   'name' => 'Talent Acquisition', 'icon' => 'ti-user-plus',     'route' => null,                              'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'hrm'],
        ['slug' => 'hrm-recruitments-vacancies',    'prefix' => 'HRMRV',  'name' => 'Vacancies',          'icon' => 'ti-briefcase-2',   'route' => '/hrm/recruitments/vacancies',     'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'hrm-recruitments'],
        ['slug' => 'hrm-recruitments-applications', 'prefix' => 'HRMRA',  'name' => 'Applications',       'icon' => 'ti-user-search',   'route' => '/hrm/recruitments/applications',  'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'hrm-recruitments'],
        ['slug' => 'hrm-recruitments-stages',       'prefix' => 'HRMRS',  'name' => 'Candidate Stages',   'icon' => 'ti-layout-kanban', 'route' => '/hrm/recruitments/candidates',    'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'hrm-recruitments'],

        // HRM > Employee Management (slug kept as hrm-employees)
        ['slug' => 'hrm-employees',              'prefix' => 'HRME',   'name' => 'Employee Management', 'icon' => 'ti-user-circle', 'route' => null,               'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'hrm'],
        ['slug' => 'hrm-employees-list',         'prefix' => 'HRMEL',  'name' => 'Employee Directory',  'icon' => 'ti-address-book','route' => '/hrm/employees',   'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'hrm-employees'],
        ['slug' => 'hrm-employees-positions',    'prefix' => 'HRMEP',  'name' => 'Positions & Roles',   'icon' => 'ti-briefcase',   'route' => '/hrm/positions',   'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'hrm-employees'],
        ['slug' => 'hrm-employees-departments',  'prefix' => 'HRMED',  'name' => 'Departments',         'icon' => 'ti-building',    'route' => '/hrm/departments', 'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'hrm-employees'],

        // HRM > Time & Attendance (slug kept as hrm-timeoff)
        ['slug' => 'hrm-timeoff',            'prefix' => 'HRMT',  'name' => 'Time & Attendance',  'icon' => 'ti-clock-check',    'route' => null,                       'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'hrm'],
        ['slug' => 'hrm-timeoff-attendance', 'prefix' => 'HRMTA', 'name' => 'Attendance Tracking','icon' => 'ti-fingerprint',    'route' => '/hrm/timeoff/attendance',  'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'hrm-timeoff'],
        ['slug' => 'hrm-timeoff-shifts',     'prefix' => 'HRMTS', 'name' => 'Shift Scheduling',   'icon' => 'ti-clock-hour-8',   'route' => '/hrm/timeoff/shifts',      'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'hrm-timeoff'],
        ['slug' => 'hrm-timeoff-overtime',   'prefix' => 'HRMTO', 'name' => 'Overtime Management','icon' => 'ti-clock-up',       'route' => '/hrm/timeoff/overtime',    'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'hrm-timeoff'],
        ['slug' => 'hrm-timeoff-leaves',     'prefix' => 'HRMTL', 'name' => 'Leave Requests',     'icon' => 'ti-calendar-event', 'route' => '/hrm/timeoff/leaves',      'group' => 'apps', 'sort_order' => 4, 'parent_slug' => 'hrm-timeoff'],
        ['slug' => 'hrm-timeoff-holidays',   'prefix' => 'HRMTH', 'name' => 'Holidays',           'icon' => 'ti-confetti',       'route' => '/hrm/timeoff/holidays',    'group' => 'apps', 'sort_order' => 5, 'parent_slug' => 'hrm-timeoff'],
        ['slug' => 'hrm-timeoff-calendar',   'prefix' => 'HRMTC', 'name' => 'Calendar',           'icon' => 'ti-calendar',       'route' => '/hrm/timeoff/calendar',    'group' => 'apps', 'sort_order' => 6, 'parent_slug' => 'hrm-timeoff'],

        // HRM > Performance Management (slug kept as hrm-appraisals)
        ['slug' => 'hrm-appraisals',             'prefix' => 'HRMPR',  'name' => 'Performance Management', 'icon' => 'ti-stars',            'route' => null,              'group' => 'apps', 'sort_order' => 4, 'parent_slug' => 'hrm'],
        ['slug' => 'hrm-appraisals-performance', 'prefix' => 'HRMPRP', 'name' => 'Performance Appraisals', 'icon' => 'ti-clipboard-check', 'route' => '/hrm/appraisals', 'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'hrm-appraisals'],

        // HRM > Payroll (standalone, last item)
        ['slug' => 'hrm-payroll', 'prefix' => 'HRMPA', 'name' => 'Payroll', 'icon' => 'ti-cash', 'route' => '/hrm/payroll', 'group' => 'apps', 'sort_order' => 5, 'parent_slug' => 'hrm'],

        // Apps: standalone
        // Fleets: group → 3 leaf pages. The route on the parent stays null so
        // clicks open the disclosure rather than navigating. The two non-active
        // leaves keep route='#' until their pages land — the sidebar renders
        // them as disabled "coming soon" buttons via the operational flag.
        ['slug' => 'fleets',            'prefix' => 'FLT',  'name' => 'Fleets',      'icon' => 'ti-truck',        'route' => null,              'group' => 'apps', 'sort_order' => 7],
        ['slug' => 'fleet-vehicles',    'prefix' => 'FLTV', 'name' => 'Vehicles',    'icon' => 'ti-car',          'route' => '/fleet/vehicles', 'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'fleets'],
        ['slug' => 'fleet-maintenance', 'prefix' => 'FLTM', 'name' => 'Maintenance', 'icon' => 'ti-tool',         'route' => '/fleet/maintenance', 'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'fleets'],
        ['slug' => 'fleet-fuel',        'prefix' => 'FLTF', 'name' => 'Fuel Logs',   'icon' => 'ti-gas-station',  'route' => '/fleet/fuel',     'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'fleets'],
        // Apps: Fixed Assets — five leaves under the parent slug `assets` used
        // by useModules().hasModule('assets') to gate the whole sidebar group.
        // Sort-order intentionally collides with `fleets` (7) — the visual nav
        // order is driven by the literal navGroups array in layouts/default.vue;
        // the Modules admin page falls back to alphabetical within a tier.
        ['slug' => 'assets',            'prefix' => 'AST',  'name' => 'Fixed Assets',  'icon' => 'ti-cube',           'route' => null,                  'group' => 'apps', 'sort_order' => 7],
        ['slug' => 'assets-registry',   'prefix' => 'ASTR', 'name' => 'Asset Registry','icon' => 'ti-list-check',     'route' => '/assets',             'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'assets'],
        ['slug' => 'assets-depreciation','prefix'=> 'ASTD', 'name' => 'Depreciation',  'icon' => 'ti-receipt',        'route' => '/assets/depreciation','group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'assets'],
        ['slug' => 'assets-revaluation','prefix' => 'ASTV', 'name' => 'Revaluation',   'icon' => 'ti-stars',          'route' => '/assets/revaluation', 'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'assets'],
        ['slug' => 'assets-disposal',   'prefix' => 'ASTX', 'name' => 'Disposal',      'icon' => 'ti-archive',        'route' => '/assets/disposal',    'group' => 'apps', 'sort_order' => 4, 'parent_slug' => 'assets'],
        ['slug' => 'assets-audits',     'prefix' => 'ASTA', 'name' => 'Audit Campaigns','icon'=> 'ti-calendar-stats', 'route' => '/assets/audits',      'group' => 'apps', 'sort_order' => 5, 'parent_slug' => 'assets'],
        ['slug' => 'projects',            'prefix' => 'PROJ',  'name' => 'Project Management', 'icon' => 'ti-presentation', 'route' => null,                  'group' => 'apps', 'sort_order' => 8],
        ['slug' => 'projects-overview',   'prefix' => 'PROJP', 'name' => 'Projects',           'icon' => 'ti-folder-open',  'route' => '/projects',           'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'projects'],
        ['slug' => 'projects-tasks',      'prefix' => 'PROJT', 'name' => 'Tasks',              'icon' => 'ti-checkbox',     'route' => '/projects/tasks',     'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'projects'],
        ['slug' => 'projects-timesheets', 'prefix' => 'PROJH', 'name' => 'Timesheets',         'icon' => 'ti-clock-hour-3', 'route' => '/projects/timesheets','group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'projects'],
        ['slug' => 'eapprovals', 'prefix' => 'EAPP', 'name' => 'eApprovals',         'icon' => 'ti-circle-check', 'route' => '#', 'group' => 'apps', 'sort_order' => 9],
        ['slug' => 'edocuments', 'prefix' => 'EDOC', 'name' => 'eDocuments',         'icon' => 'ti-file-text',    'route' => '#', 'group' => 'apps', 'sort_order' => 10],
        ['slug' => 'reporting',  'prefix' => 'RPT',  'name' => 'Reports & Analytics','icon' => 'ti-chart-bar',    'route' => '#', 'group' => 'apps', 'sort_order' => 11],

        // Core: Configurations
        ['slug' => 'settings-apps',   'prefix' => 'SETA', 'name' => 'Apps Management', 'icon' => 'ti-box',        'route' => null,        'group' => 'apps', 'sort_order' => 12, 'is_core' => true],
        // Apps Management sub-tree — each system that owns auto-generated codes
        // exposes its prefix matrix as a `Prefix Code` leaf under its own group.
        // Backend setting keys (numbering.*) are unchanged; the grouping is a
        // pure UI organization concern. Invoice + Subscription sit under
        // Finance (financial documents), Asset Code under System (FAM infra).
        ['slug' => 'settings-apps-hrm', 'prefix' => 'SETAH', 'name' => 'Human Resource', 'icon' => 'ti-users',   'route' => null, 'group' => 'apps', 'sort_order' => 1, 'is_core' => true, 'parent_slug' => 'settings-apps'],
        ['slug' => 'settings-apps-hrm-leave-types', 'prefix' => 'SETAHL', 'name' => 'Leave Types', 'icon' => 'ti-list', 'route' => '/settings/apps/hrm/leave-types', 'group' => 'apps', 'sort_order' => 1, 'is_core' => true, 'parent_slug' => 'settings-apps-hrm'],
        ['slug' => 'settings-apps-hrm-prefix-code', 'prefix' => 'SETAHP', 'name' => 'Prefix Code', 'icon' => 'ti-hash', 'route' => '/settings/apps/hrm/prefix-code', 'group' => 'apps', 'sort_order' => 2, 'is_core' => true, 'parent_slug' => 'settings-apps-hrm'],
        ['slug' => 'settings-apps-sales', 'prefix' => 'SETASLS', 'name' => 'Sales', 'icon' => 'ti-address-book', 'route' => null, 'group' => 'apps', 'sort_order' => 2, 'is_core' => true, 'parent_slug' => 'settings-apps'],
        ['slug' => 'settings-apps-sales-prefix-code', 'prefix' => 'SETASLSP', 'name' => 'Prefix Code', 'icon' => 'ti-hash', 'route' => '/settings/apps/sales/prefix-code', 'group' => 'apps', 'sort_order' => 1, 'is_core' => true, 'parent_slug' => 'settings-apps-sales'],
        ['slug' => 'settings-apps-inventory', 'prefix' => 'SETAINV', 'name' => 'Inventory', 'icon' => 'ti-building-warehouse', 'route' => null, 'group' => 'apps', 'sort_order' => 3, 'is_core' => true, 'parent_slug' => 'settings-apps'],
        ['slug' => 'settings-apps-inventory-prefix-code', 'prefix' => 'SETAINVP', 'name' => 'Prefix Code', 'icon' => 'ti-hash', 'route' => '/settings/apps/inventory/prefix-code', 'group' => 'apps', 'sort_order' => 1, 'is_core' => true, 'parent_slug' => 'settings-apps-inventory'],
        ['slug' => 'settings-apps-finance', 'prefix' => 'SETAFIN', 'name' => 'Finance', 'icon' => 'ti-coin', 'route' => null, 'group' => 'apps', 'sort_order' => 4, 'is_core' => true, 'parent_slug' => 'settings-apps'],
        ['slug' => 'settings-apps-finance-prefix-code', 'prefix' => 'SETAFINP', 'name' => 'Prefix Code', 'icon' => 'ti-hash', 'route' => '/settings/apps/finance/prefix-code', 'group' => 'apps', 'sort_order' => 1, 'is_core' => true, 'parent_slug' => 'settings-apps-finance'],
        ['slug' => 'settings-apps-system', 'prefix' => 'SETASYS', 'name' => 'System', 'icon' => 'ti-cube', 'route' => null, 'group' => 'apps', 'sort_order' => 5, 'is_core' => true, 'parent_slug' => 'settings-apps'],
        ['slug' => 'settings-apps-system-prefix-code', 'prefix' => 'SETASYSP', 'name' => 'Prefix Code', 'icon' => 'ti-hash', 'route' => '/settings/apps/system/prefix-code', 'group' => 'apps', 'sort_order' => 1, 'is_core' => true, 'parent_slug' => 'settings-apps-system'],
        ['slug' => 'settings-apps-fleet', 'prefix' => 'SETAFLT', 'name' => 'Fleet', 'icon' => 'ti-truck', 'route' => null, 'group' => 'apps', 'sort_order' => 6, 'is_core' => true, 'parent_slug' => 'settings-apps'],
        ['slug' => 'settings-apps-fleet-vehicle-models', 'prefix' => 'SETAFLTV', 'name' => 'Vehicle Models', 'icon' => 'ti-car', 'route' => '/settings/apps/fleet/vehicle-models', 'group' => 'apps', 'sort_order' => 1, 'is_core' => true, 'parent_slug' => 'settings-apps-fleet'],

        ['slug' => 'settings-users',  'prefix' => 'SETU', 'name' => 'User Directory', 'icon' => 'ti-users-group',  'route' => '/settings/users',    'group' => 'apps', 'sort_order' => 13, 'is_core' => true],
        ['slug' => 'settings-roles',  'prefix' => 'SETR', 'name' => 'Roles Matrix',   'icon' => 'ti-shield-check', 'route' => '/settings/roles',    'group' => 'apps', 'sort_order' => 14, 'is_core' => true],

        ['slug' => 'settings-config', 'prefix' => 'SETC', 'name' => 'Configuration',  'icon' => 'ti-settings',     'route' => null, 'group' => 'apps', 'sort_order' => 16, 'is_core' => true],
        ['slug' => 'settings-config-branding', 'prefix' => 'SETCB', 'name' => 'Branding', 'icon' => 'ti-palette', 'route' => '/settings/configuration/branding', 'group' => 'apps', 'sort_order' => 1, 'is_core' => true, 'parent_slug' => 'settings-config'],
        ['slug' => 'settings-config-locale', 'prefix' => 'SETCL', 'name' => 'Locale', 'icon' => 'ti-language', 'route' => '/settings/configuration/locale', 'group' => 'apps', 'sort_order' => 2, 'is_core' => true, 'parent_slug' => 'settings-config'],
        ['slug' => 'settings-config-notifications', 'prefix' => 'SETCN', 'name' => 'Notifications', 'icon' => 'ti-bell', 'route' => '/settings/configuration/notifications', 'group' => 'apps', 'sort_order' => 3, 'is_core' => true, 'parent_slug' => 'settings-config'],
        ['slug' => 'settings-config-security', 'prefix' => 'SETCS', 'name' => 'Security', 'icon' => 'ti-shield-lock', 'route' => '/settings/configuration/security', 'group' => 'apps', 'sort_order' => 4, 'is_core' => true, 'parent_slug' => 'settings-config'],
        ['slug' => 'settings-config-modules', 'prefix' => 'SETCM', 'name' => 'Modules', 'icon' => 'ti-puzzle', 'route' => '/settings/configuration/modules', 'group' => 'apps', 'sort_order' => 5, 'is_core' => true, 'parent_slug' => 'settings-config'],
        ['slug' => 'settings-config-platform', 'prefix' => 'SETCP', 'name' => 'Platform', 'icon' => 'ti-server', 'route' => '/settings/configuration/platform', 'group' => 'apps', 'sort_order' => 6, 'is_core' => true, 'parent_slug' => 'settings-config'],
    ];

    public function run(): void
    {
        // Delete modules that are no longer defined
        $slugs = array_column($this->definitions, 'slug');
        Module::whereNotIn('slug', $slugs)->delete();

        $idMap = [];
        $pending = $this->definitions;

        // Iterative topological resolution — a definition is inserted once its
        // parent_slug (if any) is in $idMap. Supports arbitrary depth
        // (e.g. crm › sales-pipeline › opportunities). Throws on a definition
        // whose parent_slug doesn't exist anywhere in the catalogue.
        $maxIterations = count($pending) + 2;
        for ($i = 0; $i < $maxIterations && !empty($pending); $i++) {
            $remaining = [];
            $progress = false;

            foreach ($pending as $def) {
                $parentSlug = $def['parent_slug'] ?? null;
                if ($parentSlug !== null && !isset($idMap[$parentSlug])) {
                    $remaining[] = $def;
                    continue;
                }

                $attrs = $this->attrs($def);
                if ($parentSlug !== null) {
                    $attrs['parent_id'] = $idMap[$parentSlug];
                }

                // withTrashed: a previous seeder run may have soft-deleted this
                // slug. The unique index is on (slug, tenant_id) without
                // deleted_at, so a plain updateOrCreate would collide on insert.
                // We look up including trashed rows, restore if found, then sync.
                $module = Module::withTrashed()->updateOrCreate(['slug' => $def['slug']], $attrs);
                if ($module->trashed()) {
                    $module->restore();
                }
                $idMap[$def['slug']] = $module->id;
                $progress = true;
            }

            if (!$progress) {
                $orphans = array_map(fn ($d) => "{$d['slug']} (parent: {$d['parent_slug']})", $remaining);
                throw new \RuntimeException(
                    'ModuleSeeder: unresolved parent_slug on ' . implode(', ', $orphans)
                );
            }

            $pending = $remaining;
        }
    }

    private function attrs(array $def): array
    {
        return [
            'prefix'      => $def['prefix'],
            'name'        => $def['name'],
            'icon'        => $def['icon'] ?? null,
            'description' => null,
            'route'       => $def['route'] ?? null,
            'group'       => $def['group'] ?? 'apps',
            'sort_order'  => $def['sort_order'] ?? 0,
            'is_active'   => true,
            'is_core'     => $def['is_core'] ?? false,
        ];
    }
}
