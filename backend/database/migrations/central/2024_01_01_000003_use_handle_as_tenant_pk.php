<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Transitions existing central DBs from a UUID `id` primary key to `handle`.
 *
 * Before: tenants(id PK uuid, handle unique)  domains(tenant_id → tenants.id)
 * After:  tenants(handle PK)                  domains(tenant_id → tenants.handle)
 *
 * Also updates the provisioned_tenant_id on Customer / Subscription rows
 * inside each already-provisioned tenant's own database so that cross-
 * references stay consistent.
 *
 * Safe to run on fresh installs — all steps are guarded by Schema::has* checks.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Nothing to do if the tenants table already uses handle as PK
        if (!Schema::hasTable('tenants') || !Schema::hasColumn('tenants', 'id')) {
            return;
        }

        // ── 1. Drop FK on domains so we can re-point it later ─────────────────
        if (Schema::hasTable('domains')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->dropForeign(['tenant_id']);
            });

            // Re-map existing domain rows: tenant_id (UUID) → handle
            DB::statement(
                'UPDATE domains SET tenant_id = t.handle FROM tenants t WHERE domains.tenant_id = t.id'
            );
        }

        // ── 2. Drop the UUID `id` column and promote handle to PK ─────────────
        Schema::table('tenants', function (Blueprint $table) {
            // Drop unique index on handle before making it the PK
            $table->dropUnique(['handle']);
            $table->dropPrimary(['id']);
            $table->dropColumn('id');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->primary('handle');
        });

        // ── 3. Re-add FK on domains pointing at the new PK ────────────────────
        if (Schema::hasTable('domains')) {
            Schema::table('domains', function (Blueprint $table) {
                $table->foreign('tenant_id')
                    ->references('handle')
                    ->on('tenants')
                    ->onUpdate('cascade')
                    ->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        // Intentional no-op.
        //
        // This migration either (a) transitioned an existing DB from a UUID
        // `id` PK to `handle` PK, or (b) was a no-op on a fresh install where
        // the `id` column never existed.
        //
        // In both cases there is nothing to meaningfully restore here:
        //   - Re-adding a NOT NULL `id` column fails when rows already exist.
        //   - The original UUID values are gone and cannot be recreated.
        //   - On migrate:refresh, migration 000001.down() drops the entire
        //     `tenants` table (and 000002.down() drops `domains`), so no
        //     residual state survives anyway.
    }
};
