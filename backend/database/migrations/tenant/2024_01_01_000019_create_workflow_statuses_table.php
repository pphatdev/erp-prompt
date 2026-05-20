<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_statuses')) {
            Schema::create('workflow_statuses', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('module', 60);        // e.g. hrm.application
                $table->string('key', 40);           // e.g. applied
                $table->string('label', 80);
                $table->string('color', 20)->default('secondary');  // CSS-token name
                $table->string('icon', 60)->nullable();             // Tabler icon name
                $table->integer('sequence')->default(0);
                $table->boolean('is_initial')->default(false);      // status used when a new record is created
                $table->boolean('is_terminal')->default(false);     // no outgoing transitions
                $table->json('allowed_next')->nullable();           // [string]
                $table->json('metadata')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index(['module', 'sequence']);
                $table->unique(['tenant_id', 'module', 'key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_statuses');
    }
};
