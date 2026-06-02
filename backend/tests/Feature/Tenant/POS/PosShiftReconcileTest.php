<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\POS;

use App\Models\Tenant\Account;
use App\Models\Tenant\JournalEntry;
use App\Models\Tenant\PosPayment;
use App\Models\Tenant\PosShift;
use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\Product;
use App\Models\Tenant\User;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Services\StockService;
use App\Tenants\Modules\POS\Services\PosOrderService;
use App\Tenants\Modules\POS\Services\PosShiftService;
use App\Tenants\Modules\POS\Services\PosShiftSupervisorService;
use Tests\Feature\TenantTestCase;

/**
 * Phase 2.5 - Supervisor reconciliation of variance_pending shifts.
 *
 * Asserts:
 *   - Over (variance > 0) posts DR Cash / CR Cash Over-Short with the
 *     absolute variance amount.
 *   - Short (variance < 0) posts DR Cash Over-Short / CR Cash.
 *   - Status flips to `reconciled` and `variance_journal_entry_id` is set.
 *   - Re-reconciling an already-reconciled shift is a no-op (idempotent).
 *   - Open / closed (variance-clean) shifts cannot be reconciled.
 */
class PosShiftReconcileTest extends TenantTestCase
{
    private PosShiftService $shifts;
    private PosOrderService $orders;
    private PosShiftSupervisorService $supervisor;
    private StockService $stock;

    private PosTerminal $terminal;
    private User $cashier;
    private Product $product;
    private Warehouse $warehouse;
    private Account $cashAccount;
    private Account $overShortAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->shifts = app(PosShiftService::class);
        $this->orders = app(PosOrderService::class);
        $this->supervisor = app(PosShiftSupervisorService::class);
        $this->stock = app(StockService::class);

        // Ensure the GL accounts the reconcile journal references exist.
        $this->cashAccount = Account::query()->where('code', '1100')->first()
            ?? Account::create(['code' => '1100', 'name' => 'Cash on Hand', 'type' => 'asset']);
        $this->overShortAccount = Account::query()->where('code', '5900')->first()
            ?? Account::create(['code' => '5900', 'name' => 'Cash Over/Short', 'type' => 'expense']);

