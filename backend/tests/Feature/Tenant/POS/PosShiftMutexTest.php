<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\POS;

use App\Models\Tenant\PosShift;
use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\POS\Services\PosShiftService;
use Tests\Feature\TenantTestCase;

/**
 * P1 - openShift mutex: at most one open shift per terminal, and at most
 * one open shift per cashier across all terminals.
 */
class PosShiftMutexTest extends TenantTestCase
{
    private PosShiftService $service;
    private PosTerminal $terminal1;
    private PosTerminal $terminal2;
    private User $cashier1;
    private User $cashier2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(PosShiftService::class);

        $warehouse = Warehouse::create(['code' => 'WH-MX', 'name' => 'Mutex WH']);
        $this->terminal1 = PosTerminal::create([
            'code' => 'REG-1', 'name' => 'Register 1',
            'warehouse_id' => $warehouse->id,
            'status' => PosTerminal::STATUS_ACTIVE,
        ]);
        $this->terminal2 = PosTerminal::create([
            'code' => 'REG-2', 'name' => 'Register 2',
            'warehouse_id' => $warehouse->id,
            'status' => PosTerminal::STATUS_ACTIVE,
        ]);
        $this->cashier1 = User::create([
            'name' => 'Cashier One', 'email' => 'c1@test.com', 'password' => 'secret123',
        ]);
        $this->cashier2 = User::create([
            'name' => 'Cashier Two', 'email' => 'c2@test.com', 'password' => 'secret123',
        ]);
    }

    public function test_second_open_shift_on_same_terminal_is_rejected(): void
    {
        $first = $this->service->openShift($this->terminal1, $this->cashier1, 100.0);
        $this->assertSame(PosShift::STATUS_OPEN, $first->status);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("already has an open shift");
        $this->service->openShift($this->terminal1, $this->cashier2, 100.0);
    }

    public function test_second_open_shift_for_same_cashier_on_other_terminal_is_rejected(): void
    {
        $this->service->openShift($this->terminal1, $this->cashier1, 100.0);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage("already has an open shift on another terminal");
        $this->service->openShift($this->terminal2, $this->cashier1, 50.0);
    }

    public function test_two_cashiers_can_open_shifts_on_different_terminals(): void
    {
        $a = $this->service->openShift($this->terminal1, $this->cashier1, 100.0);
        $b = $this->service->openShift($this->terminal2, $this->cashier2, 80.0);

        $this->assertSame(PosShift::STATUS_OPEN, $a->status);
        $this->assertSame(PosShift::STATUS_OPEN, $b->status);
        $this->assertSame(2, PosShift::where('status', PosShift::STATUS_OPEN)->count());
    }

    public function test_reopening_after_close_is_allowed(): void
    {
        $shift = $this->service->openShift($this->terminal1, $this->cashier1, 100.0);
        $this->service->closeShift($shift->fresh(), 100.0);

        // After close, the terminal is free again.
        $next = $this->service->openShift($this->terminal1, $this->cashier1, 50.0);
        $this->assertSame(PosShift::STATUS_OPEN, $next->status);
    }

    public function test_disabled_terminal_rejects_open_shift(): void
    {
        $this->terminal1->update(['status' => PosTerminal::STATUS_DISABLED]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not active');
        $this->service->openShift($this->terminal1, $this->cashier1, 100.0);
    }

    public function test_close_computes_expected_cash_and_variance(): void
    {
        $shift = $this->service->openShift($this->terminal1, $this->cashier1, 100.0);

        // Counted matches opening float because we took zero sales -> variance 0 -> closed.
        $closed = $this->service->closeShift($shift->fresh(), 100.0);

        $this->assertSame(PosShift::STATUS_CLOSED, $closed->status);
        $this->assertEquals(100.0, (float) $closed->expected_cash);
        $this->assertEquals(0.0, (float) $closed->variance);
    }

    public function test_close_with_off_count_flips_to_variance_pending(): void
    {
        $shift = $this->service->openShift($this->terminal1, $this->cashier1, 100.0);

        $closed = $this->service->closeShift($shift->fresh(), 95.0);

        $this->assertSame(PosShift::STATUS_VARIANCE_PENDING, $closed->status);
        $this->assertEquals(-5.0, (float) $closed->variance, 'closing 95 vs expected 100 = -5');
    }
}
