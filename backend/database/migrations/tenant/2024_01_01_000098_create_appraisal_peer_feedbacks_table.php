<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * HRM Phase 4 (Performance) — 360-degree peer feedback.
 *
 * Each appraisal can collect feedback from any number of peer reviewers;
 * one row per (appraisal, reviewer) pair. The model never overlaps with
 * the existing `appraisals.reviewer_id` (which is the line manager) —
 * peers contribute alongside the manager evaluation.
 *
 * Aggregate is computed in PerformanceService::aggregatePeerFeedback
 * (average rating + count). `applyWeightedRating` blends the peer
 * average into the final score when the optional
 * `hrm.appraisal.peer_evaluation_weight` setting is configured.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('appraisal_peer_feedbacks')) {
            return;
        }

        Schema::create('appraisal_peer_feedbacks', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('appraisal_id');
            $table->uuid('reviewer_id'); // -> employees

            // Same scale as InterviewFeedback - decimal(3,1) so 0.0-5.0
            // rounds cleanly. Nullable while invited but not yet submitted.
            $table->decimal('rating', 3, 1)->nullable();
            $table->text('strengths')->nullable();
            $table->text('concerns')->nullable();
            $table->text('notes')->nullable();

            // Status of the peer review itself - invited / submitted /
            // declined - kept as a string so a tenant can add new states
            // through workflow_statuses if needed later.
            $table->string('status', 32)->default('invited');
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('submitted_at')->nullable();

            $table->string('tenant_id');
            $table->timestamps();
            $table->softDeletes();

            $table->index('tenant_id');
            $table->index(['appraisal_id', 'status']);
            $table->unique(['appraisal_id', 'reviewer_id'], 'appraisal_peer_feedback_unique');
            $table->foreign('appraisal_id')->references('id')->on('appraisals')->onDelete('cascade');
            $table->foreign('reviewer_id')->references('id')->on('employees')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appraisal_peer_feedbacks');
    }
};
