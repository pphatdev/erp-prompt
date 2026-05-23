<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Roles Table
        if (!Schema::hasTable('roles')) {
            Schema::create('roles', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug');
                $table->string('description')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->unique(['tenant_id', 'slug']);
                $table->index('tenant_id');
            });
        }

        // Permissions Table
        if (!Schema::hasTable('permissions')) {
            Schema::create('permissions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('slug'); // Pattern: module.feature.action
                $table->string('module');
                $table->string('feature');
                $table->string('action');
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->index(['module', 'feature', 'action']);
                $table->unique(['tenant_id', 'slug']);
                $table->index('tenant_id');
            });
        }

        // Role-Permission Pivot
        if (!Schema::hasTable('role_has_permissions')) {
            Schema::create('role_has_permissions', function (Blueprint $table) {
                $table->uuid('role_id');
                $table->uuid('permission_id');
                
                $table->primary(['role_id', 'permission_id']);
                
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
                $table->foreign('permission_id')->references('id')->on('permissions')->onDelete('cascade');
            });
        }

        // User-Role Pivot
        if (!Schema::hasTable('user_has_roles')) {
            Schema::create('user_has_roles', function (Blueprint $table) {
                $table->uuid('user_id');
                $table->uuid('role_id');
                
                $table->primary(['user_id', 'role_id']);
                
                $table->foreign('user_id')->references('id')->on('users')->onDelete('cascade');
                $table->foreign('role_id')->references('id')->on('roles')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('user_has_roles');
        Schema::dropIfExists('role_has_permissions');
        Schema::dropIfExists('permissions');
        Schema::dropIfExists('roles');
    }
};
