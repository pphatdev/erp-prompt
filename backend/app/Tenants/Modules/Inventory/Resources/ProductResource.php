<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'image_url' => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'sku' => $this->sku,
            'name' => $this->name,
            'product_type' => $this->product_type,
            'category_id' => $this->category_id,
            'category' => $this->whenLoaded('category', fn () => $this->category ? [
                'id'    => $this->category->id,
                'name'  => $this->category->name,
                'slug'  => $this->category->slug,
                'color' => $this->category->color,
            ] : null),
            'description' => $this->description,
            'description_long' => $this->description_long,
            'unit_price' => (float) $this->unit_price,
            'minimum_stock_level' => $this->minimum_stock_level,
            'is_active' => (bool) $this->is_active,
            'current_stock' => $this->currentStock(),
            // Inventory valuation — populated by StockService on every receipt.
            // `total_quantity` is the denormalized running on-hand across all
            // warehouses (cheaper than summing stock_movements on every list).
            'total_quantity' => (float) $this->total_quantity,
            'average_cost'   => (float) $this->average_cost,
            'last_cost'      => $this->last_cost !== null ? (float) $this->last_cost : null,
            // Variants are eager-loaded by the controller (`with('variants')`)
            // so the quotation builder can populate its inline variant
            // dropdown without an extra round trip per product.
            'variants' => $this->whenLoaded('variants', fn () => $this->variants->map(fn ($v) => [
                'id' => $v->id,
                'product_id' => $v->product_id,
                'sku' => $v->sku,
                'name' => $v->name,
                'unit_price' => (float) $v->unit_price,
                'attributes' => $v->attributes,
                'is_active' => (bool) $v->is_active,
            ])),
            'modules' => $this->whenLoaded('modules', fn () => $this->modules->map(fn ($m) => [
                'id'     => $m->id,
                'slug'   => $m->slug,
                'name'   => $m->name,
                'prefix' => $m->prefix,
                'icon'   => $m->icon,
            ])),
        ];
    }
}
