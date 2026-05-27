<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Services;

use App\Models\Tenant\Product;
use App\Models\Tenant\StockReservation;
use App\Models\Tenant\Warehouse;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Stock reservation engine — soft holds for online carts and POS sessions.
 *
 * Concurrency model:
 *   - reserve()  locks the Product row (lockForUpdate), recomputes net
 *                availability with the reservation lock held, then inserts.
 *                Two parallel reserves over the same product serialise on
 *                the row lock, so the second one sees the first's row when
 *                it checks availability.
 *   - commit()   re-checks status before posting the out-movement so a
 *                race against cancel() / expire() can't double-spend.
 *   - cancel()   is idempotent on already-terminal rows.
 *
 * TTL default is 15 minutes — typical POS checkout window. The scheduled
 * expire daemon (INV-DAEMON, command `inventory:expire-reservations`)
 * sweeps `active` rows with `expires_at < now`.
 */
class StockReservationService
{
    public const DEFAULT_TTL_MINUTES = 15;

    public function __construct(private readonly StockService $stock) {}

    public function buildQuery(): Builder
    {
        return StockReservation::query()
            ->with(['product', 'warehouse', 'variant', 'actor'])
            ->orderByDesc('created_at');
    }

    /**
     * Reserve `qty` units of `product` in `warehouse` for `ttlMinutes`.
     * Throws when net available stock can't cover the request.
     */
    public function reserve(array $data, int $ttlMinutes = self::DEFAULT_TTL_MINUTES): StockReservation
    {
        $qty = (float) $data['quantity'];
        if ($qty <= 0) {
            throw new DomainException('Reservation quantity must be positive.');
        }
        if ($ttlMinutes <= 0) {
            throw new DomainException('Reservation TTL must be positive.');
        }

        return DB::transaction(function () use ($data, $qty, $ttlMinutes) {
            // Lock the product so concurrent reserves serialise. The
            // availability calculation reads stock_reservations rows the
            // other transaction has already inserted, so two callers can't
            // both pass the guard for the same units.
            $product   = Product::lockForUpdate()->findOrFail($data['product_id']);
            $warehouse = Warehouse::findOrFail($data['warehouse_id']);

            $available = $this->stock->getNetAvailableStock($product->id, $warehouse->id);
            if ($available < $qty) {
                throw new DomainException(
                    "Insufficient available stock for {$product->sku} in {$warehouse->name}. " .
                    "Available: {$available}, requested: {$qty}."
                );
            }

            return StockReservation::create([
                'product_id'   => $product->id,
                'warehouse_id' => $warehouse->id,
                'variant_id'   => $data['variant_id'] ?? null,
                'quantity'     => $qty,
                'reference'    => $data['reference'] ?? null,
                'status'       => StockReservation::STATUS_ACTIVE,
                'expires_at'   => CarbonImmutable::now()->addMinutes($ttlMinutes),
                'actor_id'     => $data['actor_id'] ?? Auth::id(),
            ])->load(['product', 'warehouse', 'variant', 'actor']);
        });
    }

    /**
     * Commit an active reservation: post the corresponding stock-out
     * movement and flip the reservation to `committed`. Idempotent on
     * already-committed rows; rejects expired/cancelled rows so the
     * caller can decide whether to re-reserve.
     */
    public function commit(StockReservation $reservation): StockReservation
    {
        return DB::transaction(function () use ($reservation) {
            $fresh = StockReservation::lockForUpdate()->find($reservation->id);
            if (!$fresh) {
                throw new DomainException('Reservation not found.');
            }
            if ($fresh->isCommitted()) {
                return $fresh->load(['product', 'warehouse', 'variant', 'actor']);
            }
            if (!$fresh->isActive()) {
                throw new DomainException(
                    "Reservation is {$fresh->status}; only active reservations can be committed."
                );
            }

            // Post the stock-out via StockService so the negative-stock
            // guard and product-row lock are reused. Reference threads back
            // to the reservation row for audit lookups.
            $this->stock->recordMovement([
                'product_id'   => $fresh->product_id,
                'warehouse_id' => $fresh->warehouse_id,
                'type'         => 'out',
                'quantity'     => (float) $fresh->quantity,
                'reference'    => "RES:{$fresh->id}" . ($fresh->reference ? "/{$fresh->reference}" : ''),
                'notes'        => 'Reservation commit',
            ]);

            $fresh->update([
                'status'       => StockReservation::STATUS_COMMITTED,
                'committed_at' => now(),
            ]);

            return $fresh->fresh(['product', 'warehouse', 'variant', 'actor']);
        });
    }

    /**
     * Release an active reservation back to net-available stock. Idempotent.
     */
    public function cancel(StockReservation $reservation, ?string $reason = null): StockReservation
    {
        return DB::transaction(function () use ($reservation, $reason) {
            $fresh = StockReservation::lockForUpdate()->find($reservation->id);
            if (!$fresh) {
                throw new DomainException('Reservation not found.');
            }
            if ($fresh->isTerminal() && !$fresh->isExpired()) {
                // Already cancelled / committed — no-op return the row as-is.
                // Expired rows can still be cancelled to record an explicit reason.
                return $fresh;
            }
            $fresh->update([
                'status'        => StockReservation::STATUS_CANCELLED,
                'cancel_reason' => $reason,
                'cancelled_at'  => now(),
            ]);
            return $fresh->fresh(['product', 'warehouse', 'variant', 'actor']);
        });
    }

    /**
     * Flip all `active` reservations whose `expires_at < now` to `expired`.
     * Returns the count touched. Called by the scheduled
     * `inventory:expire-reservations` command (INV-DAEMON).
     */
    public function expireDue(): int
    {
        return StockReservation::query()
            ->where('status', StockReservation::STATUS_ACTIVE)
            ->where('expires_at', '<', now())
            ->update([
                'status'     => StockReservation::STATUS_EXPIRED,
                'expired_at' => now(),
            ]);
    }
}
