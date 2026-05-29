<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('workflow_statuses')) {
            return;
        }

        $tenantIds = DB::table('workflow_statuses')
            ->where('module', 'hrm.application')
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            // Skip if shortlisted is already present (idempotent re-runs).
            $exists = DB::table('workflow_statuses')
                ->where('tenant_id', $tenantId)
                ->where('module', 'hrm.application')
                ->where('key', 'shortlisted')
                ->exists();
            if ($exists) {
                continue;
            }

            // Bump sequence on every downstream status so shortlisted slots
            // in at sequence=3 (between screening and assessment/interview)
            // without colliding with later columns.
            DB::table('workflow_statuses')
                ->where('tenant_id', $tenantId)
                ->where('module', 'hrm.application')
                ->whereIn('key', ['assessment', 'assessment_completed', 'interview', 'offer', 'hired'])
                ->where('sequence', '<', 90)
                ->update(['sequence' => DB::raw('sequence + 1')]);

            // Insert the new shortlisted row.
            DB::table('workflow_statuses')->insert([
                'id'           => (string) Str::uuid(),
                'tenant_id'    => $tenantId,
                'module'       => 'hrm.application',
                'key'          => 'shortlisted',
                'label'        => 'Shortlisted',
                'color'        => 'primary',
                'icon'         => 'ti-list-check',
                'sequence'     => 3,
                'is_initial'   => false,
                'is_terminal'  => false,
                'allowed_next' => json_encode(['assessment', 'interview', 'rejected', 'withdrawn']),
                'metadata'     => null,
                'created_at'   => now(),
                'updated_at'   => now(),
            ]);

            // Fold shortlisted into screening's allowed_next so recruiters
            // can transition Screening → Shortlisted via the Kanban drag.
            $screening = DB::table('workflow_statuses')
                ->where('tenant_id', $tenantId)
                ->where('module', 'hrm.application')
                ->where('key', 'screening')
                ->first();

            if ($screening) {
                $next = json_decode($screening->allowed_next ?? '[]', true) ?: [];
                if (!in_array('shortlisted', $next, true)) {
                    array_unshift($next, 'shortlisted');
                    DB::table('workflow_statuses')
                        ->where('id', $screening->id)
                        ->update([
                            'allowed_next' => json_encode($next),
                            'updated_at'   => now(),
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        if (!Schema::hasTable('workflow_statuses')) {
            return;
        }

        $tenantIds = DB::table('workflow_statuses')
            ->where('module', 'hrm.application')
            ->where('key', 'shortlisted')
            ->distinct()
            ->pluck('tenant_id');

        foreach ($tenantIds as $tenantId) {
            DB::table('workflow_statuses')
                ->where('tenant_id', $tenantId)
                ->where('module', 'hrm.application')
                ->where('key', 'shortlisted')
                ->delete();

            DB::table('workflow_statuses')
                ->where('tenant_id', $tenantId)
                ->where('module', 'hrm.application')
                ->whereIn('key', ['assessment', 'assessment_completed', 'interview', 'offer', 'hired'])
                ->where('sequence', '<', 90)
                ->update(['sequence' => DB::raw('sequence - 1')]);

            $screening = DB::table('workflow_statuses')
                ->where('tenant_id', $tenantId)
                ->where('module', 'hrm.application')
                ->where('key', 'screening')
                ->first();

            if ($screening) {
                $next = json_decode($screening->allowed_next ?? '[]', true) ?: [];
                $next = array_values(array_filter($next, fn ($k) => $k !== 'shortlisted'));
                DB::table('workflow_statuses')
                    ->where('id', $screening->id)
                    ->update([
                        'allowed_next' => json_encode($next),
                        'updated_at'   => now(),
                    ]);
            }
        }
    }
};
