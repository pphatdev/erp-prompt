<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\EDocuments;

use App\Models\Central\Tenant;
use App\Models\Tenant\Document;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P1 — DocumentController::index search uses ILIKE on title/filename. The
 * BelongsToTenant global scope must ensure results never leak across tenant
 * boundaries even when both tenants happen to have matching strings.
 */
class SearchScopingTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenantA = Tenant::create(['id' => 'tenant-a', 'handle' => 'tenant-a', 'name' => 'A']);
        $this->tenantB = Tenant::create(['id' => 'tenant-b', 'handle' => 'tenant-b', 'name' => 'B']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_search_does_not_leak_across_tenants(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);

        Document::create([
            'title' => 'Annual Report',
            'filename' => 'annual.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 256,
            'path' => 'edocuments/documents/a.pdf',
        ]);

        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $this->seed(TenantDatabaseSeeder::class);

        Document::create([
            'title' => 'Annual Report',
            'filename' => 'annual.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 256,
            'path' => 'edocuments/documents/b.pdf',
        ]);

        $resultsB = Document::query()
            ->where('title', 'ilike', '%Annual%')
            ->get();

        $this->assertCount(1, $resultsB, 'Search must only see the active tenant rows.');
    }
}
