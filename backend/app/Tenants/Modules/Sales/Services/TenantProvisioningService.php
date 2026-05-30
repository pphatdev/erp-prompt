<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Services;

use App\Models\Central\Tenant as CentralTenant;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Product;
use App\Models\Tenant\Setting;
use App\Models\Tenant\Subscription;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

                // Seed any new subscription products into the existing tenant
                // DB so they show up in the customer's catalog.
                $subItems = $sub->loadMissing('items')->items;
                if ($subItems->isNotEmpty()) {
                    $centralTenant = CentralTenant::find($customer->provisioned_tenant_id);
                    if ($centralTenant) {
                        $centralTenant->run(fn () => $this->seedSubscriptionProducts($subItems));
                    }
                }
            }

            // Always re-derive module visibility from the customer's current
            // active subscriptions. Calling this on the already-provisioned
            // path covers the case where one of N subscriptions was added /
            // upgraded / cancelled / expired since the last sync.
            $this->syncModuleEntitlement($customer->fresh());

            Log::info('Customer already provisioned — mirrored tenant ref onto subscription and re-synced module entitlement.', [
                'customer_id' => $customer->id,
                'tenant_id'   => $customer->provisioned_tenant_id,
            ]);
            return;
        }

        $handle = $customer->tenant_handle ?: $this->deriveHandle($customer);

        $this->provision($customer, $sub, $handle);

        // Re-derive module visibility off the live subscription set as the
        // last step of provisioning. The inline step 8 inside provision()
        // covers the most common case (single subscription, single product);
        // calling syncModuleEntitlement here is the source-of-truth pass
        // that also catches any pre-existing active subscriptions on this
        // customer that didn't ride in via $sub.
        $this->syncModuleEntitlement($customer->fresh());
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
        // Normalize email (trim + lowercase) so case differences between the
        // customer record and what the user types on the login form can't
        // produce a "user not found" 401. Customer.email is nullable since
        // migration 55, so we must also tolerate empty values without crashing.
        $customerEmail = $customer->email ? Str::lower(trim($customer->email)) : null;
        $customerName  = $customer->name;

        // Pre-load subscription items in the seller's DB context so the
        // collection is available inside $centralTenant->run() (customer DB).
        $subItems = $sub ? $sub->loadMissing('items')->items : collect();

        // Resolve entitled module slugs (via product → module pivot) in the
        // seller's DB context before switching into the customer's tenant.
        // `$productIds` is kept around so we can log it from inside the
        // tenant context when the lookup returns empty.
        $productIds = collect();
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
        $centralTenant->run(function () use ($brandColor, $brandLogoUrl, $customerEmail, $customerName, $subItems, $entitledSlugs, $productIds) {
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
            // Guard against a null/empty customer.email — the column is nullable
            // and a User row with email=null is unreachable from the login form.
            // If a customer was created without an email, the seeded
            // admin@example.com user already exists in this tenant DB and can
            // be used as a fallback admin instead.
            if ($customerEmail) {
                $adminRole   = \App\Models\Tenant\Role::where('slug', 'admin')->first();
                $tenantAdmin = \App\Models\Tenant\User::firstOrCreate(
                    ['email' => $customerEmail],
                    ['name' => $customerName, 'password' => 'password', 'is_active' => true]
                );

                // Self-heal pattern from TenantDatabaseSeeder: if a previous
                // partial provisioning (or any observer in the lifecycle) left
                // the password as bcrypt(bcrypt('password')), Hash::check fails
                // forever and login returns 401 invalid_grant. forceFill
                // bypasses fillable but still triggers the `hashed` cast, so
                // exactly one hash is applied on save.
                if (!Hash::check('password', $tenantAdmin->getAuthPassword())) {
                    $tenantAdmin->forceFill(['password' => 'password'])->save();
                }

                // Ensure the admin user is active even if a stale row existed.
                if (!$tenantAdmin->is_active) {
                    $tenantAdmin->forceFill(['is_active' => true])->save();
                }

                if ($adminRole && !$tenantAdmin->roles->contains($adminRole->id)) {
                    $tenantAdmin->roles()->attach($adminRole->id);
                }

                Log::info('Tenant admin user provisioned.', [
                    'email'  => $customerEmail,
                    'name'   => $customerName,
                    'role'   => $adminRole?->slug,
                ]);
            } else {
                Log::warning('Tenant provisioned WITHOUT an admin user — customer.email was empty. Use the seeded admin@example.com / password account to bootstrap, then add real users.', [
                    'customer_name' => $customerName,
                ]);
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
            // TenantDatabaseSeeder seeds every module with is_active=true, so the
            // customer's tenant inherits ALL systems unless we deactivate the
            // ones their subscription doesn't pay for.
            //
            // Fail-closed: run the restriction whenever there's a subscription,
            // even when no entitled module slugs were resolved. The previous
            // fail-open guard (`if ($entitledSlugs->isNotEmpty())`) silently
            // leaked every module to subscribed tenants whose products had no
            // product↔module links yet — the symptom was "customer purchased
            // only Sales but sees all systems".
            if ($subItems->isNotEmpty()) {
                $allEntitled = $entitledSlugs->isNotEmpty()
                    ? $this->expandEntitledSlugs($entitledSlugs)
                    : collect();

                // Deactivate every non-core module that the subscription doesn't entitle.
                \App\Models\Tenant\Module::where('is_core', false)
                    ->when($allEntitled->isNotEmpty(),
                        fn ($q) => $q->whereNotIn('slug', $allEntitled->toArray()))
                    ->update(['is_active' => false]);

                if ($allEntitled->isNotEmpty()) {
                    // Activate the entitled set so a re-provision after the seller
                    // adds a product→module link doesn't leave old hides in place.
                    \App\Models\Tenant\Module::where('is_core', false)
                        ->whereIn('slug', $allEntitled->toArray())
                        ->update(['is_active' => true]);
                } else {
                    Log::warning(
                        'Tenant provisioned with subscription but ZERO entitled modules — only core modules will be visible. Link products to modules on the seller-side Inventory product editor to grant access.',
                        ['product_ids' => $productIds->toArray()]
                    );
                }
            }
        });

        Log::info('Tenant provisioned.', [
            'tenant_id' => $tenantKey,
            'handle'    => $handle,
            'subdomain' => $subdomain,
        ]);
    }

    /**
     * Re-derive the customer's tenant module visibility from their CURRENT
     * active subscriptions.
     *
     * Call this anywhere a subscription's lifecycle state could shift — at a
     * minimum SubscriptionService::cancel / renew / changePlan /
     * expireDueSubscriptions. Idempotent and safe to call repeatedly; the
     * inner update statements no-op when the desired state already matches.
     *
     * Behavior:
     *   - Returns silently if the customer isn't a tenant-type or hasn't been
     *     provisioned (no tenant DB to sync into).
     *   - Resolves the UNION of all `active` subscription items' products and
     *     maps them to module slugs via the `product_modules` pivot in the
     *     seller's DB.
     *   - Switches into the customer's tenant via `$centralTenant->run()` and
     *     activates the entitled module slugs (plus their children) while
     *     deactivating every other non-core module.
     *   - When the customer has no active subscription at all, every non-core
     *     module is deactivated — they keep only the core surfaces (Settings,
     *     Roles, etc.) until they renew.
     */
    public function syncModuleEntitlement(Customer $customer): void
    {
        if (!$customer->isTenantCustomer() || !$customer->isProvisioned()) {
            return;
        }

        $centralTenant = CentralTenant::find($customer->provisioned_tenant_id);
        if (!$centralTenant) {
            return;
        }

        // Compute (in the seller's DB context) the slug set the customer's
        // tenant should expose: parents linked to any product on an active
        // subscription, plus those parents' children expanded inside the
        // customer's DB. `entitledSlugs` is empty when the customer has zero
        // active subscriptions OR none of the subscribed products are linked
        // to a module.
        $activeProductIds = Subscription::query()
            ->where('customer_id', $customer->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->with('items')
            ->get()
            ->flatMap(fn (Subscription $s) => $s->items->pluck('product_id'))
            ->filter()
            ->unique()
            ->values();

        $entitledSlugs = $activeProductIds->isEmpty()
            ? collect()
            : \App\Models\Tenant\Module::whereHas(
                'products',
                fn ($q) => $q->whereIn('products.id', $activeProductIds->toArray())
            )->pluck('slug');

        $centralTenant->run(function () use ($entitledSlugs, $activeProductIds) {
            $allEntitled = $entitledSlugs->isNotEmpty()
                ? $this->expandEntitledSlugs($entitledSlugs)
                : collect();

            // Deactivate every non-core module not in the entitled set
            // (or every non-core module when entitled is empty).
            \App\Models\Tenant\Module::where('is_core', false)
                ->when($allEntitled->isNotEmpty(),
                    fn ($q) => $q->whereNotIn('slug', $allEntitled->toArray()))
                ->update(['is_active' => false]);

            if ($allEntitled->isNotEmpty()) {
                \App\Models\Tenant\Module::where('is_core', false)
                    ->whereIn('slug', $allEntitled->toArray())
                    ->update(['is_active' => true]);
            } elseif ($activeProductIds->isNotEmpty()) {
                Log::warning(
                    'Customer tenant has active subscriptions but ZERO entitled modules — products are not linked to modules on the seller side. Link products to modules on the Inventory product editor.',
                    ['product_ids' => $activeProductIds->toArray()]
                );
            }
        });
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
