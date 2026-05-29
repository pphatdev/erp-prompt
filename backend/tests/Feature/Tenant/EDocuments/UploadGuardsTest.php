<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\EDocuments;

use App\Tenants\Modules\EDocuments\Services\DocumentService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\TenantTestCase;

/**
 * Guards inside DocumentService::uploadDocument — banned extensions, banned
 * MIME types, and filename sanitisation. These are the P0 defences that keep
 * an attacker from writing a `.php` payload into tenant storage.
 */
class UploadGuardsTest extends TenantTestCase
{
    private DocumentService $service;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->service = app(DocumentService::class);
    }

    public function test_banned_extension_is_rejected(): void
    {
        $file = UploadedFile::fake()->create('payload.php', 4, 'application/x-php');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("File extension '.php' is not allowed.");

        $this->service->uploadDocument($file, [], (string) $this->admin->id);
    }

    public function test_banned_mime_is_rejected(): void
    {
        // .txt extension passes the extension allowlist but the MIME flag still trips.
        $file = UploadedFile::fake()->create('innocent.txt', 4, 'text/x-php');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("MIME type 'text/x-php' is not allowed.");

        $this->service->uploadDocument($file, [], (string) $this->admin->id);
    }

    public function test_filename_is_sanitised_against_path_traversal(): void
    {
        $file = UploadedFile::fake()->create('../../../etc/passwd.pdf', 4, 'application/pdf');

        $doc = $this->service->uploadDocument($file, [], (string) $this->admin->id);

        $this->assertStringNotContainsString('..', $doc->filename);
        $this->assertStringNotContainsString('/', $doc->filename);
    }

    public function test_clean_upload_persists_a_version(): void
    {
        $file = UploadedFile::fake()->create('report.pdf', 16, 'application/pdf');

        $doc = $this->service->uploadDocument($file, ['title' => 'Q1 Report'], (string) $this->admin->id);

        $this->assertSame('Q1 Report', $doc->title);
        $this->assertSame(1, $doc->versions()->count(), 'Initial upload must seed version 1.');
    }
}
