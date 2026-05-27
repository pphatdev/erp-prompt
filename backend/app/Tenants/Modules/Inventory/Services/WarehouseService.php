<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Services;

use App\Models\Tenant\StockMovement;
use App\Models\Tenant\Warehouse;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class WarehouseService
{
    public function buildQuery(): Builder
    {
        return Warehouse::query()->with('manager')->orderBy('name');
    }

    public function create(array $data): Warehouse
    {
        if (!empty($data['code']) && Warehouse::where('code', $data['code'])->exists()) {
            throw new DomainException("A warehouse with code '{$data['code']}' already exists.");
        }
        return Warehouse::create($data)->load('manager');
    }

    public function update(Warehouse $w, array $data): Warehouse
    {
        if (!empty($data['code']) && $data['code'] !== $w->code
            && Warehouse::where('code', $data['code'])->where('id', '!=', $w->id)->exists()) {
            throw new DomainException("A warehouse with code '{$data['code']}' already exists.");
        }
        $w->update($data);
        return $w->fresh('manager');
    }

    /**
     * Soft-archive. Blocked when on-hand stock is non-zero — operator must
     * transfer stock out first so we don't strand inventory in an orphan
     * warehouse.
     */
    public function archive(Warehouse $w): Warehouse
    {
        $onHand = (int) StockMovement::where('warehouse_id', $w->id)->sum('quantity');
        if ($onHand !== 0) {
            throw new DomainException(
                "Cannot archive warehouse '{$w->name}' — it still holds {$onHand} unit(s) on hand. " .
                'Transfer stock to another warehouse first.'
            );
        }

        $w->update(['is_active' => false]);
        $w->delete();
        return $w;
    }

    /**
     * Sum on-hand quantity per product within a warehouse. Returns an array
     * of `{product_id, name, sku, on_hand}` shaped rows — feeds the bin-detail
     * panel on the Warehouses page.
     */
    public function stockByProduct(Warehouse $w): array
    {
        return DB::table('stock_movements as sm')
            ->join('products as p', 'p.id', '=', 'sm.product_id')
            ->where('sm.warehouse_id', $w->id)
            ->groupBy('sm.product_id', 'p.name', 'p.sku')
            ->select([
                'sm.product_id',
                'p.name',
                'p.sku',
                DB::raw('SUM(sm.quantity) as on_hand'),
            ])
            ->having(DB::raw('SUM(sm.quantity)'), '!=', 0)
            ->orderBy('p.name')
            ->get()
            ->map(fn ($r) => [
                'productId' => $r->product_id,
                'name'      => $r->name,
                'sku'       => $r->sku,
                'onHand'    => (int) $r->on_hand,
            ])
            ->all();
    }
}
