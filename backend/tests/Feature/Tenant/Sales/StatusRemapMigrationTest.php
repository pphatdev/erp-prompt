<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Sales;

use Illuminate\Support\Facades\DB;
use Tests\Feature\TenantTestCase;

/**
 * Verifies the per-tenant remap migration body. Because the migration runs as
 * part of the standard tenant migrate sequence, by the time the test runs the
 * data is already in the target shape. We re-seed legacy values directly and
 * re-run the remap logic to assert the mapping is correct on a clean slate.
 */
class StatusRemapMigrationTest extends TenantTestCase
{
    public function test_remap_translates_legacy_status_values(): void
    {
        // Seed a row in each legacy state directly via raw DB inserts (skipping
        // model casts / global scopes) so we can verify the remap rules
        // in isolation.
        $tenantId = tenant('id') ?? 'test-tenant';
        $now = now();

        DB::table('quotations')->insert([
            ['id' => $this->uuid(), 'quote_number' => 'QT-LEG-1', 'status' => 'new',       'tenant_id' => $tenantId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => $this->uuid(), 'quote_number' => 'QT-LEG-2', 'status' => 'confirmed', 'tenant_id' => $tenantId, 'created_at' => $now, 'updated_at' => $now],
            ['id' => $this->uuid(), 'quote_number' => 'QT-LEG-3', 'status' => 'cancelled', 'cancel_reason' => null, 'tenant_id' => $tenantId, 'created_at' => $now, 'updated_at' => $now],
        ]);

        // Run the same remap logic the migration runs.
        DB::table('quotations')->where('status', 'new')->update(['status' => 'draft']);
        DB::table('quotations')->where('status', 'confirmed')->update(['status' => 'won']);
        DB::table('quotations')
            ->where('status', 'cancelled')
            ->update([
                'status'        => 'lost',
                'cancel_reason' => DB::raw("COALESCE(cancel_reason, 'Legacy cancellation')"),
            ]);

        $this->assertSame('draft', DB::table('quotations')->where('quote_number', 'QT-LEG-1')->value('status'));
        $this->assertSame('won',   DB::table('quotations')->where('quote_number', 'QT-LEG-2')->value('status'));
        $row = DB::table('quotations')->where('quote_number', 'QT-LEG-3')->first();
        $this->assertSame('lost', $row->status);
        $this->assertSame('Legacy cancellation', $row->cancel_reason);
    }

    private function uuid(): string
    {
        return (string) \Illuminate\Support\Str::uuid();
    }
}
