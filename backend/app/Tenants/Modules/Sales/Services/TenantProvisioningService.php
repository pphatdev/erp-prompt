<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Services;

use App\Models\Central\Tenant as CentralTenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\Setting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class TenantProvisioningService
{
    /**
     * Provision a Stancl tenant for a tenant-type customer.
     *
     * Idempotent: if the customer is already provisioned, mirrors the existing
     * tenant reference onto $sub (when given) and returns.
     *
     * @param  Customer      $customer  The tenant-type customer to provision.
     * @param  object|null   $sub       Optional Subscription row to update with the tenant ref.
     */
    public function provisionForCustomer(Customer $customer, ?object $sub = null): void
    {
        if (!$customer->isTenantCustomer()) {
            return;
        }

        if ($customer->isProvisioned()) {
            if ($sub) {
                $sub->update([
                    'provisioned_tenant_id' => $customer->provisioned_tenant_id,
                    'provisioned_at'        => $customer->provisioned_at,
                ]);

                // Seed subscription products and update module visibility.
                $subItems = $sub->loadMissing('items')->items;
                if ($subItems->isNotEmpty()) {
                    $productIds   = $subItems->pluck('product_id')->filter()->unique()->values();
                    $entitledSlugs = \App\Models\Tenant\Module::whereHas(
                        'products', fn ($q) => $q->whereIn('products.id', $productIds->toArray())
                    )->pluck('slug');

                    $centralTenant = CentralTenant::find($customer->provisioned_tenant_id);
                    if ($centralTenant) {
                        $centralTenant->run(function () use ($subItems, $entitledSlugs) {
                            $this->seedSubscriptionProducts($subItems);
                            if ($entitledSlugs->isNotEmpty()) {
                                $allEntitled = $this->expandEntitledSlugs($entitledSlugs);
                                \App\Models\Tenant\Module::where('is_core', false)
                                    ->whereNotIn('slug', $allEntitled->toArray())
                                    ->update(['is_active' => false]);
                                \App\Models\Tenant\Module::where('is_core', false)
                                    ->whereIn('slug', $allEntitled->toArray())
                                    ->update(['is_active' => true]);
                            }
                        });
                    }
                }
            }
            Log::info('Customer already provisioned — mirrored tenant ref onto subscription.', [
                'customer_id' => $customer->id,
                'tenant_id'   => $customer->provisioned_tenant_id,
            ]);
            return;
        }

        $handle = $customer->tenant_handle ?: $this->deriveHandle($customer);

        $this->provision($customer, $sub, $handle);
    }

    private function provision(Customer $customer, ?object $sub, string $handle): void
    {
        $systemDomain = config('platform.system_domain', 'localhost');
        $subdomain    = "{$handle}.{$systemDomain}";

        // ── 1. Create the Central Tenant row (central DB connection) ──────────
        // firstOrCreate guards against a previous partial failure where the
        // CentralTenant row was written but the customer.provisioned_tenant_id
        // update never committed (e.g. an exception later in provision()).
        $centralTenant = CentralTenant::firstOrCreate(
            ['handle' => $handle],
            ['name'   => $customer->company_name ?: $customer->name]
        );

        // ── 2. Register the subdomain ─────────────────────────────────────────
        if (!$centralTenant->domains()->where('domain', $subdomain)->exists()) {
            $centralTenant->domains()->create(['domain' => $subdomain]);
        }

        $now           = now();
        $tenantKey     = $centralTenant->getKey();
        $brandColor    = $customer->brand_primary_color;
        $brandLogoUrl  = $customer->brand_logo_url;
        $customerEmail = $customer->email;
        $customerName  = $customer->name;

        // Pre-load subscription items in the seller's DB context so the
        // collection is available inside $centralTenant->run() (customer DB).
        $subItems = $sub ? $sub->loadMissing('items')->items : collect();

        // Resolve entitled module slugs (via product → module pivot) in the
        // seller's DB context before switching into the customer's tenant.
        $entitledSlugs = collect();
        if ($subItems->isNotEmpty()) {
            $productIds = $subItems->pluck('product_id')->filter()->unique()->values();
            $entitledSlugs = \App\Models\Tenant\Module::whereHas(
                'products', fn ($q) => $q->whereIn('products.id', $productIds->toArray())
            )->pluck('slug');
        }

        // ── 3. Update seller's DB: customer + optional subscription ───────────
        DB::transaction(function () use ($customer, $sub, $tenantKey, $handle, $now) {
            $customer->update([
                'provisioned_tenant_id' => $tenantKey,
                'provisioned_at'        => $now,
                'tenant_handle'         => $handle,
            ]);
            if ($sub) {
                $sub->update([
                    'provisioned_tenant_id' => $tenantKey,
                    'provisioned_at'        => $now,
                    'status'                => 'active',
                ]);
            }
        });

        // ── 4 & 5. Migrate + seed the new tenant's own database ───────────────
        $centralTenant->run(function () use ($brandColor, $brandLogoUrl, $customerEmail, $customerName, $subItems, $entitledSlugs) {
            Artisan::call('migrate', [
                '--path'     => database_path('migrations/tenant'),
                '--realpath' => true,
                '--force'    => true,
            ]);

            Artisan::call('db:seed', [
                '--class' => 'Database\\Seeders\\TenantDatabaseSeeder',
                '--force' => true,
            ]);

            // ── 6. Create admin user from customer email ──────────────────────
            $adminRole   = \App\Models\Tenant\Role::where('slug', 'admin')->first();
            $tenantAdmin = \App\Models\Tenant\User::firstOrCreate(
                ['email' => $customerEmail],
                ['name' => $customerName, 'password' => 'password', 'is_active' => true]
            );
            if ($adminRole && !$tenantAdmin->roles->contains($adminRole->id)) {
                $tenantAdmin->roles()->attach($adminRole->id);
            }

            if ($brandColor) {
                Setting::updateOrCreate(
                    ['key' => 'branding.primary_color'],
                    ['value' => $brandColor, 'group' => 'branding', 'type' => 'color',
                     'label' => 'Primary accent color', 'is_public' => true]
                );
            }

            if ($brandLogoUrl) {
                Setting::updateOrCreate(
                    ['key' => 'branding.logo_url'],
                    ['value' => $brandLogoUrl, 'group' => 'branding', 'type' => 'url',
                     'label' => 'Logo URL', 'is_public' => true]
                );
            }

            // ── 7. Seed subscribed software products ──────────────────────────
            if ($subItems->isNotEmpty()) {
                $this->seedSubscriptionProducts($subItems);
            }

            // ── 8. Restrict module visibility to entitled modules + core ───────
            // TenantDatabaseSeeder seeds all modules as active=true. Deactivate every
            // non-core module that isn't linked to a subscribed product, and cascade
            // to children so child nav items are also hidden/shown correctly.
            if ($entitledSlugs->isNotEmpty()) {
                $allEntitled = $this->expandEntitledSlugs($entitledSlugs);
                \App\Models\Tenant\Module::where('is_core', false)
                    ->whereNotIn('slug', $allEntitled->toArray())
                    ->update(['is_active' => false]);
                \App\Models\Tenant\Module::where('is_core', false)
                    ->whereIn('slug', $allEntitled->toArray())
                    ->update(['is_active' => true]);
            }
        });

        Log::info('Tenant provisioned.', [
            'tenant_id' => $tenantKey,
            'handle'    => $handle,
            'subdomain' => $subdomain,
        ]);
    }

    /**
     * Expand a set of entitled parent slugs to also include their children.
     * Must be called inside $centralTenant->run() so the DB context is the
     * customer's tenant (where the modules table lives).
     */
    private function expandEntitledSlugs(Collection $slugs): Collection
    {
        $childSlugs = \App\Models\Tenant\Module::whereIn('slug', $slugs->toArray())
            ->with('children')
            ->get()
            ->flatMap(fn ($m) => $m->children->pluck('slug'));

        return $slugs->merge($childSlugs)->unique()->values();
    }

    /**
     * Create or update Product rows in the current tenant DB from subscription
     * snapshot items. Called inside $centralTenant->run() so the DB context is
     * already switched to the customer's tenant.
     */
    private function seedSubscriptionProducts(Collection $items): void
    {
        foreach ($items as $item) {
            $sku = $item->variant_sku ?: Str::slug((string) $item->product_name, '-');

            Product::updateOrCreate(
                ['sku' => $sku],
                [
                    'name'         => $item->product_name,
                    'product_type' => Product::TYPE_SOFTWARE,
                    'unit_price'   => $item->unit_price,
                    'is_active'    => true,
                ]
            );
        }
    }

    /**
     * Derive a handle from the customer's name when none was set.
     * Slugifies, caps at 30 chars, appends a 4-char suffix to reduce collisions.
     */
    private function deriveHandle(Customer $customer): string
    {
        $base = Str::slug($customer->company_name ?: $customer->name, '-');
        $base = Str::substr($base, 0, 30) ?: 'tenant';

        return $base . '-' . Str::lower(Str::random(4));
    }
}
