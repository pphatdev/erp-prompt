<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Tenant\Permission;
use App\Models\Tenant\Role;
use Illuminate\Database\Seeder;

/**
 * Backfill the crm.* permissions and attach them to every admin role in all
 * existing tenant databases.
 *
 * Run per-tenant:
 *   php artisan tenants:run db:seed --option="class=Database\Seeders\CrmPermissionSeeder" --option="force=true"
 */
class CrmPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $permissions = [
            ['name' => 'Read Leads',           'slug' => 'crm.leads.read',           'module' => 'crm', 'feature' => 'leads',         'action' => 'read'],
            ['name' => 'Write Leads',          'slug' => 'crm.leads.write',          'module' => 'crm', 'feature' => 'leads',         'action' => 'write'],
            ['name' => 'Delete Leads',         'slug' => 'crm.leads.delete',         'module' => 'crm', 'feature' => 'leads',         'action' => 'delete'],
            ['name' => 'Read Opportunities',   'slug' => 'crm.opportunities.read',   'module' => 'crm', 'feature' => 'opportunities', 'action' => 'read'],
            ['name' => 'Write Opportunities',  'slug' => 'crm.opportunities.write',  'module' => 'crm', 'feature' => 'opportunities', 'action' => 'write'],
            ['name' => 'Delete Opportunities', 'slug' => 'crm.opportunities.delete', 'module' => 'crm', 'feature' => 'opportunities', 'action' => 'delete'],
            ['name' => 'Read CRM Contacts',    'slug' => 'crm.contacts.read',        'module' => 'crm', 'feature' => 'contacts',      'action' => 'read'],
            ['name' => 'Write CRM Contacts',   'slug' => 'crm.contacts.write',       'module' => 'crm', 'feature' => 'contacts',      'action' => 'write'],
            ['name' => 'Delete CRM Contacts',  'slug' => 'crm.contacts.delete',      'module' => 'crm', 'feature' => 'contacts',      'action' => 'delete'],
            ['name' => 'Read CRM Activities',  'slug' => 'crm.activities.read',      'module' => 'crm', 'feature' => 'activities',    'action' => 'read'],
            ['name' => 'Write CRM Activities', 'slug' => 'crm.activities.write',     'module' => 'crm', 'feature' => 'activities',    'action' => 'write'],
            ['name' => 'Delete CRM Activities','slug' => 'crm.activities.delete',    'module' => 'crm', 'feature' => 'activities',    'action' => 'delete'],
            ['name' => 'Read CRM Appointments', 'slug' => 'crm.appointments.read',   'module' => 'crm', 'feature' => 'appointments',  'action' => 'read'],
            ['name' => 'Write CRM Appointments','slug' => 'crm.appointments.write',  'module' => 'crm', 'feature' => 'appointments',  'action' => 'write'],
            ['name' => 'Delete CRM Appointments','slug'=> 'crm.appointments.delete', 'module' => 'crm', 'feature' => 'appointments',  'action' => 'delete'],
        ];

        $ids = [];
        foreach ($permissions as $perm) {
            $ids[] = Permission::updateOrCreate(['slug' => $perm['slug']], $perm)->id;
        }

        Role::where('slug', 'admin')->each(function (Role $role) use ($ids) {
            $role->permissions()->syncWithoutDetaching($ids);
        });
    }
}
