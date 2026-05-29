<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\EDocuments;

use App\Models\Central\Tenant;
use App\Models\Tenant\Document;
use App\Models\Tenant\Folder;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 — eDocuments rows created in Tenant A's DB must be invisible to
 * Tenant B. BelongsToTenant's global scope + the per-tenant DB connection
 * are the two defences this test exercises.
 */
class DocumentIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['id' => 'tenant-a', 'handle' => 'tenant-a', 'name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['id' => 'tenant-b', 'handle' => 'tenant-b', 'name' => 'Tenant B']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_tenant_b_cannot_see_tenant_a_documents(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);

        $folderA = Folder::create(['name' => 'A-Folder']);
        $docA = Document::create([
            'title' => 'A-Confidential',
            'filename' => 'a-confidential.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 1024,
            'path' => 'edocuments/documents/a.pdf',
            'folder_id' => $folderA->id,
        ]);

        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $this->seed(TenantDatabaseSeeder::class);

        $this->assertSame(0, Document::count(), 'Tenant B must not see Tenant A documents.');
        $this->assertNull(Document::find($docA->id));
    }

    public function test_tenant_b_cannot_load_tenant_a_folder(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);

        $folderA = Folder::create(['name' => 'A-Private']);

        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $this->seed(TenantDatabaseSeeder::class);

        $this->assertSame(0, Folder::count(), 'Tenant B must not see Tenant A folders.');
        $this->assertNull(Folder::find($folderA->id));
    }
}
