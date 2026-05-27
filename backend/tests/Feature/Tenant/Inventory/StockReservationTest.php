<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Inventory;

use App\Models\Tenant\Product;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\StockReservation;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Services\StockReservationService;
use App\Tenants\Modules\Inventory\Services\StockService;
use Tests\Feature\TenantTestCase;

class StockReservationTest extends TenantTestCase
{
    private StockReservationService $reservations;
    private StockService $stock;
    private Warehouse $warehouse;
    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reservations = app(StockReservationService::class);
        $this->stock        = app(StockService::class);

        $this->warehouse = Warehouse::create(['code' => 'WH-RES', 'name' => 'Reservation Test WH']);
        $this->product = Product::create([
            'sku' => 'RES-1', 'name' => 'Reservable item',
            'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 10, 'minimum_stock_level' => 0,
        ]);

        // Seed 20 physical units so reservation math has room to work.
        $this->stock->recordMovement([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id,
            'type' => 'in', 'quantity' => 20, 'unit_cost' => 5.00,
        ]);
    }

    public function test_reserve_creates_active_row_and_drops_net_available_stock(): void
    {
        $this->assertSame(20.0, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id));
        $this->assertSame(20.0, $this->stock->getNetAvailableStock($this->product->id, $this->warehouse->id));

        $res = $this->reservations->reserve([
            'product_id'   => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity'     => 7,
            'reference'    => 'CART:test-1',
        ]);

        $this->assertSame(StockReservation::STATUS_ACTIVE, $res->status);
        $this->assertNotNull($res->expires_at);
        // Physical unchanged, net drops by reserved qty.
        $this->assertSame(20.0, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id));
        $this->assertSame(13.0, $this->stock->getNetAvailableStock($this->product->id, $this->warehouse->id));
    }

    public function test_second_reservation_over_net_available_is_rejected(): void
    {
        $this->reservations->reserve([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 15,
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Insufficient available stock');
        // Net available is now 5; asking for 6 must fail before any insert.
        $this->reservations->reserve([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 6,
        ]);
    }

    public function test_commit_posts_out_movement_and_flips_status(): void
    {
        $res = $this->reservations->reserve([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 4,
        ]);

        $committed = $this->reservations->commit($res);

        $this->assertSame(StockReservation::STATUS_COMMITTED, $committed->status);
        $this->assertNotNull($committed->committed_at);
        // Physical drops; net should equal physical again (no active hold left).
        $this->assertSame(16.0, $this->stock->getPhysicalStock($this->product->id, $this->warehouse->id));
        $this->assertSame(16.0, $this->stock->getNetAvailableStock($this->product->id, $this->warehouse->id));

        $out = StockMovement::where('reference', "RES:{$res->id}")
            ->where('type', 'out')
            ->first();
        $this->assertNotNull($out);
        $this->assertSame(-4, (int) $out->quantity);
    }

    public function test_cancel_releases_the_hold_and_is_idempotent(): void
    {
        $res = $this->reservations->reserve([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 8,
        ]);
        $this->assertSame(12.0, $this->stock->getNetAvailableStock($this->product->id, $this->warehouse->id));

        $cancelled = $this->reservations->cancel($res, 'Customer abandoned cart');
        $this->assertSame(StockReservation::STATUS_CANCELLED, $cancelled->status);
        $this->assertSame('Customer abandoned cart', $cancelled->cancel_reason);
        $this->assertSame(20.0, $this->stock->getNetAvailableStock($this->product->id, $this->warehouse->id));

        // Idempotent — re-cancelling returns the same row, no exception.
        $again = $this->reservations->cancel($cancelled);
        $this->assertSame(StockReservation::STATUS_CANCELLED, $again->status);
    }

    public function test_committed_reservation_cannot_be_committed_again(): void
    {
        $res = $this->reservations->reserve([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 2,
        ]);
        $this->reservations->commit($res);

        // Re-commit must be a no-op (already-committed early return).
        $again = $this->reservations->commit($res->fresh());
        $this->assertSame(StockReservation::STATUS_COMMITTED, $again->status);

        // Only ONE out-movement was posted (no double-spend).
        $count = StockMovement::where('reference', "RES:{$res->id}")
            ->where('type', 'out')
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_cancelled_reservation_cannot_be_committed(): void
    {
        $res = $this->reservations->reserve([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 3,
        ]);
        $this->reservations->cancel($res);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('only active reservations can be committed');
        $this->reservations->commit($res->fresh());
    }

    public function test_expire_due_flips_past_ttl_to_expired(): void
    {
        $res = $this->reservations->reserve([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 5,
        ]);
        // Backdate so the daemon sweep would catch it.
        $res->update(['expires_at' => now()->subMinute()]);

        $affected = $this->reservations->expireDue();
        $this->assertSame(1, $affected);

        $this->assertSame(StockReservation::STATUS_EXPIRED, $res->fresh()->status);
        // Expired no longer holds stock.
        $this->assertSame(20.0, $this->stock->getNetAvailableStock($this->product->id, $this->warehouse->id));
    }

    public function test_expire_leaves_future_dated_reservations_active(): void
    {
        $res = $this->reservations->reserve([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 5,
        ]);

        $affected = $this->reservations->expireDue();
        $this->assertSame(0, $affected);
        $this->assertSame(StockReservation::STATUS_ACTIVE, $res->fresh()->status);
    }

    public function test_zero_or_negative_quantity_rejected(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('quantity must be positive');
        $this->reservations->reserve([
            'product_id' => $this->product->id, 'warehouse_id' => $this->warehouse->id, 'quantity' => 0,
        ]);
    }
}
