<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Central\Tenant as CentralTenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Repairs tenant user credentials for tenants provisioned before the
 * double-hashing and customer-admin-user fixes were applied.
 *
 * What it does inside each targeted tenant DB:
 *   1. Self-heals any user whose stored password is double-hashed
 *      (i.e. Hash::check('password', $hash) fails).
 *   2. Creates the customer admin user (customer email → password 'password')
 *      if it does not already exist, and attaches the admin role.
 *
 * Usage:
 *   php artisan tenants:repair-credentials                # all tenants
 *   php artisan tenants:repair-credentials --tenant=kean  # one tenant
 */
class RepairTenantCredentials extends Command
{
    protected $signature = 'tenants:repair-credentials
                            {--tenant= : Handle of a specific tenant to repair (omit for all)}';

    protected $description = 'Fix double-hashed passwords and create missing customer admin users in provisioned tenant DBs';

    public function handle(): int
    {
        $handle = $this->option('tenant');

        $query = CentralTenant::query();
        if ($handle) {
            $query->where('handle', $handle);
        }

        $tenants = $query->get();

        if ($tenants->isEmpty()) {
            $this->error($handle ? "Tenant '{$handle}' not found." : 'No tenants found.');
            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->info("Repairing tenant: {$tenant->handle}");

            $tenant->run(function () use ($tenant) {
                $this->repairPasswords($tenant->handle);
                $this->ensureCustomerAdminUser($tenant->handle);
            });
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    private function repairPasswords(string $handle): void
    {
        $broken = User::all()->filter(
            fn (User $u) => !Hash::check('password', $u->getAuthPassword())
        );

        foreach ($broken as $user) {
            $user->forceFill(['password' => 'password'])->save();
            $this->line("  [fixed password] {$user->email}");
        }

        if ($broken->isEmpty()) {
            $this->line("  [ok] all passwords are correctly hashed");
        }
    }

    private function ensureCustomerAdminUser(string $handle): void
    {
        // The customer email is stored on the Customer record in the SELLER's
        // tenant DB — not accessible here. Instead, look up the Central Tenant
        // to find the associated customer by provisioned_tenant_id.
        // Fallback: derive email from handle if no match is found.
        $customerEmail = $this->resolveCustomerEmail($handle);

        if (!$customerEmail) {
            $this->warn("  [skip] could not resolve customer email for handle '{$handle}'");
            return;
        }

        $adminRole = Role::where('slug', 'admin')->first();

        $user = User::firstOrCreate(
            ['email' => $customerEmail],
            [
                'name'      => ucwords(str_replace(['-', '_'], ' ', $handle)),
                'password'  => 'password',
                'is_active' => true,
            ]
        );

        if ($adminRole && !$user->roles->contains($adminRole->id)) {
            $user->roles()->attach($adminRole->id);
        }

        $this->line("  [ok] customer admin: {$user->email}");
    }

    /**
     * Walk the seller tenant DBs looking for a Customer whose
     * provisioned_tenant_id matches this handle. Falls back to
     * {handle}@example.com as a best-guess if nothing is found.
     */
    private function resolveCustomerEmail(string $handle): ?string
    {
        // Try every provisioned tenant DB looking for the matching Customer
        $allTenants = CentralTenant::all();

        foreach ($allTenants as $sellerTenant) {
            if ($sellerTenant->handle === $handle) {
                continue; // don't search inside the tenant we're repairing
            }

            $email = null;
            $sellerTenant->run(function () use ($handle, &$email) {
                if (!class_exists(Customer::class)) {
                    return;
                }
                $customer = Customer::where('provisioned_tenant_id', $handle)->first();
                if ($customer) {
                    $email = $customer->email;
                }
            });

            if ($email) {
                return $email;
            }
        }

        // Best-guess fallback
        return "{$handle}@example.com";
    }
}
