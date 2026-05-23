<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\Module;
use Illuminate\Database\Seeder;

/**
 * Seeds every sidebar menu item as a Module row.
 *
 * prefix conventions (uppercase, ≤ 8 chars):
 *   DASH / TASK         → main group
 *   MY*                 → self-service group
 *   ECO* / SLS* / HRM* → app modules with children
 *   FLT / PROJ / EAPP / EDOC / RPT → standalone app modules
 *   SET*                → settings (core, always active)
 *
 * Two-pass to resolve parent_id from parent_slug before inserting children.
 */
class ModuleSeeder extends Seeder
{
    private array $definitions = [
        // ── Main ─────────────────────────────────────────────────────────────
        ['slug' => 'dashboard',    'prefix' => 'DASH',   'name' => 'Dashboard',          'icon' => 'ti-layout-dashboard', 'route' => '/dashboard', 'group' => 'main', 'sort_order' => 1, 'is_core' => true],
        ['slug' => 'tasks',        'prefix' => 'TASK',   'name' => 'Tasks Canvas',        'icon' => 'ti-checklist',        'route' => '/tasks',     'group' => 'main', 'sort_order' => 2],

        // ── Self-service ──────────────────────────────────────────────────────
        ['slug' => 'my-profile',    'prefix' => 'MYPR', 'name' => 'My Profile',    'icon' => 'ti-user-circle',    'route' => '#',           'group' => 'self-service', 'sort_order' => 1, 'is_core' => true],
        ['slug' => 'my-leaves',     'prefix' => 'MYLS', 'name' => 'My Leaves',     'icon' => 'ti-calendar-event', 'route' => '/leaves',     'group' => 'self-service', 'sort_order' => 2],
        ['slug' => 'my-payslips',   'prefix' => 'MYPS', 'name' => 'My Payslips',   'icon' => 'ti-cash',           'route' => '#',           'group' => 'self-service', 'sort_order' => 3],
        ['slug' => 'my-appraisals', 'prefix' => 'MYAP', 'name' => 'My Appraisals', 'icon' => 'ti-clipboard-list', 'route' => '/appraisals', 'group' => 'self-service', 'sort_order' => 4],

        // ── Apps: Ecommerce ───────────────────────────────────────────────────
        ['slug' => 'ecommerce',      'prefix' => 'ECO',  'name' => 'Ecommerce',  'icon' => 'ti-shopping-cart',  'route' => null, 'group' => 'apps', 'sort_order' => 1],
        ['slug' => 'ecom-products',  'prefix' => 'ECOP', 'name' => 'Products',   'icon' => 'ti-package',        'route' => '/products', 'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'ecommerce'],
        ['slug' => 'ecom-orders',    'prefix' => 'ECOO', 'name' => 'Orders',     'icon' => 'ti-receipt',        'route' => '#',         'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'ecommerce'],
        ['slug' => 'ecom-inventory', 'prefix' => 'ECOI', 'name' => 'Inventory',  'icon' => 'ti-archive',        'route' => '#',         'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'ecommerce'],
        ['slug' => 'ecom-refunds',   'prefix' => 'ECOR', 'name' => 'Refunds',    'icon' => 'ti-receipt-refund', 'route' => '#',         'group' => 'apps', 'sort_order' => 4, 'parent_slug' => 'ecommerce'],

        // ── Apps: Sales ───────────────────────────────────────────────────────
        ['slug' => 'sales',               'prefix' => 'SLS',  'name' => 'Sales',         'icon' => 'ti-address-book', 'route' => null,                   'group' => 'apps', 'sort_order' => 2],
        ['slug' => 'sales-customers',     'prefix' => 'SLSC', 'name' => 'Customers',     'icon' => 'ti-users',        'route' => '/sales/customers',     'group' => 'apps', 'sort_order' => 1, 'parent_slug' => 'sales'],
        ['slug' => 'sales-quotations',    'prefix' => 'SLSQ', 'name' => 'Quotations',    'icon' => 'ti-file-text',    'route' => '/sales/quotations',    'group' => 'apps', 'sort_order' => 2, 'parent_slug' => 'sales'],
        ['slug' => 'sales-orders',        'prefix' => 'SLSO', 'name' => 'Sales Orders',  'icon' => 'ti-shopping-cart','route' => '/sales/orders',        'group' => 'apps', 'sort_order' => 3, 'parent_slug' => 'sales'],
        ['slug' => 'sales-invoices',      'prefix' => 'SLSI', 'name' => 'Invoices',      'icon' => 'ti-receipt',      'route' => '/sales/invoices',      'group' => 'apps', 'sort_order' => 4, 'parent_slug' => 'sales'],
        ['slug' => 'sales-subscriptions', 'prefix' => 'SLSS', 'name' => 'Subscriptions', 'icon' => 'ti-cloud',        'route' => '/sales/subscriptions', 'group' => 'apps', 'sort_order' => 5, 'parent_slug' => 'sales'],

        // ── Apps: HRM ─────────────────────────────────────────────────────────
        ['slug' => 'hrm',              'prefix' => 'HRM',    'name' => 'Human Resource', 'icon' => 'ti-users',          'route' => null,           'group' => 'apps', 'sort_order' => 3],
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

        // ── Apps: standalone ──────────────────────────────────────────────────
        ['slug' => 'fleets',     'prefix' => 'FLT',  'name' => 'Fleets',             'icon' => 'ti-truck',        'route' => '#', 'group' => 'apps', 'sort_order' => 4],
        ['slug' => 'projects',   'prefix' => 'PROJ', 'name' => 'Project Management', 'icon' => 'ti-presentation', 'route' => '#', 'group' => 'apps', 'sort_order' => 5],
        ['slug' => 'eapprovals', 'prefix' => 'EAPP', 'name' => 'eApprovals',         'icon' => 'ti-circle-check', 'route' => '#', 'group' => 'apps', 'sort_order' => 6],
        ['slug' => 'edocuments', 'prefix' => 'EDOC', 'name' => 'eDocuments',         'icon' => 'ti-file-text',    'route' => '#', 'group' => 'apps', 'sort_order' => 7],
        ['slug' => 'reporting',  'prefix' => 'RPT',  'name' => 'Reports & Analytics','icon' => 'ti-chart-bar',    'route' => '#', 'group' => 'apps', 'sort_order' => 8],

        // ── Core: Settings ────────────────────────────────────────────────────
        ['slug' => 'settings',        'prefix' => 'SET',  'name' => 'Settings',       'icon' => 'ti-shield-lock',  'route' => null,        'group' => 'apps', 'sort_order' => 9, 'is_core' => true],
        ['slug' => 'settings-users',  'prefix' => 'SETU', 'name' => 'User Directory', 'icon' => 'ti-users-group',  'route' => '/users',    'group' => 'apps', 'sort_order' => 1, 'is_core' => true, 'parent_slug' => 'settings'],
        ['slug' => 'settings-roles',  'prefix' => 'SETR', 'name' => 'Roles Matrix',   'icon' => 'ti-shield-check', 'route' => '/roles',    'group' => 'apps', 'sort_order' => 2, 'is_core' => true, 'parent_slug' => 'settings'],
        ['slug' => 'settings-config', 'prefix' => 'SETC', 'name' => 'Configuration',  'icon' => 'ti-settings',     'route' => '/settings', 'group' => 'apps', 'sort_order' => 3, 'is_core' => true, 'parent_slug' => 'settings'],
    ];

    public function run(): void
    {
        $idMap = [];

        // Pass 1: top-level modules
        foreach ($this->definitions as $def) {
            if (isset($def['parent_slug'])) {
                continue;
            }
            $module = Module::updateOrCreate(
                ['slug' => $def['slug']],
                $this->attrs($def)
            );
            $idMap[$def['slug']] = $module->id;
        }

        // Pass 2: child modules
        foreach ($this->definitions as $def) {
            if (!isset($def['parent_slug'])) {
                continue;
            }
            Module::updateOrCreate(
                ['slug' => $def['slug']],
                array_merge($this->attrs($def), [
                    'parent_id' => $idMap[$def['parent_slug']] ?? null,
                ])
            );
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
