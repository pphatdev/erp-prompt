<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Central\Tenant as CentralTenant;
use App\Models\Tenant\Category;
use App\Models\Tenant\Product;
use App\Models\Tenant\StockMovement;
use App\Models\Tenant\Warehouse;
use App\Tenants\Modules\Inventory\Services\StockService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Public, unauthenticated storefront surface for the inventory catalog.
 *
 * Three endpoints:
 *   GET /public/catalog                       -> list active products + price
 *   GET /public/catalog/categories            -> category sidebar source
 *   GET /public/catalog/{product}             -> single product detail
 *   GET /public/catalog/{product}/availability -> per-warehouse stock breakdown
 *
 * Tenant is resolved by the X-Tenant-Handle header (per InitializeTenancyByHandle).
 * Each tenant's storefront sees only its own catalog. Soft-deleted / inactive
 * rows are excluded.
 *
 * AGGREGATOR MODE (demo / root):
 * -------------------------------
 * When the active tenant handle is in `AGGREGATOR_HANDLES`, this controller
 * federates the catalog across every other tenant in the central registry.
 * Used to power a marketplace-style browse view that lists every seller's
 * products side-by-side. Cart/checkout stay tenant-local — aggregator
 * products carry a `tenantHandle` so the frontend can route the shopper to
 * the owning tenant's shop to buy.
 *
 * Deliberately omits: average_cost, last_cost, total_quantity across
 * warehouses (those are internal). Public payload shows only sellable
 * fields + per-warehouse net availability.
 */
class PublicCatalogController extends Controller
{
    /**
     * Tenant handles that act as a marketplace aggregator instead of a
     * regular storefront. Catalog requests against these handles fan out
     * across all other tenants and merge results.
     */
    private const AGGREGATOR_HANDLES = ['demo', 'root'];

    /**
     * Per-tenant row cap when federating. Each tenant DB returns at most
     * this many rows before merge — prevents an N-tenant fanout from
     * ballooning memory if a seller has tens of thousands of products.
     */
    private const AGGREGATOR_PER_TENANT_CAP = 200;

    public function __construct(
        private readonly StockService $stock,
    ) {}

    public function index(Request $request): JsonResponse
    {
        if ($this->isAggregator()) {
            return $this->indexAggregated($request);
        }

        $query = $this->buildProductQuery($request);

        $limit = max(1, min((int) $request->query('limit', 24), 100));
        $page  = $query->paginate($limit)->withQueryString();

        $stockByProduct = $this->stockLedgerFor($page->getCollection()->pluck('id')->all());

        return response()->json([
            'data' => $page->getCollection()
                ->map(fn ($p) => $this->transform($p, $stockByProduct->get($p->id)))
                ->values(),
            'pagination' => [
                'page'       => $page->currentPage(),
                'limit'      => $page->perPage(),
                'total'      => $page->total(),
                'totalPages' => $page->lastPage(),
            ],
        ]);
    }

