<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Settings\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ModuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'slug'        => $this->slug,
            'prefix'      => $this->prefix,
            'name'        => $this->name,
            'icon'        => $this->icon,
            'description' => $this->description,
            'route'       => $this->route,
            'group'       => $this->group,
            'sortOrder'   => $this->sort_order,
            'isActive'    => $this->is_active,
            'isCore'      => $this->is_core,
            'parentId'    => $this->parent_id,
            'children'    => ModuleResource::collection($this->whenLoaded('children')),
            'products'    => $this->whenLoaded('products', fn () => $this->products->map(fn ($p) => [
                'id'   => $p->id,
                'name' => $p->name,
                'sku'  => $p->sku,
            ])),
        ];
    }
}
