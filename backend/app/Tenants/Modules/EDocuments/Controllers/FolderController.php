<?php

namespace App\Tenants\Modules\EDocuments\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Folder;
use App\Tenants\Modules\EDocuments\Requests\MoveFolderRequest;
use App\Tenants\Modules\EDocuments\Requests\StoreFolderRequest;
use App\Tenants\Modules\EDocuments\Requests\UpdateFolderRequest;
use App\Tenants\Modules\EDocuments\Resources\FolderResource;
use App\Tenants\Modules\EDocuments\Services\FolderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FolderController extends Controller
{
    use Paginates;

    public function __construct(private readonly FolderService $folderService)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Folder::class);

        $query = Folder::query()
            ->withCount(['children', 'documents'])
            ->orderBy('name');

        // Default to root level when no parentId is specified so the client can
        // lazily expand subtrees as the user navigates. Pass parentId=null
        // explicitly via ?root=1 if you want only orphan folders.
        if ($request->has('parentId')) {
            $parentId = $request->query('parentId');
            $parentId === '' || $parentId === null
                ? $query->whereNull('parent_id')
                : $query->where('parent_id', $parentId);
        } elseif ($request->boolean('root')) {
            $query->whereNull('parent_id');
        }

        if ($search = $request->query('search')) {
            $query->where('name', 'ilike', "%{$search}%");
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(FolderResource::class, $paginator, $request);
    }

    public function store(StoreFolderRequest $request): FolderResource
    {
        $this->authorize('create', Folder::class);

        $folder = $this->folderService->createFolder($request->validated());

        return new FolderResource($folder->loadCount(['children', 'documents']));
    }

    public function show(Folder $folder): FolderResource
    {
        $this->authorize('view', $folder);

        return new FolderResource(
            $folder->load(['children', 'documents.uploader', 'documents.tags'])
                ->loadCount(['children', 'documents']),
        );
    }

    public function update(UpdateFolderRequest $request, Folder $folder): FolderResource
    {
        $this->authorize('update', $folder);

        if ($request->filled('name')) {
            $this->folderService->renameFolder($folder, $request->input('name'));
        }

        return new FolderResource($folder->fresh()->loadCount(['children', 'documents']));
    }

    public function destroy(Request $request, Folder $folder): JsonResponse
    {
        $this->authorize('delete', $folder);

        $this->folderService->deleteFolder($folder, $request->boolean('force'));

        return response()->json(null, 204);
    }

    public function move(MoveFolderRequest $request, Folder $folder): FolderResource
    {
        $this->authorize('update', $folder);

        $newParent = $request->input('parent_id')
            ? Folder::findOrFail($request->input('parent_id'))
            : null;

        $folder = $this->folderService->moveFolder($folder, $newParent);

        return new FolderResource($folder->loadCount(['children', 'documents']));
    }
}
