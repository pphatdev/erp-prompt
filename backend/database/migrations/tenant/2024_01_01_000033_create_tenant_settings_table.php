<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Configuration & Tenant Settings — key/value store, tenant-scoped.
 *
 * `key` follows a dotted convention ("branding.primary_color",
 * "locale.timezone", "notifications.email_enabled"). The `group` column is
 * derived from the first segment for filtered queries (admin UI tabs).
 *
 * `value` is jsonb so booleans, arrays, and strings share one column without
 * an awkward `value_string / value_json / value_bool` split.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('tenant_settings')) {
            Schema::create('tenant_settings', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('key', 120);
                $table->jsonb('value')->nullable();
                $table->string('group', 40);          // branding|locale|notifications|security|general
                $table->string('type', 20)->default('string'); // string|json|boolean|integer|color|url
                $table->string('label')->nullable();
                $table->text('description')->nullable();
                // Settings exposed without auth (e.g. logo URL on the login screen).
                $table->boolean('is_public')->default(false);

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->unique(['tenant_id', 'key'], 'tenant_settings_key_unique');
                $table->index(['tenant_id', 'group'], 'tenant_settings_group_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('tenant_settings');
    }
};
