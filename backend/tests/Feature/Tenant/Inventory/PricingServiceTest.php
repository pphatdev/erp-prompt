<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Tenants\Modules\Inventory\Services\PricingService;
use Tests\Feature\TenantTestCase;

class PricingServiceTest extends TenantTestCase
{
    private PricingService $pricing;
    private Product $product;
    private ProductVariant $variant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->pricing = app(PricingService::class);
        $this->product = Product::create([
            'sku' => 'P-1', 'name' => 'Base',
            'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 100.00, 'minimum_stock_level' => 0,
        ]);
        $this->variant = ProductVariant::create([
            'product_id' => $this->product->id,
            'sku' => 'P-1-PRO', 'name' => 'Pro',
            'unit_price' => 150.00,
        ]);
    }

    public function test_resolves_to_product_unit_price_by_default(): void
    {
        $r = $this->pricing->resolveLine(['product_id' => $this->product->id]);

        $this->assertSame(100.00, $r['unit_price']);
        $this->assertNull($r['variant']);
        $this->assertNull($r['variant_sku']);
    }

    public function test_variant_overrides_product_price(): void
    {
        $r = $this->pricing->resolveLine([
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
        ]);

        $this->assertSame(150.00, $r['unit_price']);
        $this->assertSame('P-1-PRO', $r['variant_sku']);
    }

    public function test_caller_override_wins_over_everything(): void
    {
        // Even when a variant baseline of 150 is in play, an explicit
        // unit_price (e.g. negotiated discount) must take precedence.
        $r = $this->pricing->resolveLine([
            'product_id' => $this->product->id,
            'variant_id' => $this->variant->id,
            'unit_price' => 119.99,
        ]);

        $this->assertSame(119.99, $r['unit_price']);
        $this->assertSame('P-1-PRO', $r['variant_sku']);
    }

    public function test_null_override_is_ignored(): void
    {
        // Explicit null is treated as "not provided" — falls through to baseline.
        $r = $this->pricing->resolveLine([
            'product_id' => $this->product->id,
            'unit_price' => null,
        ]);

        $this->assertSame(100.00, $r['unit_price']);
    }
}
