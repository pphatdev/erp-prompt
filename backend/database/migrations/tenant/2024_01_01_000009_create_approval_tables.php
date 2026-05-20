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
        // Approval Workflows (The Template)
        if (!Schema::hasTable('approval_workflows')) {
            Schema::create('approval_workflows', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->string('module'); // hrm, sales, inventory
                $table->string('type'); // leave, order, expense
                
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();
                
                $table->index('tenant_id');
            });
        }

        // Approval Levels (The Steps)
        if (!Schema::hasTable('approval_levels')) {
            Schema::create('approval_levels', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('workflow_id');
                $table->integer('sequence');
                $table->string('approver_role')->nullable(); // Role required to approve
                $table->uuid('approver_id')->nullable(); // Specific user required to approve
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->foreign('workflow_id')->references('id')->on('approval_workflows')->onDelete('cascade');
            });
        }

        // Approval Requests (The Instance)
        if (!Schema::hasTable('approval_requests')) {
            Schema::create('approval_requests', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('workflow_id');
                $table->uuid('requester_id');
                $table->uuid('current_level_id')->nullable();
                
                // Polymorphic link to the source record (e.g., Leave ID)
                $table->string('requestable_type');
                $table->uuid('requestable_id');
                
                $table->string('status')->default('pending'); // pending, approved, rejected, sent_back
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->foreign('workflow_id')->references('id')->on('approval_workflows')->onDelete('cascade');
                $table->foreign('requester_id')->references('id')->on('users')->onDelete('cascade');
                $table->index('tenant_id');
                $table->index(['requestable_type', 'requestable_id']);
            });
        }

        // Approval History (The Audit)
        if (!Schema::hasTable('approval_history')) {
            Schema::create('approval_history', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('approval_request_id');
                $table->uuid('approver_id');
                $table->string('action'); // approved, rejected, sent_back
                $table->text('comment')->nullable();
                
                $table->string('tenant_id');
                $table->timestamps();
                
                $table->foreign('approval_request_id')->references('id')->on('approval_requests')->onDelete('cascade');
                $table->foreign('approver_id')->references('id')->on('users')->onDelete('cascade');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('approval_history');
        Schema::dropIfExists('approval_requests');
        Schema::dropIfExists('approval_levels');
        Schema::dropIfExists('approval_workflows');
    }
};
