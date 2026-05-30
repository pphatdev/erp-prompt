<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Convert `assets.location_id` from uuid -> varchar.
 *
 * The original create migration (#12) declared it as a UUID with the comment
 * "Branch/Dept link (generic string for now)" — but the spec (and downstream
 * code: seeders, validation, frontend label) treats it as a free-text physical
 * location key like `"HQ-Floor3-Desk12"`. Inserting such a value against the
 * uuid column raises `SQLSTATE[22P02]: Invalid text representation`.
 *
 * Postgres requires an explicit USING clause when widening between uuid and
 * varchar; valid uuids round-trip cleanly because their canonical text form
 * is already a string.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('assets') || !Schema::hasColumn('assets', 'location_id')) {
            return;
        }

        // Postgres-only conversion — every tenant DB on this stack is pgsql.
        DB::statement('ALTER TABLE assets ALTER COLUMN location_id TYPE VARCHAR(255) USING location_id::text');
    }

    public function down(): void
    {
        if (!Schema::hasTable('assets') || !Schema::hasColumn('assets', 'location_id')) {
            return;
        }

        // Reverse: free-text strings that aren't valid uuids would break the
        // cast, so down() first nulls non-uuid rows, then converts. Strictly a
        // best-effort rollback — anyone running it must accept the data loss.
        DB::statement(
            "UPDATE assets SET location_id = NULL WHERE location_id IS NOT NULL AND location_id !~ '^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}$'"
        );
        DB::statement('ALTER TABLE assets ALTER COLUMN location_id TYPE uuid USING location_id::uuid');
    }
};
