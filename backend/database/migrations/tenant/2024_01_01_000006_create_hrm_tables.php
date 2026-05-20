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
        // Departments Table
        if (!Schema::hasTable('departments')) {
            Schema::create('departments', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('code')->unique();
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('tenant_id');
            });
        }

        // Positions Table
        if (!Schema::hasTable('positions')) {
            Schema::create('positions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title');
                $table->string('level')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->index('tenant_id');
            });
        }

        // Employees Table
        if (!Schema::hasTable('employees')) {
            Schema::create('employees', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('user_id')->nullable(); // Link to auth user
                $table->uuid('department_id')->nullable();
                $table->uuid('position_id')->nullable();
                
                $table->string('employee_id')->unique();
                $table->string('first_name');
                $table->string('last_name');
                $table->string('email')->unique();
                $table->string('phone')->nullable();
                
                $table->date('hired_at')->nullable();
                $table->decimal('base_salary', 15, 2)->nullable(); // Should be encrypted in production
                $table->string('status')->default('active'); // active, on_leave, terminated
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('tenant_id');
                $table->foreign('user_id')->references('id')->on('users')->onDelete('set null');
                $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
                $table->foreign('position_id')->references('id')->on('positions')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employees');
        Schema::dropIfExists('positions');
        Schema::dropIfExists('departments');
    }
};
