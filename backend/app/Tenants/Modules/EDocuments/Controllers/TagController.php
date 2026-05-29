<?php

namespace App\Tenants\Modules\EDocuments\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Tag;
use App\Tenants\Modules\EDocuments\Requests\StoreTagRequest;
use App\Tenants\Modules\EDocuments\Requests\UpdateTagRequest;
use App\Tenants\Modules\EDocuments\Resources\TagResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class TagController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Tag::class);

        $query = Tag::query()->withCount('documents')->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(TagResource::class, $paginator, $request);
    }

    public function store(StoreTagRequest $request): TagResource
    {
        $this->authorize('create', Tag::class);

        $data = $request->validated();
        $data['slug'] = $data['slug'] ?? Str::slug($data['name']);

        $tag = Tag::create($data);

        return new TagResource($tag);
    }

    public function show(Tag $tag): TagResource
    {
        $this->authorize('view', $tag);

        return new TagResource($tag->loadCount('documents'));
    }

    public function update(UpdateTagRequest $request, Tag $tag): TagResource
    {
        $this->authorize('update', $tag);

        $data = $request->validated();
        if (isset($data['name']) && !array_key_exists('slug', $data)) {
            $data['slug'] = Str::slug($data['name']);
        }

        $tag->update($data);

        return new TagResource($tag->loadCount('documents'));
    }

    public function destroy(Tag $tag): JsonResponse
    {
        $this->authorize('delete', $tag);

        $tag->documents()->detach();
        $tag->delete();

        return response()->json(null, 204);
    }
}
