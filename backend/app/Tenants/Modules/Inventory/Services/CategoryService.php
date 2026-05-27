<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Services;

use App\Models\Tenant\Category;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class CategoryService
{
    public function buildQuery(): Builder
    {
        return Category::query()
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function create(array $data): Category
    {
        $data['slug'] = $this->resolveSlug($data['slug'] ?? null, $data['name']);
        $this->assertSlugUnique($data['slug']);
        $this->assertParentReachable($data['parent_id'] ?? null);

        return Category::create($data)->fresh(['parent']);
    }

    public function update(Category $c, array $data): Category
    {
        if (array_key_exists('slug', $data) || array_key_exists('name', $data)) {
            $data['slug'] = $this->resolveSlug(
                $data['slug'] ?? $c->slug,
                $data['name'] ?? $c->name
            );
            $this->assertSlugUnique($data['slug'], $c->id);
        }

        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== $c->parent_id) {
            if ($data['parent_id'] === $c->id) {
                throw new DomainException('A category cannot be its own parent.');
            }
            $this->assertParentReachable($data['parent_id']);
            $this->assertNotMovingUnderDescendant($c, $data['parent_id']);
        }

        $c->update($data);
        return $c->fresh(['parent']);
    }

    public function archive(Category $c): Category
    {
        if ($c->children()->exists()) {
            throw new DomainException(
                "Cannot archive '{$c->name}' — it still has sub-categories. Re-parent or archive them first."
            );
        }
        $productsCount = $c->products()->count();
        if ($productsCount > 0) {
            throw new DomainException(
                "Cannot archive '{$c->name}' — {$productsCount} product(s) still belong to it. Re-assign them first."
            );
        }

        $c->update(['is_active' => false]);
        $c->delete();
        return $c;
    }

    public function tree(): array
    {
        $roots = Category::query()
            ->whereNull('parent_id')
            ->with(['children' => fn ($q) => $q->with('children')])
            ->withCount('products')
            ->orderBy('sort_order')->orderBy('name')
            ->get();

        return CategoryService::collectionToArray($roots);
    }

    public static function collectionToArray($collection): array
    {
        return $collection->map(fn (Category $c) => [
            'id'             => $c->id,
            'slug'           => $c->slug,
            'name'           => $c->name,
            'description'    => $c->description,
            'color'          => $c->color,
            'sortOrder'      => (int) $c->sort_order,
            'isActive'       => (bool) $c->is_active,
            'parentId'       => $c->parent_id,
            'productsCount'  => (int) ($c->products_count ?? 0),
            'children'       => self::collectionToArray($c->children),
        ])->all();
    }

    private function resolveSlug(?string $slug, string $name): string
    {
        $slug = $slug ?: Str::slug($name);
        return Str::limit($slug, 120, '');
    }

    private function assertSlugUnique(string $slug, ?string $ignoreId = null): void
    {
        $exists = Category::query()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        if ($exists) {
            throw new DomainException("A category with slug '{$slug}' already exists.");
        }
    }

    private function assertParentReachable(?string $parentId): void
    {
        if ($parentId === null) return;
        if (!Category::query()->whereKey($parentId)->exists()) {
            throw new DomainException('Parent category not found.');
        }
    }

    // Prevents a cycle when moving a node beneath its own descendant.
    private function assertNotMovingUnderDescendant(Category $c, ?string $newParentId): void
    {
        if ($newParentId === null) return;
        $current = Category::query()->find($newParentId);
        while ($current !== null) {
            if ($current->id === $c->id) {
                throw new DomainException('Cannot move a category beneath one of its own descendants.');
            }
            $current = $current->parent_id ? Category::query()->find($current->parent_id) : null;
        }
    }
}
