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
        ['slug' => 'tasks',        'prefix' => 'TASK',   'name' => 'Tasks Canvas',        'icon' => 'ti-checklist',        'route' => '/tasks',     'group' => 'main', 'sort_order' => 2],

        // Self-service
        ['slug' => 'my-profile',    'prefix' => 'MYPR', 'name' => 'My Profile',    'icon' => 'ti-user-circle',    'route' => '#',           'group' => 'self-service', 'sort_order' => 1, 'is_core' => true],
        ['slug' => 'my-leaves',     'prefix' => 'MYLS', 'name' => 'My Leaves',     'icon' => 'ti-calendar-event', 'route' => '/leaves',     'group' => 'self-service', 'sort_order' => 2],
        ['slug' => 'my-payslips',   'prefix' => 'MYPS', 'name' => 'My Payslips',   'icon' => 'ti-cash',           'route' => '#',           'group' => 'self-service', 'sort_order' => 3],
        ['slug' => 'my-appraisals', 'prefix' => 'MYAP', 'name' => 'My Appraisals', 'icon' => 'ti-clipboard-list', 'route' => '/appraisals', 'group' => 'self-service', 'sort_order' => 4],

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

        // Apps: Inventory & SCM
        // `ecom-products` slug retained (re-parented to `inventory`) — `product_modules` FKs reference it.
        ['slug' => 'inventory',            'prefix' => 'INV',  'name' => 'Inventory',       'icon' => 'ti-building-warehouse', 'route' => null,                           'group' => 'apps', 'sort_order' => 5],
        ['slug' => 'ecom-products',        'prefix' => 'ECOP', 'name' => 'Products',        'icon' => 'ti-package',            'route' => '/inventory/products',          'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'inventory'],
        ['slug' => 'inv-categories',       'prefix' => 'INVC', 'name' => 'Categories',      'icon' => 'ti-category',           'route' => '/inventory/categories',        'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'inventory'],
        ['slug' => 'inv-warehouses',       'prefix' => 'INVW', 'name' => 'Warehouses',      'icon' => 'ti-building-warehouse', 'route' => '/inventory/warehouses',        'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'inventory'],
        ['slug' => 'inv-suppliers',        'prefix' => 'INVS', 'name' => 'Suppliers',       'icon' => 'ti-truck-delivery',     'route' => '/inventory/suppliers',         'group' => 'apps', 'sort_order' => 4, 'parent_slug' => 'inventory'],
        ['slug' => 'inv-purchase-orders',  'prefix' => 'INVP', 'name' => 'Purchase Orders', 'icon' => 'ti-shopping-bag',       'route' => '/inventory/purchase-orders',   'group' => 'apps', 'sort_order' => 5, 'parent_slug' => 'inventory'],

        // Apps: HRM
        ['slug' => 'hrm',              'prefix' => 'HRM',    'name' => 'Human Resource', 'icon' => 'ti-users',          'route' => null,           'group' => 'apps', 'sort_order' => 6],
        ['slug' => 'hrm-employees',    'prefix' => 'HRME',   'name' => 'Employees',      'icon' => 'ti-user-circle',    'route' => '/employees',   'group' => 'apps', 'sort_order' => 1,  'parent_slug' => 'hrm'],
        ['slug' => 'hrm-departments',  'prefix' => 'HRMD',   'name' => 'Departments',    'icon' => 'ti-building',       'route' => '/departments', 'group' => 'apps', 'sort_order' => 2,  'parent_slug' => 'hrm'],
        ['slug' => 'hrm-positions',    'prefix' => 'HRMPOS', 'name' => 'Positions',      'icon' => 'ti-briefcase',      'route' => '/positions',   'group' => 'apps', 'sort_order' => 3,  'parent_slug' => 'hrm'],
        ['slug' => 'hrm-leaves',       'prefix' => 'HRMLV',  'name' => 'Leave Requests', 'icon' => 'ti-calendar-event', 'route' => '/leaves',      'group' => 'apps', 'sort_order' => 4,  'parent_slug' => 'hrm'],
        ['slug' => 'hrm-leave-types',  'prefix' => 'HRMLT',  'name' => 'Leave Types',    'icon' => 'ti-list',           'route' => '/leave-types', 'group' => 'apps', 'sort_order' => 5,  'parent_slug' => 'hrm'],
        ['slug' => 'hrm-shifts',       'prefix' => 'HRMSF',  'name' => 'Shifts',         'icon' => 'ti-clock-hour-8',   'route' => '/shifts',      'group' => 'apps', 'sort_order' => 6,  'parent_slug' => 'hrm'],
        ['slug' => 'hrm-attendance',   'prefix' => 'HRMAT',  'name' => 'Attendance',     'icon' => 'ti-fingerprint',    'route' => '/attendance',  'group' => 'apps', 'sort_order' => 7,  'parent_slug' => 'hrm'],
        ['slug' => 'hrm-overtime',     'prefix' => 'HRMOT',  'name' => 'Overtime',       'icon' => 'ti-clock-up',       'route' => '/overtime',    'group' => 'apps', 'sort_order' => 8,  'parent_slug' => 'hrm'],
        ['slug' => 'hrm-payroll',      'prefix' => 'HRMPA',  'name' => 'Payroll',        'icon' => 'ti-cash',           'route' => '/payroll',     'group' => 'apps', 'sort_order' => 9,  'parent_slug' => 'hrm'],
        ['slug' => 'hrm-vacancies',    'prefix' => 'HRMVC',  'name' => 'Vacancies',      'icon' => 'ti-briefcase-2',    'route' => '/vacancies',   'group' => 'apps', 'sort_order' => 10, 'parent_slug' => 'hrm'],
        ['slug' => 'hrm-applications', 'prefix' => 'HRMAP',  'name' => 'Applications',   'icon' => 'ti-user-search',    'route' => '/applications','group' => 'apps', 'sort_order' => 11, 'parent_slug' => 'hrm'],
        ['slug' => 'hrm-candidates',   'prefix' => 'HRMCD',  'name' => 'Candidates',     'icon' => 'ti-layout-kanban',  'route' => '/candidates',  'group' => 'apps', 'sort_order' => 12, 'parent_slug' => 'hrm'],
        ['slug' => 'hrm-appraisals',   'prefix' => 'HRMPR',  'name' => 'Appraisals',     'icon' => 'ti-clipboard-list', 'route' => '/appraisals',  'group' => 'apps', 'sort_order' => 13, 'parent_slug' => 'hrm'],

        // Apps: standalone
        ['slug' => 'fleets',     'prefix' => 'FLT',  'name' => 'Fleets',             'icon' => 'ti-truck',        'route' => '#', 'group' => 'apps', 'sort_order' => 7],
        ['slug' => 'projects',   'prefix' => 'PROJ', 'name' => 'Project Management', 'icon' => 'ti-presentation', 'route' => '#', 'group' => 'apps', 'sort_order' => 8],
        ['slug' => 'eapprovals', 'prefix' => 'EAPP', 'name' => 'eApprovals',         'icon' => 'ti-circle-check', 'route' => '#', 'group' => 'apps', 'sort_order' => 9],
        ['slug' => 'edocuments', 'prefix' => 'EDOC', 'name' => 'eDocuments',         'icon' => 'ti-file-text',    'route' => '#', 'group' => 'apps', 'sort_order' => 10],
        ['slug' => 'reporting',  'prefix' => 'RPT',  'name' => 'Reports & Analytics','icon' => 'ti-chart-bar',    'route' => '#', 'group' => 'apps', 'sort_order' => 11],

        // Core: Settings
        ['slug' => 'settings',        'prefix' => 'SET',  'name' => 'Settings',       'icon' => 'ti-shield-lock',  'route' => null,        'group' => 'apps', 'sort_order' => 12, 'is_core' => true],
        ['slug' => 'settings-users',  'prefix' => 'SETU', 'name' => 'User Directory', 'icon' => 'ti-users-group',  'route' => '/users',    'group' => 'apps', 'sort_order' => 1, 'is_core' => true, 'parent_slug' => 'settings'],
        ['slug' => 'settings-roles',  'prefix' => 'SETR', 'name' => 'Roles Matrix',   'icon' => 'ti-shield-check', 'route' => '/roles',    'group' => 'apps', 'sort_order' => 2, 'is_core' => true, 'parent_slug' => 'settings'],
        ['slug' => 'settings-config', 'prefix' => 'SETC', 'name' => 'Configuration',  'icon' => 'ti-settings',     'route' => '/settings', 'group' => 'apps', 'sort_order' => 3, 'is_core' => true, 'parent_slug' => 'settings'],
    ];

    public function run(): void
    {
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

                $module = Module::updateOrCreate(['slug' => $def['slug']], $attrs);
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
