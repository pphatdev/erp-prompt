<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Product;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated storefront surface for the inventory catalog.
 *
 * Three endpoints:
 *   GET /public/catalog                       -> list active products + price
 *   GET /public/catalog/{product}             -> single product detail
 *   GET /public/catalog/{product}/availability -> per-warehouse stock breakdown
 *
 * Tenant is still resolved by the X-Tenant-Handle header (per the global
 * InitializeTenancyByHandle middleware), so each tenant's storefront sees
 * only its own catalog. Soft-deleted and is_active=false rows are excluded.
 *
 * Deliberately omits: average_cost, last_cost, total_quantity across
 * warehouses (those are internal). Public payload shows only sellable
 * fields + per-warehouse net availability.
 */
class PublicCatalogController extends Controller
{
    public function __construct(
        private readonly StockService $stock,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = Product::query()
            ->where('is_active', true)
            ->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('sku', 'ilike', "%{$search}%");
            });
        }
        if ($type = $request->query('type')) {
            $query->where('product_type', $type);
        }

        $limit = max(1, min((int) $request->query('limit', 24), 100));
        $page  = $query->paginate($limit)->withQueryString();

        return response()->json([
            'data' => $page->getCollection()->map(fn ($p) => $this->transform($p))->values(),
            'pagination' => [
                'page'       => $page->currentPage(),
                'limit'      => $page->perPage(),
                'total'      => $page->total(),
                'totalPages' => $page->lastPage(),
            ],
        ]);
    }

    public function show(Product $product): JsonResponse
    {
        abort_unless($product->is_active, 404);
        return response()->json(['data' => $this->transform($product)]);
    }

    /**
     * Per-warehouse availability breakdown. Returns only warehouses with
     * non-zero physical stock so a storefront can pick the closest one.
     * Uses net availability (physical − active reservations) so a cart
     * doesn't claim units another cart is already holding.
     */
    public function availability(Product $product): JsonResponse
    {
        abort_unless($product->is_active, 404);

        $warehouses = Warehouse::query()->orderBy('code')->get();

        $breakdown = $warehouses
            ->map(function (Warehouse $w) use ($product) {
                $physical = $this->stock->getPhysicalStock($product->id, $w->id);
                $net      = $this->stock->getNetAvailableStock($product->id, $w->id);
                return [
                    'warehouseId'    => $w->id,
                    'warehouseCode'  => $w->code,
                    'warehouseName'  => $w->name,
                    'physicalStock'  => $physical,
                    'reservedStock'  => max(0.0, $physical - $net),
                    'availableStock' => $net,
                ];
            })
            ->filter(fn ($row) => $row['physicalStock'] > 0)
            ->values();

        return response()->json([
            'productId'         => $product->id,
            'sku'               => $product->sku,
            'totalAvailable'    => (float) $breakdown->sum('availableStock'),
            'warehouseBreakdown' => $breakdown,
        ]);
    }

    /**
     * Public projection — never expose cost, average_cost, last_cost, or
     * cross-warehouse aggregate quantities. Just what a shopper needs.
     */
    private function transform(Product $p): array
    {
        return [
            'id'           => $p->id,
            'sku'          => $p->sku,
            'name'         => $p->name,
            'description'  => $p->description,
            'productType'  => $p->product_type,
            'unitPrice'    => (float) $p->unit_price,
            'imagePath'    => $p->image_path,
        ];
    }
}
