<?php

namespace App\Tenants\Modules\EDocuments\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Document;
use App\Models\Tenant\Folder;
use App\Tenants\Modules\EDocuments\Requests\MoveDocumentRequest;
use App\Tenants\Modules\EDocuments\Requests\StoreDocumentRequest;
use App\Tenants\Modules\EDocuments\Requests\StoreVersionRequest;
use App\Tenants\Modules\EDocuments\Requests\UpdateDocumentRequest;
use App\Tenants\Modules\EDocuments\Resources\DocumentAcknowledgementResource;
use App\Tenants\Modules\EDocuments\Resources\DocumentResource;
use App\Tenants\Modules\EDocuments\Resources\DocumentVersionResource;
use App\Tenants\Modules\EDocuments\Services\AcknowledgementService;
use App\Tenants\Modules\EDocuments\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class DocumentController extends Controller
{
    use Paginates;

    public function __construct(
        private readonly DocumentService $documentService,
        private readonly AcknowledgementService $acknowledgementService,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Document::class);

        $query = Document::query()
            ->with(['uploader', 'tags', 'folder'])
            ->withCount('versions')
            ->orderBy('created_at', 'desc');

        if ($search = $request->query('search')) {
            // Postgres-friendly case-insensitive partial match on the two
            // indexable text fields — title and original filename.
            $query->where(function ($q) use ($search) {
                $q->where('title', 'ilike', "%{$search}%")
                  ->orWhere('filename', 'ilike', "%{$search}%");
            });
        }

        if ($folderId = $request->query('folderId')) {
            $query->where('folder_id', $folderId);
        }

        if ($request->boolean('rootOnly')) {
            $query->whereNull('folder_id');
        }

        $tagIds = (array) $request->query('tagIds', []);
        if (!empty($tagIds)) {
            $query->whereHas('tags', fn ($q) => $q->whereIn('tags.id', $tagIds));
        }

        if ($uploaderId = $request->query('uploaderId')) {
            $query->where('uploader_id', $uploaderId);
        }

        if ($mimeType = $request->query('mimeType')) {
            $query->where('mime_type', 'like', $mimeType . '%');
        }

        if ($from = $request->query('from')) {
            $query->where('created_at', '>=', $from);
        }

        if ($to = $request->query('to')) {
            $query->where('created_at', '<=', $to);
        }

        if ($polymorphicType = $request->query('polymorphicType')) {
            $query->where('documentable_type', $polymorphicType);
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(DocumentResource::class, $paginator, $request);
    }

    public function store(StoreDocumentRequest $request): DocumentResource
    {
        $this->authorize('create', Document::class);

        $document = $this->documentService->uploadDocument(
            $request->file('file'),
            $request->safe()->except('file'),
            $request->user()->id,
        );

        return new DocumentResource($document->load('uploader', 'tags', 'folder'));
    }

    public function show(Document $document): DocumentResource
    {
        $this->authorize('view', $document);

        return new DocumentResource(
            $document->loadMissing(['uploader', 'tags', 'folder'])->loadCount('versions'),
        );
    }

    public function update(UpdateDocumentRequest $request, Document $document): DocumentResource
    {
        $this->authorize('update', $document);

        $document = $this->documentService->updateDocument($document, $request->validated());

        return new DocumentResource($document->load('uploader', 'tags', 'folder'));
    }

    public function destroy(Document $document): JsonResponse
    {
        $this->authorize('delete', $document);

        $this->documentService->deleteDocument($document);

        return response()->json(null, 204);
    }

    public function move(MoveDocumentRequest $request, Document $document): DocumentResource
    {
        $this->authorize('update', $document);

        $folder = $request->input('folder_id')
            ? Folder::findOrFail($request->input('folder_id'))
            : null;

        $document = $this->documentService->moveToFolder($document, $folder);

        return new DocumentResource($document->load('uploader', 'tags', 'folder'));
    }

    public function download(Document $document): BinaryFileResponse
    {
        $this->authorize('view', $document);

        $filePath = $this->documentService->getDocumentFile($document);

        return response()->download($filePath, $document->filename);
    }

    public function versions(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        $versions = $document->versions()->with('uploader')->get();

        return response()->json([
            'data' => DocumentVersionResource::collection($versions),
        ]);
    }

    public function createVersion(StoreVersionRequest $request, Document $document): DocumentVersionResource
    {
        $this->authorize('update', $document);

        $version = $this->documentService->createVersion(
            $document,
            $request->file('file'),
            $request->input('change_summary'),
            $request->user()->id,
        );

        return new DocumentVersionResource($version->load('uploader'));
    }

    public function acknowledge(Request $request, Document $document): DocumentAcknowledgementResource
    {
        $this->authorize('acknowledge', $document);

        $ack = $this->acknowledgementService->acknowledge($document, $request->user());

        return new DocumentAcknowledgementResource($ack->load('user'));
    }

    public function acknowledgementSummary(Document $document): JsonResponse
    {
        $this->authorize('view', $document);

        return response()->json($this->acknowledgementService->summary($document));
    }
}
