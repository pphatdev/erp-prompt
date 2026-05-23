<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('modules')) {
            return;
        }

        Schema::create('modules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('slug', 100);
            $table->string('prefix', 20);
            $table->string('name');
            $table->string('icon', 100)->nullable();
            $table->text('description')->nullable();
            $table->string('route', 255)->nullable();
            // Sidebar group: 'main' | 'self-service' | 'apps'
            $table->string('group', 30)->default('apps');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            // Core modules are always visible regardless of subscription.
            $table->boolean('is_core')->default(false);
            // Null = top-level module; set = sub-item nested under parent.
            $table->uuid('parent_id')->nullable();
            $table->string('tenant_id');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['tenant_id', 'is_active']);
            $table->unique(['slug', 'tenant_id']);
        });

        // Self-referential FK must be added after the table (and its PK) exists.
        Schema::table('modules', function (Blueprint $table) {
            $table->foreign('parent_id')->references('id')->on('modules')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('modules', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
        });
        Schema::dropIfExists('modules');
    }
};
