<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Verification campaigns — periodic stock-take events. Custodians scan
        // QR codes during the active window; assets without a scan by the end
        // of the campaign are flagged as `missing` during reconciliation.
        if (!Schema::hasTable('asset_audit_campaigns')) {
            Schema::create('asset_audit_campaigns', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('frequency')->default('biannual'); // annual | biannual | quarterly | adhoc
                $table->date('starts_at');
                $table->date('ends_at');
                $table->string('status')->default('draft'); // draft | active | completed | cancelled
                $table->uuid('assigned_to')->nullable(); // user responsible
                $table->integer('expected_asset_count')->nullable(); // snapshotted at start
                $table->timestamp('started_at')->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index(['tenant_id', 'status']);
                $table->index(['starts_at', 'ends_at']);
            });
        }

        // Verification log — one row per scan/check. `campaign_id` is nullable
        // so adhoc field scans outside a formal campaign are still recorded.
        if (!Schema::hasTable('asset_verification_logs')) {
            Schema::create('asset_verification_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('campaign_id')->nullable();
                $table->uuid('asset_id');
                $table->uuid('verified_by')->nullable(); // User who scanned
                $table->timestamp('verified_at');

                // Snapshot the before/after so the reconciliation report can
                // show drift without re-deriving from audit_logs.
                $table->string('previous_condition')->nullable();
                $table->string('new_condition')->nullable();
                $table->string('previous_location_id')->nullable();
                $table->string('new_location_id')->nullable();

                // matched | moved | damaged | missing | transferred
                $table->string('reconciliation_status')->default('matched');
                $table->text('notes')->nullable();

                $table->string('tenant_id');
                $table->timestamps();

                $table->index('tenant_id');
                $table->index(['campaign_id', 'asset_id']);
                $table->index(['asset_id', 'verified_at']);

                $table->foreign('campaign_id')->references('id')->on('asset_audit_campaigns')->nullOnDelete();
                $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('asset_verification_logs');
        Schema::dropIfExists('asset_audit_campaigns');
    }
};
