<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('quizzes')) {
            Schema::create('quizzes', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('title', 160);
                $table->text('description')->nullable();
                $table->unsignedSmallInteger('time_limit_minutes')->nullable();
                $table->decimal('pass_score', 5, 2)->nullable();
                $table->string('status', 40)->default('draft'); // draft, published, archived

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index(['tenant_id', 'status']);
            });
        }

        if (!Schema::hasTable('quiz_questions')) {
            Schema::create('quiz_questions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('quiz_id');
                $table->unsignedInteger('sequence')->default(0);
                $table->text('prompt');
                $table->string('question_type', 40); // single_choice, multiple_choice, short_text
                $table->json('options')->nullable(); // [{ "key": "a", "text": "..." }]
                $table->text('correct_answer')->nullable(); // encrypted JSON: ["a"] or "text"
                $table->unsignedSmallInteger('points')->default(1);

                $table->string('tenant_id');
                $table->timestamps();

                $table->index('tenant_id');
                $table->index(['quiz_id', 'sequence']);
                $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
            });
        }

        if (!Schema::hasTable('quiz_attempts')) {
            Schema::create('quiz_attempts', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('quiz_id');
                $table->uuid('application_id')->nullable();
                $table->string('candidate_email', 160);
                $table->string('candidate_name', 160)->nullable();

                // Raw token is never persisted — only its SHA-256 hash. The
                // tenant_id + token_hash composite is unique, but the hash
                // alone is unique enough (SHA-256 collision space).
                $table->string('secure_token_hash', 128);

                $table->timestamp('invited_at');
                $table->timestamp('expires_at');
                $table->timestamp('started_at')->nullable();
                $table->timestamp('submitted_at')->nullable();

                $table->string('status', 40)->default('invited'); // invited, in_progress, completed, expired, abandoned

                $table->json('answers')->nullable(); // { questionId: ["a","b"] | "text" }
                $table->decimal('score', 5, 2)->nullable();
                $table->boolean('passed')->nullable();

                $table->string('tenant_id');
                $table->timestamps();

                $table->index('tenant_id');
                $table->index(['quiz_id', 'status']);
                $table->index('application_id');
                $table->unique('secure_token_hash');
                $table->foreign('quiz_id')->references('id')->on('quizzes')->onDelete('cascade');
                $table->foreign('application_id')->references('id')->on('applications')->onDelete('set null');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('quiz_attempts');
        Schema::dropIfExists('quiz_questions');
        Schema::dropIfExists('quizzes');
    }
};
