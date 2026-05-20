<?php

namespace App\Tenants\Modules\EDocuments\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Document;
use App\Tenants\Modules\EDocuments\Resources\DocumentResource;
use App\Tenants\Modules\EDocuments\Services\DocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DocumentController extends Controller
{
    use Paginates;

    protected $documentService;

    public function __construct(DocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = Document::query()->with('uploader', 'tags', 'folder')
            ->orderBy('created_at', 'desc');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                  ->orWhere('filename', 'like', "%{$search}%");
            });
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(DocumentResource::class, $paginator, $request);
    }

    /**
     * Store a newly uploaded document.
     */
    public function store(Request $request): DocumentResource
    {
        $request->validate([
            'file' => 'required|file|max:10240', // 10MB limit
            'title' => 'nullable|string|max:255',
            'folder_id' => 'nullable|exists:folders,id',
            'tag_ids' => 'nullable|array',
            'tag_ids.*' => 'exists:tags,id',
        ]);

        $document = $this->documentService->uploadDocument(
            $request->file('file'),
            $request->except('file'),
            $request->user()->id
        );

        return new DocumentResource($document->load('uploader', 'tags'));
    }

    /**
     * Display the specified document details.
     */
    public function show(Document $document): DocumentResource
    {
        return new DocumentResource($document->load('uploader', 'tags', 'folder'));
    }

    /**
     * Download the specified document file.
     */
    public function download(Document $document)
    {
        $filePath = $this->documentService->getDocumentFile($document);
        return response()->download($filePath, $document->filename);
    }
}
