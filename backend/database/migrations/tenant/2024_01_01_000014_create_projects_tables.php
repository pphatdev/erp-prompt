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
        // Projects Table
        if (!Schema::hasTable('projects')) {
            Schema::create('projects', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->text('description')->nullable();
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->decimal('budget', 15, 2)->default(0);
                $table->string('status')->default('planning'); // planning, active, on_hold, completed
                
                $table->uuid('manager_id')->nullable(); // Employee managing the project
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('tenant_id');
                $table->foreign('manager_id')->references('id')->on('employees')->onDelete('set null');
            });
        }

        // Tasks Table
        if (!Schema::hasTable('tasks')) {
            Schema::create('tasks', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('project_id');
                $table->string('title');
                $table->text('description')->nullable();
                $table->date('due_date')->nullable();
                $table->string('status')->default('todo'); // todo, in_progress, review, done
                $table->string('priority')->default('medium'); // low, medium, high, urgent
                
                $table->uuid('assignee_id')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->foreign('project_id')->references('id')->on('projects')->onDelete('cascade');
                $table->foreign('assignee_id')->references('id')->on('employees')->onDelete('set null');
                $table->index('tenant_id');
            });
        }

        // Timesheets Table
        if (!Schema::hasTable('timesheets')) {
            Schema::create('timesheets', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('task_id');
                $table->uuid('employee_id');
                $table->date('log_date');
                $table->decimal('hours_worked', 5, 2);
                $table->text('notes')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->foreign('task_id')->references('id')->on('tasks')->onDelete('cascade');
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->index('tenant_id');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('timesheets');
        Schema::dropIfExists('tasks');
        Schema::dropIfExists('projects');
    }
};
