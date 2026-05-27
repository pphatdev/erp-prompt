<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Events;

use App\Models\Tenant\Product;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired once per threshold-crossing — when an out-movement (or transfer-out
 * or sale commit) drops `Product->total_quantity` from at-or-above
 * `minimum_stock_level` to below it. Subsequent out-movements while
 * already below threshold do NOT re-fire, so a busy SKU doesn't spam the
 * notification queue.
 */
class ProductWentBelowMinimumStock
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly Product $product,
        public readonly float $previousQuantity,
        public readonly float $currentQuantity,
        public readonly int $threshold,
    ) {}
}
