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
            'sku' => $this->sku,
            'name' => $this->name,
            'product_type' => $this->product_type,
            'description' => $this->description,
            'description_long' => $this->description_long,
            'unit_price' => (float) $this->unit_price,
            'minimum_stock_level' => $this->minimum_stock_level,
            'is_active' => (bool) $this->is_active,
            'current_stock' => $this->currentStock(),
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