    /**
     * Marketplace path. Iterates every tenant via `$tenant->run()`, applies
     * the same filters per-tenant, then merges + sorts + paginates the union
     * in memory. Each row carries `tenantHandle` and `tenantName` so the
     * frontend can route detail clicks back to the owning tenant.
     *
     * Category filtering works across tenants because `buildProductQuery()`
     * accepts slugs as well as UUIDs — the aggregator's categories()
     * endpoint returns each row's slug as the synthetic id, so the same
     * slug threads through every tenant's local query.
     */
    private function indexAggregated(Request $request): JsonResponse
    {
        $page   = max(1, (int) $request->query('page', 1));
        $limit  = max(1, min((int) $request->query('limit', 24), 100));
        $sort   = (string) ($request->query('sort') ?? '');

        // Federate across every tenant in the central registry, including
        // the aggregator itself — demo/root may also have their own products
        // and "show all" should mean exactly that.
        $tenants = CentralTenant::query()->get();

        $merged = collect();
        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($tenant, $request, &$merged) {
                $query = $this->buildProductQuery($request);
                $rows  = $query->limit(self::AGGREGATOR_PER_TENANT_CAP)->get();
                $stockByProduct = $this->stockLedgerFor($rows->pluck('id')->all());
                foreach ($rows as $row) {
                    $data = $this->transform($row, $stockByProduct->get($row->id));
                    $data['tenantHandle'] = $tenant->getTenantKey();
                    $data['tenantName']   = $tenant->name;
                    $merged->push($data);
                }
            });
        }

        // Apply the same sort across the merged set so the marketplace view
        // doesn't grow per-tenant blocks.
        $merged = $this->sortAggregated($merged, $sort);

        $total      = $merged->count();
        $totalPages = (int) max(1, ceil($total / $limit));
        $offset     = ($page - 1) * $limit;

        return response()->json([
            'data' => $merged->slice($offset, $limit)->values()->all(),
            'pagination' => [
                'page'       => $page,
                'limit'      => $limit,
                'total'      => $total,
                'totalPages' => $totalPages,
            ],
        ]);
    }

    /**
     * Build the filtered product Builder used by both the local index and
     * each per-tenant pass inside the aggregator. The sort is applied here
     * too so the local path still gets DB-side ordering; the aggregator
     * re-sorts after merge.
     */
    private function buildProductQuery(Request $request): Builder
    {
        $query = Product::query()->where('is_active', true);

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'ilike', "%{$search}%")
                  ->orWhere('sku', 'ilike', "%{$search}%");
            });
        }
        if ($type = $request->query('type')) {
            $query->where('product_type', $type);
        }

        $categoryIds = (array) $request->query('category_ids', []);
        if (! empty($single = $request->query('category_id'))) {
            $categoryIds[] = $single;
        }
        $categoryIds = array_values(array_filter($categoryIds, fn ($v) => is_string($v) && $v !== ''));
        if (! empty($categoryIds)) {
            // Accept either UUIDs (single-tenant local id) or slugs (the
            // aggregator returns slugs in place of ids because each tenant
            // has its own category UUIDs). Splitting the input lets the
            // same Builder work in both contexts without changing the
            // frontend payload.
            $uuids = [];
            $slugs = [];
            foreach ($categoryIds as $value) {
                if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
                    $uuids[] = $value;
                } else {
                    $slugs[] = $value;
                }
            }
            $query->where(function ($q) use ($uuids, $slugs) {
                if (! empty($uuids)) {
                    $q->whereIn('category_id', $uuids);
                }
                if (! empty($slugs)) {
                    $q->orWhereHas('category', fn ($sub) => $sub->whereIn('slug', $slugs));
                }
            });
        }

        $minPrice = $request->query('min_price');
        $maxPrice = $request->query('max_price');
        if (is_numeric($minPrice)) {
            $query->where('unit_price', '>=', (float) $minPrice);
        }
        if (is_numeric($maxPrice)) {
            $query->where('unit_price', '<=', (float) $maxPrice);
        }

        // Stock filter via the ledger — the same source as getPhysicalStock.
        $inStock = $request->query('in_stock');
        if ($inStock !== null && $inStock !== '' && $inStock !== 'all') {
            $truthy = in_array(strtolower((string) $inStock), ['1', 'true', 'in', 'in_stock', 'yes'], true);
            $ledger = StockMovement::query()
                ->select('product_id', DB::raw('SUM(quantity) AS qty'))
                ->groupBy('product_id');
            $query->leftJoinSub($ledger, 'sm_agg', 'sm_agg.product_id', '=', 'products.id');
            $query->where(DB::raw('COALESCE(sm_agg.qty, 0)'), $truthy ? '>' : '<=', 0);
        }

        match ($request->query('sort')) {
            'price_asc'   => $query->orderBy('unit_price')->orderBy('name'),
            'price_desc'  => $query->orderByDesc('unit_price')->orderBy('name'),
            'newest'      => $query->orderByDesc('created_at')->orderBy('name'),
            'featured'    => $query->orderByDesc('updated_at')->orderBy('name'),
            default       => $query->orderBy('name'),
        };

        return $query;
    }

    /**
     * In-memory sort over the merged aggregator collection. Matches the
     * allowlist used in buildProductQuery so a 'price_asc' on aggregator
     * matches what a single-tenant 'price_asc' returns.
     */
    private function sortAggregated(Collection $rows, string $sort): Collection
    {
        return match ($sort) {
            'price_asc'  => $rows->sortBy([['unitPrice', 'asc'], ['name', 'asc']])->values(),
            'price_desc' => $rows->sortBy([['unitPrice', 'desc'], ['name', 'asc']])->values(),
            'newest'     => $rows->sortBy([['createdAt', 'desc'], ['name', 'asc']])->values(),
            'featured'   => $rows->sortBy([['updatedAt', 'desc'], ['name', 'asc']])->values(),
            default      => $rows->sortBy('name')->values(),
        };
    }

    /**
     * One aggregate query — `SUM(quantity) GROUP BY product_id` over the
     * movement ledger — keyed by product id. Returns a Collection so callers
     * can ->get($id) with a null fallback.
     */
    private function stockLedgerFor(array $productIds): Collection
    {
        if (empty($productIds)) {
            return collect();
        }

        return StockMovement::query()
            ->whereIn('product_id', $productIds)
            ->select('product_id', DB::raw('SUM(quantity) AS qty'))
            ->groupBy('product_id')
            ->pluck('qty', 'product_id');
    }

    /**
     * Storefront sidebar source. On aggregator, federates categories across
     * all tenants and merges by slug — the synthetic `id` is the slug so the
     * frontend filter ships back the same string and `buildProductQuery()`
     * threads it through every tenant's local query.
     */
    public function categories(Request $request): JsonResponse
    {
        if ($this->isAggregator()) {
            return $this->aggregatedCategories();
        }

        $productCounts = Product::query()
            ->where('is_active', true)
            ->whereNotNull('category_id')
            ->select('category_id', DB::raw('COUNT(*) as product_count'))
            ->groupBy('category_id')
            ->pluck('product_count', 'category_id');

        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (Category $c) => [
                'id'           => $c->id,
                'slug'         => $c->slug,
                'name'         => $c->name,
                'color'        => $c->color,
                'parentId'     => $c->parent_id,
                'productCount' => (int) ($productCounts[$c->id] ?? 0),
            ])
            ->values();

        return response()->json([
            'data' => $categories,
        ]);
    }

    /**
     * Federated categories. Iterates every tenant, sums active-product
     * counts per slug, and returns one row per distinct slug. The synthetic
     * `id` is the slug — paired with `buildProductQuery()`'s UUID-or-slug
     * detection, the same filter value works for both local and aggregator
     * paths without the frontend knowing the difference.
     */
    private function aggregatedCategories(): JsonResponse
    {
        $tenants = CentralTenant::query()->get();
        $merged  = [];

        foreach ($tenants as $tenant) {
            $tenant->run(function () use ($tenant, &$merged) {
                $productCounts = Product::query()
                    ->where('is_active', true)
                    ->whereNotNull('category_id')
                    ->select('category_id', DB::raw('COUNT(*) as product_count'))
                    ->groupBy('category_id')
                    ->pluck('product_count', 'category_id');

                $categories = Category::query()
                    ->where('is_active', true)
                    ->get();

                foreach ($categories as $c) {
                    $count = (int) ($productCounts[$c->id] ?? 0);
                    if ($count <= 0) {
                        continue;
                    }
                    $key = $c->slug;
                    if (! isset($merged[$key])) {
                        $merged[$key] = [
                            'id'            => $c->slug,
                            'slug'          => $c->slug,
                            'name'          => $c->name,
                            'color'         => $c->color,
                            'parentId'      => null, // parent UUIDs would collide; omit on aggregator
                            'productCount'  => 0,
                            'tenantHandles' => [],
                        ];
                    }
                    $merged[$key]['productCount']  += $count;
                    $merged[$key]['tenantHandles'][] = $tenant->getTenantKey();
                }
            });
        }

        $rows = array_values($merged);
        usort($rows, fn ($a, $b) => strcasecmp($a['name'], $b['name']));

        return response()->json(['data' => $rows]);
    }

    /**
     * Single product detail. Signature uses `string $product` rather than
     * route-bound `Product $product` because aggregator mode needs to
     * switch tenant before resolving the row — the auto-binding would
     * hit the aggregator's empty DB and 404.
     */
    public function show(Request $request, string $product): JsonResponse
    {
        $tenant = $this->resolveAggregatorTenant($request);

        // Same-handle aggregator click — already in the right tenant context,
        // just tag the response with the handle so the frontend stays
        // marketplace-aware.
        if ($tenant && $tenant->getTenantKey() === tenant()?->getTenantKey()) {
            return $this->respondWithProduct($product, $tenant);
        }

        if ($tenant) {
            return $tenant->run(fn () => $this->respondWithProduct($product, $tenant));
        }

        return $this->respondWithProduct($product, null);
    }

    private function respondWithProduct(string $productId, ?CentralTenant $tenant): JsonResponse
    {
        $p = Product::query()->where('id', $productId)->first();
        abort_unless($p && $p->is_active, 404);

        $stock = $this->stockLedgerFor([$p->id])->get($p->id);
        $data  = $this->transform($p, $stock);

        if ($tenant) {
            $data['tenantHandle'] = $tenant->getTenantKey();
            $data['tenantName']   = $tenant->name;
        }

        return response()->json(['data' => $data]);
    }

    /**
     * Per-warehouse availability breakdown. Returns only warehouses with
     * non-zero physical stock so a storefront can pick the closest one.
     * Uses net availability (physical − active reservations) so a cart
     * doesn't claim units another cart is already holding.
     */
    public function availability(Request $request, string $product): JsonResponse
    {
        $tenant = $this->resolveAggregatorTenant($request);

        if ($tenant && $tenant->getTenantKey() === tenant()?->getTenantKey()) {
            return $this->respondWithAvailability($product);
        }

        if ($tenant) {
            return $tenant->run(fn () => $this->respondWithAvailability($product));
        }

        return $this->respondWithAvailability($product);
    }

    private function respondWithAvailability(string $productId): JsonResponse
    {
        $p = Product::query()->where('id', $productId)->first();
        abort_unless($p && $p->is_active, 404);

        $warehouses = Warehouse::query()->orderBy('code')->get();

        $breakdown = $warehouses
            ->map(function (Warehouse $w) use ($p) {
                $physical = $this->stock->getPhysicalStock($p->id, $w->id);
                $net      = $this->stock->getNetAvailableStock($p->id, $w->id);
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
            'productId'          => $p->id,
            'sku'                => $p->sku,
            'totalAvailable'     => (float) $breakdown->sum('availableStock'),
            'warehouseBreakdown' => $breakdown,
        ]);
    }

    private function isAggregator(): bool
    {
        $handle = tenant()?->getTenantKey();
        return $handle && in_array($handle, self::AGGREGATOR_HANDLES, true);
    }

    /**
     * On aggregator, the caller must say which tenant's product they want
     * via `?tenant=<handle>`. Returns the resolved tenant for the caller
     * to `->run()` inside. On non-aggregator paths returns null so the
     * controller continues with the request's natural tenant context.
     */
    private function resolveAggregatorTenant(Request $request): ?CentralTenant
    {
        if (! $this->isAggregator()) {
            return null;
        }

        $handle = (string) $request->query('tenant', '');
        abort_if($handle === '', 400, 'Aggregator catalog requires a `tenant` query parameter.');

        return CentralTenant::query()->where('handle', $handle)->firstOrFail();
    }

    /**
     * Public projection — never expose cost, average_cost, last_cost, or
     * cross-warehouse aggregate quantities. Just what a shopper needs.
     */
    private function transform(Product $p, mixed $ledgerQty = null): array
    {
        $p->loadMissing('category', 'variants');

        // Stock from the ledger; column fallback only if no batched lookup happened.
        $available = (float) ($ledgerQty ?? $p->total_quantity ?? 0);

        return [
            'id'              => $p->id,
            'sku'             => $p->sku,
            'name'            => $p->name,
            'description'     => $p->description,
            'descriptionLong' => $p->description_long,
            'productType'     => $p->product_type,
            'unitPrice'       => (float) $p->unit_price,
            'imagePath'       => $p->image_path,
            // Resolved public URL — matches the admin ProductResource recipe.
            // `image_path` is a relative storage path (e.g. `products/demo/abc.webp`);
            // serving it directly would 404 from the SPA.
            'imageUrl'        => $p->image_path ? asset('storage/' . $p->image_path) : null,
            'categoryId'      => $p->category_id,
            'categoryName'    => $p->category?->name,
            'categorySlug'    => $p->category?->slug,
            'availableStock'  => $available,
            'inStock'         => $available > 0,
            'createdAt'       => optional($p->created_at)->toIso8601String(),
            'updatedAt'       => optional($p->updated_at)->toIso8601String(),
            'variants'        => $p->variants->where('is_active', true)->map(fn ($v) => [
                'id'         => $v->id,
                'sku'        => $v->sku,
                'name'       => $v->name,
                'attributes' => $v->attributes,
                'price'      => $v->unit_price !== null ? (float) $v->unit_price : null,
            ])->values(),
        ];
    }
}
