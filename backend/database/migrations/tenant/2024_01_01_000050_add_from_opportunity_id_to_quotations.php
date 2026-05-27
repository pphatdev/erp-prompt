<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Quotations get a nullable link back to the originating Opportunity so the
 * Win flow can update the linked Lead and the audit trail can trace the
 * Quotation back to the prospect's B2B Product Schedule snapshot.
 *
 * Also adds the won/lost actor + timestamp columns and a free-form
 * `loss_reason` column distinct from the legacy `cancel_reason`. The legacy
 * `confirmed_*` / `cancelled_*` columns are kept (the remap migration moved
 * data into `status`; columns remain in case any historical reporting needs
 * them) and may be dropped in a later cleanup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (!Schema::hasColumn('quotations', 'from_opportunity_id')) {
                $table->uuid('from_opportunity_id')->nullable()->after('customer_id');
                $table->foreign('from_opportunity_id')->references('id')->on('opportunities')->nullOnDelete();
                $table->index('from_opportunity_id');
            }
            if (!Schema::hasColumn('quotations', 'loss_reason')) {
                $table->text('loss_reason')->nullable()->after('notes');
            }
            if (!Schema::hasColumn('quotations', 'won_by')) {
                $table->uuid('won_by')->nullable();
                $table->timestamp('won_at')->nullable();
            }
            if (!Schema::hasColumn('quotations', 'lost_by')) {
                $table->uuid('lost_by')->nullable();
                $table->timestamp('lost_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::table('quotations', function (Blueprint $table) {
            if (Schema::hasColumn('quotations', 'from_opportunity_id')) {
                $table->dropForeign(['from_opportunity_id']);
                $table->dropColumn('from_opportunity_id');
            }
            foreach (['loss_reason', 'won_by', 'won_at', 'lost_by', 'lost_at'] as $col) {
                if (Schema::hasColumn('quotations', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
