<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Map-in-place status remap aligned with the target hybrid-sales flow.
 *
 *   Quotation:    new       -> draft
 *                 confirmed -> won
 *                 cancelled -> lost (cancel_reason backfilled as loss_reason source)
 *
 *   Order:        new       -> draft
 *                 confirmed -> confirm
 *                 cancelled -> cancel
 *
 *   Subscription: new       -> active
 *                 confirmed -> active
 *                 active/expired/cancelled left as-is
 *
 * Idempotent — re-running on already-remapped data is a no-op because the
 * source enum values no longer exist.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function () {
            if (Schema::hasTable('quotations')) {
                DB::table('quotations')->where('status', 'new')->update(['status' => 'draft']);
                DB::table('quotations')->where('status', 'confirmed')->update(['status' => 'won']);
                DB::table('quotations')
                    ->where('status', 'cancelled')
                    ->update([
                        'status'        => 'lost',
                        'cancel_reason' => DB::raw("COALESCE(cancel_reason, 'Legacy cancellation')"),
                    ]);
            }

            if (Schema::hasTable('orders')) {
                DB::table('orders')->where('status', 'new')->update(['status' => 'draft']);
                DB::table('orders')->where('status', 'confirmed')->update(['status' => 'confirm']);
                DB::table('orders')->where('status', 'cancelled')->update(['status' => 'cancel']);
            }

            if (Schema::hasTable('subscriptions')) {
                DB::table('subscriptions')->whereIn('status', ['new', 'confirmed'])->update(['status' => 'active']);
            }
        });
    }

    public function down(): void
    {
        DB::transaction(function () {
            if (Schema::hasTable('quotations')) {
                DB::table('quotations')->where('status', 'draft')->update(['status' => 'new']);
                DB::table('quotations')->where('status', 'won')->update(['status' => 'confirmed']);
                DB::table('quotations')->where('status', 'lost')->update(['status' => 'cancelled']);
            }

            if (Schema::hasTable('orders')) {
                DB::table('orders')->where('status', 'draft')->update(['status' => 'new']);
                DB::table('orders')->where('status', 'confirm')->update(['status' => 'confirmed']);
                DB::table('orders')->where('status', 'cancel')->update(['status' => 'cancelled']);
            }

            // Subscription down-mapping is ambiguous (we don't know whether an
            // 'active' row was originally 'new' or 'confirmed'). Leave as-is.
        });
    }
};
