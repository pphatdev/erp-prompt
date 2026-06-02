<?php

declare(strict_types=1);

namespace App\Tenants\Modules\POS\Services;

use App\Models\Tenant\PosOrder;
use App\Models\Tenant\PosPayment;
use App\Models\Tenant\PosShift;
use App\Models\Tenant\PosTerminal;
use App\Models\Tenant\User;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Cashier shift lifecycle.
 *
 *   openShift  - records opening_float, transitions to `open`. Refuses if the
 *                terminal already has an open shift or the cashier holds an open
 *                shift on any terminal (one register at a time per cashier).
 *
 *   closeShift - computes:
 *                  expected_cash = opening_float + sum(cash payments on this shift)
 *                  variance      = closing_cash - expected_cash
 *                Transitions to:
 *                  closed              if variance == 0
 *                  variance_pending    if variance != 0 (manager must reconcile
 *                                      via the supervisor flow - out of this
 *                                      service's scope; PosShiftSupervisorService
 *                                      will own it in a later phase)
 */
class PosShiftService
{
    public function openShift(PosTerminal $terminal, User $cashier, float $openingFloat): PosShift
    {
        if (!$terminal->isActive()) {
            throw new DomainException("Terminal '{$terminal->code}' is not active.");
        }
        if ($openingFloat < 0) {
            throw new DomainException('Opening float cannot be negative.');
        }

        return DB::transaction(function () use ($terminal, $cashier, $openingFloat) {
            $terminalHasOpen = PosShift::where('terminal_id', $terminal->id)
                ->where('status', PosShift::STATUS_OPEN)
                ->lockForUpdate()
                ->exists();
            if ($terminalHasOpen) {
                throw new DomainException("Terminal '{$terminal->code}' already has an open shift.");
            }

            $cashierHasOpen = PosShift::where('cashier_id', $cashier->id)
                ->where('status', PosShift::STATUS_OPEN)
                ->lockForUpdate()
                ->exists();
            if ($cashierHasOpen) {
                throw new DomainException("Cashier '{$cashier->name}' already has an open shift on another terminal.");
            }

            return PosShift::create([
                'terminal_id' => $terminal->id,
                'cashier_id' => $cashier->id,
                'opened_at' => now(),
                'opening_float' => $openingFloat,
                'status' => PosShift::STATUS_OPEN,
            ])->fresh();
        });
    }

    public function closeShift(PosShift $shift, float $closingCash, ?string $notes = null): PosShift
    {
        if (!$shift->isOpen()) {
            throw new DomainException("Shift is '{$shift->status}'; only open shifts can be closed.");
        }
        if ($closingCash < 0) {
            throw new DomainException('Closing cash cannot be negative.');
        }

        return DB::transaction(function () use ($shift, $closingCash, $notes) {
            $cashTaken = (float) PosPayment::query()
                ->whereIn('order_id', PosOrder::query()
                    ->where('shift_id', $shift->id)
                    ->where('status', PosOrder::STATUS_PAID)
                    ->pluck('id'))
                ->where('payment_method', PosPayment::METHOD_CASH)
                ->sum('amount');

            $expected = round((float) $shift->opening_float + $cashTaken, 2);
            $variance = round($closingCash - $expected, 2);
            $isExact = abs($variance) < 0.005;

            $shift->update([
                'closed_at' => now(),
                'expected_cash' => $expected,
                'closing_cash' => $closingCash,
                'variance' => $variance,
                'status' => $isExact
                    ? PosShift::STATUS_CLOSED
                    : PosShift::STATUS_VARIANCE_PENDING,
                'notes' => $notes,
            ]);

            return $shift->fresh();
        });
    }

    /**
     * Convenience surface for the cashier dashboard. Returns the cashier's
     * single open shift (if any) - "what register am I on?".
     */
    public function activeShiftForCashier(User $cashier): ?PosShift
    {
        return PosShift::where('cashier_id', $cashier->id)
            ->where('status', PosShift::STATUS_OPEN)
            ->with(['terminal', 'cashier'])
            ->latest('opened_at')
            ->first();
    }

    /**
     * Admin-override surface: returns the most recent open shift across the
     * entire tenant regardless of which cashier holds it. Used by
     * PosShiftController::me when the signed-in user has no shift of their
     * own AND holds `pos.shift.approve`, so supervisors can close / void /
     * reconcile a shift opened under another cashier (covers the cashier
     * walked off / orphaned-row recovery cases).
     */
    public function latestOpenShift(): ?PosShift
    {
        return PosShift::where('status', PosShift::STATUS_OPEN)
            ->with(['terminal', 'cashier'])
            ->latest('opened_at')
            ->first();
    }
}
