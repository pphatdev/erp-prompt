<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Services;

use App\Models\Tenant\EcomCart;
use App\Models\Tenant\EcomCartItem;
use App\Models\Tenant\EcomCustomer;
use App\Models\Tenant\Product;
use App\Models\Tenant\ProductVariant;
use App\Models\Tenant\StockReservation;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Services\StockReservationService;
use App\Tenants\Modules\Settings\Services\SettingService;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Shopping cart engine for the storefront.
 *
 * Reservations are taken at add-to-cart time so checkout is guaranteed stock
 * (subject to the 15-min TTL — the inventory expire daemon flips abandoned
 * carts back to available). Default warehouse resolution mirrors
 * OrderFulfillmentService::resolveDefaultWarehouse.
 *
 * Guest carts are keyed by session_token (cookie); on login, mergeGuestCart()
 * folds guest line items into the shopper's existing active cart.
 */
class CartService
{
    public function __construct(
        private readonly StockReservationService $reservations,
        private readonly SettingService $settings,
    ) {
    }

    public function getOrCreateForCustomer(EcomCustomer $customer): EcomCart
    {
        $cart = EcomCart::where('customer_id', $customer->id)
            ->where('status', EcomCart::STATUS_ACTIVE)
            ->first();
        if ($cart) {
            return $cart;
        }

        return EcomCart::create([
            'customer_id' => $customer->id,
            'status' => EcomCart::STATUS_ACTIVE,
            'subtotal' => 0,
            'currency' => $this->defaultCurrency(),
        ]);
    }

    public function getOrCreateForGuest(string $sessionToken): EcomCart
    {
        $cart = EcomCart::whereNull('customer_id')
            ->where('session_token', $sessionToken)
            ->where('status', EcomCart::STATUS_ACTIVE)
            ->first();
        if ($cart) {
            return $cart;
        }

        return EcomCart::create([
            'session_token' => $sessionToken,
            'status' => EcomCart::STATUS_ACTIVE,
            'subtotal' => 0,
            'currency' => $this->defaultCurrency(),
        ]);
    }

    /**
     * Add a line — or increment quantity if the same product+variant already
     * sits in the cart. Reserves stock and persists the reservation id.
     */
    public function addItem(EcomCart $cart, array $data): EcomCartItem
    {
        $quantity = (float) ($data['quantity'] ?? 1);
        if ($quantity <= 0) {
            throw new DomainException('Quantity must be positive.');
        }

        return DB::transaction(function () use ($cart, $data, $quantity) {
            $product = Product::findOrFail($data['product_id']);
            $variant = !empty($data['variant_id'])
                ? ProductVariant::findOrFail($data['variant_id'])
                : null;

            $unitPrice = $this->resolveUnitPrice($product, $variant);

            $existing = $cart->items()
                ->where('product_id', $product->id)
                ->where('variant_id', $variant?->id)
                ->first();

            if ($existing) {
                $newQuantity = (float) $existing->quantity + $quantity;
                $this->topUpReservation($existing, $newQuantity);
                $existing->update([
                    'quantity' => $newQuantity,
                    'line_total' => round($unitPrice * $newQuantity, 2),
                ]);
                $this->recalcTotals($cart);
                return $existing->fresh();
            }

            $warehouse = $this->resolveDefaultWarehouse();
            $reservation = $this->reservations->reserve([
                'product_id' => $product->id,
                'warehouse_id' => $warehouse->id,
                'variant_id' => $variant?->id,
                'quantity' => $quantity,
                'reference' => "CART:{$cart->id}",
            ]);

            $item = $cart->items()->create([
                'product_id' => $product->id,
                'variant_id' => $variant?->id,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => round($unitPrice * $quantity, 2),
                'reservation_id' => $reservation->id,
            ]);

            $this->extendCartExpiry($cart, $reservation);
            $this->recalcTotals($cart);

            return $item->fresh();
        });
    }

    public function updateItemQuantity(EcomCartItem $item, float $quantity): EcomCartItem
    {
        if ($quantity <= 0) {
            throw new DomainException('Quantity must be positive. Use removeItem() to delete a line.');
        }

        return DB::transaction(function () use ($item, $quantity) {
            $this->topUpReservation($item, $quantity);
            $item->update([
                'quantity' => $quantity,
                'line_total' => round((float) $item->unit_price * $quantity, 2),
            ]);
            $this->recalcTotals($item->cart);
            return $item->fresh();
        });
    }

    public function removeItem(EcomCartItem $item): void
    {
        DB::transaction(function () use ($item) {
            if ($item->reservation_id) {
                $reservation = StockReservation::find($item->reservation_id);
                if ($reservation && $reservation->isActive()) {
                    $this->reservations->cancel($reservation, 'cart_item_removed');
                }
            }
            $cart = $item->cart;
            $item->delete();
            $this->recalcTotals($cart);
        });
    }