        $this->warehouse = Warehouse::create(['code' => 'WH-RC', 'name' => 'Reconcile WH']);
        $this->terminal = PosTerminal::create([
            'code' => 'REG-RC', 'name' => 'Reconcile Register',
            'warehouse_id' => $this->warehouse->id,
            'status' => PosTerminal::STATUS_ACTIVE,
        ]);
        $this->cashier = User::create([
            'name' => 'RC Cashier', 'email' => 'rc@test.com', 'password' => 'secret123',
        ]);
        $this->product = Product::create([
            'sku' => 'RC-1', 'name' => 'Donut',
            'product_type' => Product::TYPE_HARDWARE,
            'unit_price' => 2.00, 'minimum_stock_level' => 0,
        ]);
        $this->stock->recordMovement([
            'product_id' => $this->product->id,
            'warehouse_id' => $this->warehouse->id,
            'type' => 'in', 'quantity' => 50, 'unit_cost' => 0.50,
        ]);
    }

    private function shortShift(): PosShift
    {
        // Open with 100 float. Take one 2.00 cash sale -> expected 102.
        // Cashier counts 100 -> variance -2 -> variance_pending.
        $shift = $this->shifts->openShift($this->terminal, $this->cashier, 100.0);
        $this->orders->checkout($shift->fresh(), [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payments' => [['payment_method' => PosPayment::METHOD_CASH, 'amount' => 2.00, 'tendered' => 2.00]],
        ]);
        $closed = $this->shifts->closeShift($shift->fresh(), 100.0);
        $this->assertSame(PosShift::STATUS_VARIANCE_PENDING, $closed->status);
        $this->assertEquals(-2.0, (float) $closed->variance);
        return $closed;
    }

    private function overShift(): PosShift
    {
        // Open with 100 float. Take one 2.00 cash sale -> expected 102.
        // Cashier counts 105 -> variance +3 -> variance_pending.
        $shift = $this->shifts->openShift($this->terminal, $this->cashier, 100.0);
        $this->orders->checkout($shift->fresh(), [
            'items' => [['product_id' => $this->product->id, 'quantity' => 1]],
            'payments' => [['payment_method' => PosPayment::METHOD_CASH, 'amount' => 2.00, 'tendered' => 2.00]],
        ]);
        $closed = $this->shifts->closeShift($shift->fresh(), 105.0);
        $this->assertSame(PosShift::STATUS_VARIANCE_PENDING, $closed->status);
        $this->assertEquals(3.0, (float) $closed->variance);
        return $closed;
    }

    public function test_short_variance_posts_DR_over_short_CR_cash_and_reconciles(): void
    {
        $shift = $this->shortShift();
        $reconciled = $this->supervisor->reconcileVariance($shift, 'till miscount');

        $this->assertSame(PosShift::STATUS_RECONCILED, $reconciled->status);
        $this->assertNotNull($reconciled->variance_journal_entry_id);
        $this->assertNotNull($reconciled->reconciled_at);

        $journal = JournalEntry::with('lines')->find($reconciled->variance_journal_entry_id);
        $this->assertNotNull($journal);

        $lines = $journal->lines->keyBy('account_id');
        // Cash Over/Short DR 2; Cash CR 2.
        $this->assertEquals(2.00, (float) $lines[$this->overShortAccount->id]->debit);
        $this->assertEquals(0.00, (float) $lines[$this->overShortAccount->id]->credit);
        $this->assertEquals(0.00, (float) $lines[$this->cashAccount->id]->debit);
        $this->assertEquals(2.00, (float) $lines[$this->cashAccount->id]->credit);
    }

    public function test_over_variance_posts_DR_cash_CR_over_short_and_reconciles(): void
    {
        $shift = $this->overShift();
        $reconciled = $this->supervisor->reconcileVariance($shift, 'extra cash');

        $this->assertSame(PosShift::STATUS_RECONCILED, $reconciled->status);
        $journal = JournalEntry::with('lines')->find($reconciled->variance_journal_entry_id);
        $lines = $journal->lines->keyBy('account_id');

        // Cash DR 3; Cash Over/Short CR 3.
        $this->assertEquals(3.00, (float) $lines[$this->cashAccount->id]->debit);
        $this->assertEquals(0.00, (float) $lines[$this->cashAccount->id]->credit);
        $this->assertEquals(0.00, (float) $lines[$this->overShortAccount->id]->debit);
        $this->assertEquals(3.00, (float) $lines[$this->overShortAccount->id]->credit);
    }

    public function test_double_reconcile_is_idempotent_noop(): void
    {
        $shift = $this->shortShift();
        $first = $this->supervisor->reconcileVariance($shift);
        $firstJournalId = $first->variance_journal_entry_id;

        $second = $this->supervisor->reconcileVariance($first->fresh());
        $this->assertSame($first->id, $second->id);
        $this->assertSame(PosShift::STATUS_RECONCILED, $second->status);
        // No second journal posted.
        $this->assertSame($firstJournalId, $second->variance_journal_entry_id);
    }

    public function test_open_shift_cannot_be_reconciled(): void
    {
        $shift = $this->shifts->openShift($this->terminal, $this->cashier, 100.0);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only variance_pending shifts can be reconciled');
        $this->supervisor->reconcileVariance($shift);
    }

    public function test_clean_closed_shift_cannot_be_reconciled(): void
    {
        $shift = $this->shifts->openShift($this->terminal, $this->cashier, 100.0);
        $closed = $this->shifts->closeShift($shift->fresh(), 100.0);
        $this->assertSame(PosShift::STATUS_CLOSED, $closed->status);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('Only variance_pending shifts can be reconciled');
        $this->supervisor->reconcileVariance($closed);
    }
}
