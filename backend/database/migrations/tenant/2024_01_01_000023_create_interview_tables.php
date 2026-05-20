<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('interviews')) {
            Schema::create('interviews', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('application_id');
                $table->uuid('quiz_attempt_id')->nullable(); // optional link to a scored quiz
                $table->string('title', 160);
                $table->string('round', 40)->nullable(); // phone, technical, panel, executive
                $table->timestamp('scheduled_at');
                $table->unsignedSmallInteger('duration_minutes')->default(60);
                $table->string('mode', 20)->default('onsite'); // onsite, video, phone
                $table->string('location', 255)->nullable(); // address or meeting URL
                $table->string('status', 40)->default('scheduled'); // scheduled, completed, cancelled, no_show
                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index(['application_id', 'scheduled_at']);
                $table->foreign('application_id')->references('id')->on('applications')->onDelete('cascade');
                $table->foreign('quiz_attempt_id')->references('id')->on('quiz_attempts')->onDelete('set null');
            });
        }

        if (!Schema::hasTable('interview_feedback')) {
            Schema::create('interview_feedback', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('interview_id');
                $table->uuid('interviewer_id'); // → employees
                $table->decimal('rating', 3, 1)->nullable(); // 0.0 – 5.0
                $table->text('strengths')->nullable();
                $table->text('concerns')->nullable();
                $table->string('recommendation', 20)->nullable(); // strong_hire, hire, no_hire, strong_no_hire
                $table->timestamp('submitted_at');

                $table->string('tenant_id');
                $table->timestamps();

                $table->index('tenant_id');
                $table->unique(['interview_id', 'interviewer_id']);
                $table->foreign('interview_id')->references('id')->on('interviews')->onDelete('cascade');
                $table->foreign('interviewer_id')->references('id')->on('employees')->onDelete('cascade');
            });
        }

        // M:N interviewers per interview (separate from feedback table — an
        // interviewer can be assigned without having submitted feedback yet).
        if (!Schema::hasTable('interview_interviewer')) {
            Schema::create('interview_interviewer', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('interview_id');
                $table->uuid('interviewer_id');

                $table->string('tenant_id');
                $table->timestamps();

                $table->unique(['interview_id', 'interviewer_id']);
                $table->foreign('interview_id')->references('id')->on('interviews')->onDelete('cascade');
                $table->foreign('interviewer_id')->references('id')->on('employees')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_interviewer');
        Schema::dropIfExists('interview_feedback');
        Schema::dropIfExists('interviews');
    }
};
