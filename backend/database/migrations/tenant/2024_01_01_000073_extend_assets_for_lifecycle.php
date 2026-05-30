<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // --- 1. Extend `assets` to match the Fixed Asset Management spec.
        if (Schema::hasTable('assets')) {
            // Add missing lifecycle columns.
            Schema::table('assets', function (Blueprint $table) {
                if (!Schema::hasColumn('assets', 'serial_number')) {
                    $table->string('serial_number')->nullable()->after('name');
                }
                if (!Schema::hasColumn('assets', 'description')) {
                    $table->text('description')->nullable()->after('serial_number');
                }
                if (!Schema::hasColumn('assets', 'vendor_name')) {
                    $table->string('vendor_name')->nullable()->after('category');
                }
                if (!Schema::hasColumn('assets', 'accumulated_depreciation')) {
                    $table->decimal('accumulated_depreciation', 15, 2)->default(0)->after('salvage_value');
                }
                if (!Schema::hasColumn('assets', 'condition')) {
                    // Excellent / Good / Fair / Poor / Damaged
                    $table->string('condition')->default('Good')->after('status');
                }
                if (!Schema::hasColumn('assets', 'qr_code_url')) {
                    $table->string('qr_code_url', 500)->nullable()->after('condition');
                }
                if (!Schema::hasColumn('assets', 'notes')) {
                    $table->text('notes')->nullable()->after('qr_code_url');
                }
            });

            // Rename columns to the canonical spec names.
            if (Schema::hasColumn('assets', 'asset_tag') && !Schema::hasColumn('assets', 'asset_code')) {
                Schema::table('assets', fn (Blueprint $t) => $t->renameColumn('asset_tag', 'asset_code'));
            }
            if (Schema::hasColumn('assets', 'purchase_cost') && !Schema::hasColumn('assets', 'purchase_price')) {
                Schema::table('assets', fn (Blueprint $t) => $t->renameColumn('purchase_cost', 'purchase_price'));
            }
            if (Schema::hasColumn('assets', 'custodian_id') && !Schema::hasColumn('assets', 'custodian_employee_id')) {
                Schema::table('assets', fn (Blueprint $t) => $t->renameColumn('custodian_id', 'custodian_employee_id'));
            }

            // useful_life_years -> useful_life_months (data: * 12)
            if (Schema::hasColumn('assets', 'useful_life_years') && !Schema::hasColumn('assets', 'useful_life_months')) {
                Schema::table('assets', function (Blueprint $table) {
                    $table->integer('useful_life_months')->nullable()->after('useful_life_years');
                });
                DB::statement('UPDATE assets SET useful_life_months = useful_life_years * 12 WHERE useful_life_years IS NOT NULL');
                Schema::table('assets', function (Blueprint $table) {
                    $table->dropColumn('useful_life_years');
                });
            }

            // Back-fill accumulated_depreciation from legacy current_value (purchase_price - current_value),
            // then drop current_value — NBV is now computed: purchase_price - accumulated_depreciation.
            if (Schema::hasColumn('assets', 'current_value')) {
                DB::statement('
                    UPDATE assets
                    SET accumulated_depreciation = GREATEST(0, COALESCE(purchase_price, 0) - COALESCE(current_value, 0))
                    WHERE accumulated_depreciation = 0
                ');
                Schema::table('assets', function (Blueprint $table) {
                    $table->dropColumn('current_value');
                });
            }

            // Replace global unique(asset_tag/asset_code) with composite (asset_code, tenant_id).
            DB::statement('ALTER TABLE assets DROP CONSTRAINT IF EXISTS assets_asset_tag_unique');
            DB::statement('ALTER TABLE assets DROP CONSTRAINT IF EXISTS assets_asset_code_unique');
            DB::statement('DROP INDEX IF EXISTS assets_asset_tag_unique');
            DB::statement('DROP INDEX IF EXISTS assets_asset_code_unique');
            DB::statement('ALTER TABLE assets DROP CONSTRAINT IF EXISTS assets_code_tenant_id_unique');
            Schema::table('assets', function (Blueprint $table) {
                $table->unique(['asset_code', 'tenant_id'], 'assets_code_tenant_id_unique');
            });

            // Partial unique on serial_number scoped to tenant (allow NULLs).
            DB::statement('DROP INDEX IF EXISTS assets_serial_tenant_id_unique');
            DB::statement(
                'CREATE UNIQUE INDEX assets_serial_tenant_id_unique ON assets (serial_number, tenant_id) WHERE serial_number IS NOT NULL'
            );
        }

        // --- 2. Extend depreciation_logs with the chosen method.
        if (Schema::hasTable('depreciation_logs') && !Schema::hasColumn('depreciation_logs', 'method')) {
            Schema::table('depreciation_logs', function (Blueprint $table) {
                $table->string('method')->nullable()->after('book_value');
            });
        }

        // --- 3. asset_revaluation_logs
        if (!Schema::hasTable('asset_revaluation_logs')) {
            Schema::create('asset_revaluation_logs', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('asset_id');
                $table->date('appraisal_date');
                $table->decimal('previous_value', 15, 2);
                $table->decimal('appraisal_value', 15, 2);
                // Adjustment > 0: surplus; < 0: loss.
                $table->decimal('adjustment_amount', 15, 2);
                $table->string('adjustment_type'); // surplus | loss
                $table->string('appraiser')->nullable();
                $table->text('notes')->nullable();
                $table->uuid('journal_entry_id')->nullable();
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index(['asset_id', 'appraisal_date']);
                $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
            });
        }

        // --- 4. asset_disposals
        if (!Schema::hasTable('asset_disposals')) {
            Schema::create('asset_disposals', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('asset_id');
                $table->date('disposal_date');
                $table->string('disposal_type'); // sale | scrap | writeoff
                $table->decimal('sale_price', 15, 2)->default(0);
                $table->decimal('final_nbv', 15, 2);
                $table->decimal('gain_loss', 15, 2)->default(0);
                $table->string('gain_loss_type')->default('none'); // gain | loss | none
                $table->uuid('journal_entry_id')->nullable();
                $table->text('notes')->nullable();
                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->index('tenant_id');
                $table->index(['asset_id', 'disposal_date']);
                $table->foreign('asset_id')->references('id')->on('assets')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('asset_disposals')) {
            Schema::dropIfExists('asset_disposals');
        }
        if (Schema::hasTable('asset_revaluation_logs')) {
            Schema::dropIfExists('asset_revaluation_logs');
        }

        if (Schema::hasTable('depreciation_logs') && Schema::hasColumn('depreciation_logs', 'method')) {
            Schema::table('depreciation_logs', fn (Blueprint $t) => $t->dropColumn('method'));
        }

        if (Schema::hasTable('assets')) {
            DB::statement('DROP INDEX IF EXISTS assets_serial_tenant_id_unique');
            DB::statement('ALTER TABLE assets DROP CONSTRAINT IF EXISTS assets_code_tenant_id_unique');

            // Restore single-column unique on asset_code.
            if (Schema::hasColumn('assets', 'asset_code')) {
                Schema::table('assets', fn (Blueprint $t) => $t->unique('asset_code'));
            }

            // Re-add current_value as a working column (computed from NBV).
            Schema::table('assets', function (Blueprint $table) {
                if (!Schema::hasColumn('assets', 'current_value')) {
                    $table->decimal('current_value', 15, 2)->default(0)->after('purchase_price');
                }
            });
            DB::statement('
                UPDATE assets
                SET current_value = GREATEST(0, COALESCE(purchase_price, 0) - COALESCE(accumulated_depreciation, 0))
            ');

            // Reverse useful_life_months → useful_life_years.
            if (Schema::hasColumn('assets', 'useful_life_months') && !Schema::hasColumn('assets', 'useful_life_years')) {
                Schema::table('assets', function (Blueprint $table) {
                    $table->integer('useful_life_years')->nullable()->after('useful_life_months');
                });
                DB::statement('UPDATE assets SET useful_life_years = CEIL(useful_life_months / 12.0) WHERE useful_life_months IS NOT NULL');
                Schema::table('assets', fn (Blueprint $t) => $t->dropColumn('useful_life_months'));
            }

            // Reverse renames.
            if (Schema::hasColumn('assets', 'custodian_employee_id') && !Schema::hasColumn('assets', 'custodian_id')) {
                Schema::table('assets', fn (Blueprint $t) => $t->renameColumn('custodian_employee_id', 'custodian_id'));
            }
            if (Schema::hasColumn('assets', 'purchase_price') && !Schema::hasColumn('assets', 'purchase_cost')) {
                Schema::table('assets', fn (Blueprint $t) => $t->renameColumn('purchase_price', 'purchase_cost'));
            }
            if (Schema::hasColumn('assets', 'asset_code') && !Schema::hasColumn('assets', 'asset_tag')) {
                Schema::table('assets', fn (Blueprint $t) => $t->renameColumn('asset_code', 'asset_tag'));
            }

            // Drop the new lifecycle columns.
            Schema::table('assets', function (Blueprint $table) {
                foreach (['serial_number', 'description', 'vendor_name', 'accumulated_depreciation', 'condition', 'qr_code_url', 'notes'] as $col) {
                    if (Schema::hasColumn('assets', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
