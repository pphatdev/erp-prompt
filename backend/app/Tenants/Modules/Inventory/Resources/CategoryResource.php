<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'slug'        => $this->slug,
            'name'        => $this->name,
            'description' => $this->description,
            'color'       => $this->color,
            'sortOrder'   => (int) $this->sort_order,
            'isActive'    => (bool) $this->is_active,
            'parentId'    => $this->parent_id,
            'parent'      => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id'   => $this->parent->id,
                'name' => $this->parent->name,
                'slug' => $this->parent->slug,
            ] : null),
            'children'    => $this->whenLoaded('children', fn () => self::collection($this->children)),
            'productsCount' => $this->when(isset($this->products_count), fn () => (int) $this->products_count),
            'createdAt'   => optional($this->created_at)->toISOString(),
            'updatedAt'   => optional($this->updated_at)->toISOString(),
        ];
    }
}
