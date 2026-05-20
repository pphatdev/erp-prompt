<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('job_vacancies')) {
            Schema::create('job_vacancies', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('department_id')->nullable();
                $table->uuid('position_id')->nullable();

                $table->string('title');
                $table->text('description')->nullable();
                $table->string('location')->nullable();
                $table->string('employment_type')->default('full_time'); // full_time, part_time, contract, intern
                $table->integer('experience_min_years')->nullable();
                $table->integer('experience_max_years')->nullable();
                $table->decimal('salary_min', 19, 4)->nullable();
                $table->decimal('salary_max', 19, 4)->nullable();
                $table->integer('vacancies_count')->default(1);
                $table->string('status')->default('draft'); // draft, open, paused, closed, filled
                $table->date('posted_at')->nullable();
                $table->date('closes_at')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index('status');
                $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
                $table->foreign('position_id')->references('id')->on('positions')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('applications')) {
            Schema::create('applications', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('job_vacancy_id');
                $table->uuid('referrer_employee_id')->nullable();

                $table->string('applicant_name');
                $table->string('applicant_email');
                $table->string('applicant_phone')->nullable();
                $table->string('resume_path')->nullable();
                $table->text('cover_letter')->nullable();
                $table->decimal('expected_salary', 19, 4)->nullable();
                $table->text('notes')->nullable();
                $table->string('status')->default('applied'); // applied, screening, interview, offer, hired, rejected, withdrawn
                $table->timestamp('applied_at')->useCurrent();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index(['job_vacancy_id', 'status']);
                $table->foreign('job_vacancy_id')->references('id')->on('job_vacancies')->onDelete('cascade');
                $table->foreign('referrer_employee_id')->references('id')->on('employees')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
        Schema::dropIfExists('job_vacancies');
    }
};
