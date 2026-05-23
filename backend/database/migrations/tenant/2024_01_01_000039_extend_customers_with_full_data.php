<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hybrid Sales — Customer enrichment.
 *
 * Three new dimensions on a Customer record:
 *
 *   1. Classification — `customer_type` ('individual' | 'business' | 'tenant')
 *      drives the fulfillment side. When type=tenant, confirming a software
 *      subscription provisions a Central\Tenant and seeds branding from
 *      `brand_*` columns into the new tenant's `tenant_settings` table.
 *
 *   2. Structured data — tax ID, industry, website, structured billing
 *      address (city/state/postal/country), locale (currency, language,
 *      timezone), tier (standard|premium|enterprise), and an
 *      `account_manager_id` FK to the internal sales rep.
 *
 *   3. Tenant linkage — once provisioning runs, we store:
 *        - `tenant_handle`        the subdomain (must be tenant-unique)
 *        - `provisioned_tenant_id` Central tenants.id (no FK — different
 *                                  DB connection)
 *        - `provisioned_at`        first provisioning timestamp
 *      The `brand_primary_color` and `brand_logo_url` are intentionally
 *      mirrored here so they survive even if the customer's tenant DB is
 *      ever rebuilt — these are the seller's source of truth.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            // Classification
            if (!Schema::hasColumn('customers', 'customer_type')) {
                // individual | business | tenant
                $table->string('customer_type', 20)->default('business')->after('status');
            }
            if (!Schema::hasColumn('customers', 'external_code')) {
                $table->string('external_code', 60)->nullable()->after('customer_type');
            }
            if (!Schema::hasColumn('customers', 'tier')) {
                $table->string('tier', 20)->default('standard')->after('external_code'); // standard|premium|enterprise
            }

            // Business identifiers
            if (!Schema::hasColumn('customers', 'tax_id')) {
                $table->string('tax_id', 60)->nullable()->after('tier');
            }
            if (!Schema::hasColumn('customers', 'industry')) {
                $table->string('industry', 80)->nullable()->after('tax_id');
            }
            if (!Schema::hasColumn('customers', 'website')) {
                $table->string('website')->nullable()->after('industry');
            }

            // Structured billing address (free-form `address` column stays as
            // the legacy single-line fallback; new columns are the canonical
            // structured form going forward).
            if (!Schema::hasColumn('customers', 'billing_city')) {
                $table->string('billing_city', 120)->nullable()->after('address');
            }
            if (!Schema::hasColumn('customers', 'billing_state')) {
                $table->string('billing_state', 120)->nullable()->after('billing_city');
            }
            if (!Schema::hasColumn('customers', 'billing_postal_code')) {
                $table->string('billing_postal_code', 20)->nullable()->after('billing_state');
            }
            if (!Schema::hasColumn('customers', 'billing_country')) {
                $table->string('billing_country', 2)->nullable()->after('billing_postal_code'); // ISO 3166-1 alpha-2
            }

            // Locale preferences
            if (!Schema::hasColumn('customers', 'currency')) {
                $table->string('currency', 3)->default('USD')->after('billing_country'); // ISO 4217
            }
            if (!Schema::hasColumn('customers', 'language')) {
                $table->string('language', 8)->default('en')->after('currency'); // BCP-47
            }
            if (!Schema::hasColumn('customers', 'timezone')) {
                $table->string('timezone', 60)->default('UTC')->after('language');
            }

            // Account ownership
            if (!Schema::hasColumn('customers', 'account_manager_id')) {
                $table->uuid('account_manager_id')->nullable()->after('timezone');
            }

            // Notes
            if (!Schema::hasColumn('customers', 'notes')) {
                $table->text('notes')->nullable()->after('account_manager_id');
            }

            // Branding — seeds the customer's tenant_settings on provisioning.
            if (!Schema::hasColumn('customers', 'brand_primary_color')) {
                $table->string('brand_primary_color', 20)->nullable()->after('notes'); // RGB triple, e.g. "59 130 246"
            }
            if (!Schema::hasColumn('customers', 'brand_logo_url')) {
                $table->string('brand_logo_url')->nullable()->after('brand_primary_color');
            }

            // Tenant linkage (populated only when customer_type='tenant' and
            // a subscription has been confirmed).
            if (!Schema::hasColumn('customers', 'tenant_handle')) {
                // Per-tenant DB so unique here = unique within the seller's
                // customer book.
                $table->string('tenant_handle', 60)->nullable()->after('brand_logo_url');
            }
            if (!Schema::hasColumn('customers', 'provisioned_tenant_id')) {
                // Central\Tenant.id lives on a different connection — no FK.
                $table->string('provisioned_tenant_id')->nullable()->after('tenant_handle');
            }
            if (!Schema::hasColumn('customers', 'provisioned_at')) {
                $table->timestamp('provisioned_at')->nullable()->after('provisioned_tenant_id');
            }
        });

        // Indexes + FKs added separately so the column-presence checks above
        // are idempotent (Postgres rejects ALTER if the constraint already
        // exists).
        Schema::table('customers', function (Blueprint $table) {
            // Tenant-unique handle. If the column ended up null on existing
            // rows, multiple nulls are still allowed in Postgres — that's fine.
            $hasHandleUnique = collect(\DB::select(
                "SELECT 1 FROM information_schema.table_constraints
                 WHERE table_name = 'customers' AND constraint_name = 'customers_tenant_handle_unique'"
            ))->isNotEmpty();
            if (!$hasHandleUnique && Schema::hasColumn('customers', 'tenant_handle')) {
                $table->unique('tenant_handle', 'customers_tenant_handle_unique');
            }

            $hasTypeIdx = collect(\DB::select(
                "SELECT 1 FROM pg_indexes
                 WHERE tablename = 'customers' AND indexname = 'customers_type_idx'"
            ))->isNotEmpty();
            if (!$hasTypeIdx && Schema::hasColumn('customers', 'customer_type')) {
                $table->index(['tenant_id', 'customer_type'], 'customers_type_idx');
            }
        });

        // FK for account_manager_id (set null on user deletion so an archived
        // sales rep doesn't cascade-orphan their book).
        $hasFk = collect(\DB::select(
            "SELECT 1 FROM information_schema.table_constraints
             WHERE table_name = 'customers' AND constraint_name = 'customers_account_manager_id_foreign'"
        ))->isNotEmpty();
        if (!$hasFk && Schema::hasColumn('customers', 'account_manager_id') && Schema::hasTable('users')) {
            Schema::table('customers', function (Blueprint $table) {
                $table->foreign('account_manager_id')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('customers')) {
            return;
        }

        Schema::table('customers', function (Blueprint $table) {
            try { $table->dropForeign(['account_manager_id']); } catch (\Throwable $e) {}
            try { $table->dropUnique('customers_tenant_handle_unique'); } catch (\Throwable $e) {}
            try { $table->dropIndex('customers_type_idx'); } catch (\Throwable $e) {}

            foreach ([
                'customer_type', 'external_code', 'tier',
                'tax_id', 'industry', 'website',
                'billing_city', 'billing_state', 'billing_postal_code', 'billing_country',
                'currency', 'language', 'timezone',
                'account_manager_id', 'notes',
                'brand_primary_color', 'brand_logo_url',
                'tenant_handle', 'provisioned_tenant_id', 'provisioned_at',
            ] as $col) {
                if (Schema::hasColumn('customers', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
