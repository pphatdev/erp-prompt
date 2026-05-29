<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\EDocuments;

use App\Models\Tenant\Document;
use App\Tenants\Modules\EDocuments\Services\AcknowledgementService;
use Tests\Feature\TenantTestCase;

class AcknowledgementTest extends TenantTestCase
{
    private AcknowledgementService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AcknowledgementService::class);
    }

    private function makeDocument(): Document
    {
        return Document::create([
            'title' => 'Code of Conduct',
            'filename' => 'coc.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 512,
            'path' => 'edocuments/documents/coc.pdf',
        ]);
    }

    public function test_acknowledge_is_idempotent(): void
    {
        $doc = $this->makeDocument();

        $first = $this->service->acknowledge($doc, $this->admin);
        $second = $this->service->acknowledge($doc, $this->admin);

        $this->assertSame($first->id, $second->id, 'Re-acknowledging must return the existing row.');
        $this->assertSame(1, $doc->acknowledgements()->count(), 'Idempotency must not duplicate rows.');
    }

    public function test_summary_counts_acknowledged_and_pending(): void
    {
        $doc = $this->makeDocument();
        $this->service->acknowledge($doc, $this->admin);

        $summary = $this->service->summary($doc);

        $this->assertGreaterThanOrEqual(1, $summary['acknowledged']);
        $this->assertIsArray($summary['pending']);
        $this->assertSame($summary['totalEligible'], $summary['acknowledged'] + count($summary['pending']));
    }
}