    /**
     * On login: fold an active guest cart into the shopper's active cart.
     * Same product+variant rows merge quantities; otherwise rows transfer.
     * The guest cart is marked `merged`.
     */
    public function mergeGuestCart(EcomCart $guestCart, EcomCustomer $customer): EcomCart
    {
        if ($guestCart->customer_id !== null) {
            throw new DomainException('Source cart is not a guest cart.');
        }

        return DB::transaction(function () use ($guestCart, $customer) {
            $target = $this->getOrCreateForCustomer($customer);

            foreach ($guestCart->items as $guestItem) {
                $existing = $target->items()
                    ->where('product_id', $guestItem->product_id)
                    ->where('variant_id', $guestItem->variant_id)
                    ->first();

                if ($existing) {
                    $newQty = (float) $existing->quantity + (float) $guestItem->quantity;
                    $this->topUpReservation($existing, $newQty);
                    $existing->update([
                        'quantity' => $newQty,
                        'line_total' => round((float) $existing->unit_price * $newQty, 2),
                    ]);
                    // Release the duplicate guest reservation.
                    if ($guestItem->reservation_id) {
                        $r = StockReservation::find($guestItem->reservation_id);
                        if ($r && $r->isActive()) {
                            $this->reservations->cancel($r, 'cart_merged_duplicate');
                        }
                    }
                    $guestItem->delete();
                } else {
                    $guestItem->update(['cart_id' => $target->id]);
                }
            }

            $guestCart->update([
                'status' => EcomCart::STATUS_MERGED,
            ]);
            $this->recalcTotals($target);

            return $target->fresh('items');
        });
    }

    public function recalcTotals(EcomCart $cart): void
    {
        $subtotal = $cart->items()->sum('line_total');
        $cart->update(['subtotal' => $subtotal]);
    }

    /**
     * Release every active reservation under this cart. Called on
     * checkout-cancel or admin abandon.
     */
    public function releaseReservations(EcomCart $cart, string $reason = 'cart_released'): void
    {
        DB::transaction(function () use ($cart, $reason) {
            foreach ($cart->items as $item) {
                if (!$item->reservation_id) {
                    continue;
                }
                $r = StockReservation::find($item->reservation_id);
                if ($r && $r->isActive()) {
                    $this->reservations->cancel($r, $reason);
                }
            }
        });
    }

    /**
     * Top up an existing reservation to match a new total quantity. Releases
     * the old reservation and creates a fresh one — cheaper than diffing.
     */
    private function topUpReservation(EcomCartItem $item, float $newQuantity): void
    {
        if (!$item->reservation_id) {
            return;
        }
        $reservation = StockReservation::find($item->reservation_id);
        if (!$reservation || !$reservation->isActive()) {
            return;
        }
        if ((float) $reservation->quantity === $newQuantity) {
            return;
        }

        $this->reservations->cancel($reservation, 'reservation_topup');
        $fresh = $this->reservations->reserve([
            'product_id' => $item->product_id,
            'warehouse_id' => $reservation->warehouse_id,
            'variant_id' => $item->variant_id,
            'quantity' => $newQuantity,
            'reference' => $reservation->reference,
        ]);
        $item->update(['reservation_id' => $fresh->id]);
    }

    private function extendCartExpiry(EcomCart $cart, StockReservation $reservation): void
    {
        if (!$cart->expires_at || $cart->expires_at->lt($reservation->expires_at)) {
            $cart->update(['expires_at' => $reservation->expires_at]);
        }
    }

    private function resolveUnitPrice(Product $product, ?ProductVariant $variant): float
    {
        if ($variant && property_exists($variant, 'price') && $variant->price !== null) {
            return (float) $variant->price;
        }
        return (float) $product->unit_price;
    }

    /**
     * Resolution order:
     *   1. Pinned `inventory.default_warehouse_code` setting (admin override).
     *   2. Single-warehouse tenant - that one wins.
     *   3. First active warehouse alphabetically by code (deterministic so
     *      reservations don't shuffle between warehouses across requests).
     *   4. Auto-provision a `MAIN` warehouse - keeps the storefront usable
     *      on a fresh tenant without forcing the admin to pre-create one.
     *      Admin can rename/replace it from Inventory > Warehouses later.
     */
    private function resolveDefaultWarehouse(): Warehouse
    {
        $code = $this->settings->get('inventory.default_warehouse_code');
        if (is_string($code) && $code !== '') {
            $warehouse = Warehouse::where('code', $code)->first();
            if ($warehouse) {
                return $warehouse;
            }
        }
        $count = Warehouse::query()->count();
        if ($count === 1) {
            return Warehouse::query()->first();
        }
        if ($count > 1) {
            $w = Warehouse::query()
                ->where('is_active', true)
                ->orderBy('code')
                ->first()
                ?? Warehouse::query()->orderBy('code')->first();
            if ($w) {
                return $w;
            }
        }
        return Warehouse::create([
            'code' => 'MAIN',
            'name' => 'Main Warehouse',
            'is_active' => true,
            'notes' => 'Auto-created on first ecom cart reservation.',
        ]);
    }

    private function defaultCurrency(): string
    {
        $currency = $this->settings->get('locale.currency');
        return is_string($currency) && $currency !== '' ? strtoupper($currency) : 'USD';
    }
}
