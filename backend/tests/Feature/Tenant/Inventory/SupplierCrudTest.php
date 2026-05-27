<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\Supplier;
use App\Tenants\Modules\Inventory\Services\SupplierService;
use Tests\Feature\TenantTestCase;

class SupplierCrudTest extends TenantTestCase
{
    private SupplierService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(SupplierService::class);
    }

    public function test_create_with_minimal_fields(): void
    {
        $s = $this->service->create(['name' => 'Acme Supplies']);
        $this->assertTrue($s->isActive());
        $this->assertNull($s->code);
        $this->assertNull($s->rating);
    }

    public function test_duplicate_code_rejected(): void
    {
        $this->service->create(['code' => 'SUP-DUP', 'name' => 'A']);
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("code 'SUP-DUP' already exists");
        $this->service->create(['code' => 'SUP-DUP', 'name' => 'B']);
    }

    public function test_rating_out_of_range_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('between 1 and 5');
        $this->service->create(['name' => 'Bad', 'rating' => 7]);
    }

    public function test_update_rating_in_range(): void
    {
        $s = $this->service->create(['name' => 'OK']);
        $updated = $this->service->update($s, ['rating' => 4]);
        $this->assertSame(4, $updated->rating);
    }

    public function test_archive_soft_deletes_and_flips_active(): void
    {
        $s = $this->service->create(['name' => 'Going Away']);
        $this->service->archive($s);
        $fresh = Supplier::withTrashed()->find($s->id);
        $this->assertNotNull($fresh->deleted_at);
        $this->assertFalse((bool) $fresh->is_active);
    }
}
