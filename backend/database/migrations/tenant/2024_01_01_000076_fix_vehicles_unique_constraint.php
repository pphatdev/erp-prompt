<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert vehicles.registration_number from a single-column unique to a
 * composite `(registration_number, tenant_id)` unique — same pattern as
 * migration #62 already applied to users / employees / orders / customers /
 * applications / product_variants / warehouses / products. Vehicles was
 * omitted from that fix and now collides on re-seed when `tenant_id` on the
 * existing row doesn't match the current tenant scope.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('vehicles')) {
            return;
        }

        // Drop the old single-column unique whether it lives as a constraint or
        // a standalone index — IF EXISTS makes both safe on a fresh tenant DB.
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT IF EXISTS vehicles_registration_number_unique');
        DB::statement('DROP INDEX IF EXISTS vehicles_registration_number_unique');
        // Don't double-apply if a previous attempt landed it.
        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT IF EXISTS vehicles_reg_tenant_id_unique');

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unique(['registration_number', 'tenant_id'], 'vehicles_reg_tenant_id_unique');
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('vehicles')) {
            return;
        }

        DB::statement('ALTER TABLE vehicles DROP CONSTRAINT IF EXISTS vehicles_reg_tenant_id_unique');

        Schema::table('vehicles', function (Blueprint $table) {
            $table->unique('registration_number');
        });
    }
};
