<?php

declare(strict_types=1);

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            // Human-readable candidate code (e.g. CAN-202605-001). Mirrors
            // the employee_id pattern but scoped per applied month so the
            // numeric component stays short and meaningful for recruiters.
            $table->string('candidate_code')->nullable()->after('id');
        });

        $this->backfillCandidateCodes();

        Schema::table('applications', function (Blueprint $table) {
            $table->unique('candidate_code');
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropUnique(['candidate_code']);
            $table->dropColumn('candidate_code');
        });
    }

    /**
     * Assign deterministic CAN-YYYYMM-NNN codes to pre-existing rows, ordered
     * by applied_at (fallback created_at) so the sequence matches the order
     * applications were received in each month.
     */
    private function backfillCandidateCodes(): void
    {
        $rows = DB::table('applications')
            ->select('id', 'applied_at', 'created_at')
            ->whereNull('candidate_code')
            ->orderByRaw('COALESCE(applied_at, created_at) ASC')
            ->orderBy('id')
            ->get();

        $counters = [];
        foreach ($rows as $row) {
            $reference = $row->applied_at ?? $row->created_at ?? now();
            $yearMonth = Carbon::parse($reference)->format('Ym');
            $counters[$yearMonth] = ($counters[$yearMonth] ?? 0) + 1;
            $code = sprintf('CAN-%s-%03d', $yearMonth, $counters[$yearMonth]);

            DB::table('applications')
                ->where('id', $row->id)
                ->update(['candidate_code' => $code]);
        }
    }
};
