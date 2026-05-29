<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('employee_appointments')) {
            return;
        }

        Schema::create('employee_appointments', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('application_id');
            $table->uuid('employee_id')->nullable();
            $table->uuid('submitted_by')->nullable();

            // Candidate snapshot at request time — preserved even if the
            // underlying application is edited before approval lands.
            $table->string('first_name');
            $table->string('last_name');
            $table->string('email');
            $table->string('phone')->nullable();

            // Appointment overrides — HR can deviate from the vacancy defaults.
            $table->uuid('department_id')->nullable();
            $table->uuid('position_id')->nullable();
            $table->uuid('manager_id')->nullable();
            $table->date('start_date');
            // Holds Laravel-encrypted ciphertext (EncryptedWithFallback cast
            // on the model). Must be `text`, not `decimal` — the ciphertext
            // blob is far longer than 15 digits.
            $table->text('base_salary')->nullable();
            $table->string('employment_type')->default('full_time');
            $table->text('notes')->nullable();

            $table->string('status')->default('pending');
            $table->timestamp('processed_at')->nullable();

            $table->string('tenant_id');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['application_id', 'status']);
            $table->foreign('application_id')->references('id')->on('applications')->onDelete('cascade');
            $table->foreign('employee_id')->references('id')->on('employees')->onDelete('set null');
            $table->foreign('submitted_by')->references('id')->on('users')->onDelete('set null');
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
            $table->foreign('position_id')->references('id')->on('positions')->onDelete('set null');
            $table->foreign('manager_id')->references('id')->on('employees')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_appointments');
    }
};
