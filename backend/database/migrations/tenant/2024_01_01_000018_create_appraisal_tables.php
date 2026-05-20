<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('appraisals')) {
            Schema::create('appraisals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('employee_id');
                $table->uuid('reviewer_id')->nullable();

                $table->string('cycle');                 // e.g. "2026-Q2", "2026-Annual"
                $table->date('period_start');
                $table->date('period_end');

                $table->decimal('overall_rating', 3, 2)->nullable(); // 0.00 – 5.00
                $table->text('strengths')->nullable();
                $table->text('improvements')->nullable();
                $table->json('goals')->nullable();      // [{title, status, due}]

                $table->string('status')->default('draft'); // draft, submitted, reviewed, closed
                $table->timestamp('submitted_at')->nullable();
                $table->timestamp('reviewed_at')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index(['employee_id', 'cycle']);
                $table->foreign('employee_id')->references('id')->on('employees')->onDelete('cascade');
                $table->foreign('reviewer_id')->references('id')->on('employees')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisals');
    }
};
