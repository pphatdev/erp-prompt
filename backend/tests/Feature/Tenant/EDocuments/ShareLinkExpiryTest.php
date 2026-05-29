<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\EDocuments;

use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentShare;
use App\Tenants\Modules\EDocuments\Services\ShareLinkService;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Tests\Feature\TenantTestCase;

/**
 * P0 — the share-link contract: 410 Gone on expired, 403 on bad password,
 * 429 on download cap. Exercised at the service layer so we don't depend on
 * the public route's middleware stack.
 */
class ShareLinkExpiryTest extends TenantTestCase
{
    private ShareLinkService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ShareLinkService::class);
    }

    private function makeDocument(): Document
    {
        return Document::create([
            'title' => 'Policy',
            'filename' => 'policy.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 128,
            'path' => 'edocuments/documents/policy.pdf',
        ]);
    }

    public function test_expired_link_returns_410_gone(): void
    {
        $doc = $this->makeDocument();
        $share = DocumentShare::create([
            'document_id' => $doc->id,
            'token' => str_repeat('a', 64),
            'expires_at' => now()->subMinute(),
            'created_by' => $this->admin->id,
        ]);

        $this->expectException(GoneHttpException::class);
        $this->service->resolve($share->token);
    }

    public function test_password_protected_link_rejects_missing_password(): void
    {
        $doc = $this->makeDocument();
        $share = DocumentShare::create([
            'document_id' => $doc->id,
            'token' => str_repeat('b', 64),
            'password_hash' => Hash::make('correct-horse'),
            'created_by' => $this->admin->id,
        ]);

        $this->expectException(AccessDeniedHttpException::class);
        $this->service->resolve($share->token, null);
    }

    public function test_password_protected_link_rejects_wrong_password(): void
    {
        $doc = $this->makeDocument();
        $share = DocumentShare::create([
            'document_id' => $doc->id,
            'token' => str_repeat('c', 64),
            'password_hash' => Hash::make('correct-horse'),
            'created_by' => $this->admin->id,
        ]);

        $this->expectException(AccessDeniedHttpException::class);
        $this->service->resolve($share->token, 'wrong');
    }

    public function test_correct_password_resolves(): void
    {
        $doc = $this->makeDocument();
        $share = DocumentShare::create([
            'document_id' => $doc->id,
            'token' => str_repeat('d', 64),
            'password_hash' => Hash::make('open-sesame'),
            'created_by' => $this->admin->id,
        ]);

        $resolved = $this->service->resolve($share->token, 'open-sesame');
        $this->assertSame($share->id, $resolved->id);
    }

    public function test_download_cap_returns_429(): void
    {
        $doc = $this->makeDocument();
        $share = DocumentShare::create([
            'document_id' => $doc->id,
            'token' => str_repeat('e', 64),
            'max_downloads' => 2,
            'downloads_count' => 2,
            'created_by' => $this->admin->id,
        ]);

        $this->expectException(TooManyRequestsHttpException::class);
        $this->service->resolve($share->token);
    }

    public function test_unknown_token_returns_410(): void
    {
        $this->expectException(GoneHttpException::class);
        $this->service->resolve('never-issued-token');
    }
}
