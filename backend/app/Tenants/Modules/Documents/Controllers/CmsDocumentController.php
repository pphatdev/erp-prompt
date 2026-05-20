<?php

namespace App\Tenants\Modules\Documents\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\CmsDocument;
use App\Tenants\Modules\Documents\Resources\CmsDocumentResource;
use App\Tenants\Modules\Documents\Services\CmsDocumentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CmsDocumentController extends Controller
{
    use Paginates;

    protected $documentService;

    public function __construct(CmsDocumentService $documentService)
    {
        $this->documentService = $documentService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = CmsDocument::query()->with('lockedBy', 'latestVersion')
            ->orderBy('created_at', 'desc');

        if ($request->has('cms_folder_id')) {
            $query->where('cms_folder_id', $request->input('cms_folder_id'));
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(CmsDocumentResource::class, $paginator, $request);
    }

    public function store(Request $request): CmsDocumentResource
    {
        $request->validate([
            'file' => 'required|file|max:20480', // 20MB limit
            'title' => 'nullable|string|max:255',
            'cms_folder_id' => 'nullable|exists:cms_folders,id',
            'retention_expiry' => 'nullable|date',
        ]);

        $document = $this->documentService->createDocument(
            $request->except('file'),
            $request->file('file'),
            $request->user()->id
        );

        return new CmsDocumentResource($document->load('latestVersion'));
    }

    public function show(CmsDocument $document): CmsDocumentResource
    {
        return new CmsDocumentResource($document->load('versions.uploader', 'lockedBy'));
    }

    public function checkout(Request $request, CmsDocument $document): CmsDocumentResource
    {
        $lockedDoc = $this->documentService->checkout($document, $request->user()->id);
        return new CmsDocumentResource($lockedDoc->load('lockedBy'));
    }

    public function checkin(Request $request, CmsDocument $document): CmsDocumentResource
    {
        $request->validate([
            'file' => 'nullable|file|max:20480',
            'change_summary' => 'nullable|string',
        ]);

        $unlockedDoc = $this->documentService->checkin(
            $document,
            $request->user()->id,
            $request->file('file'),
            $request->input('change_summary')
        );

        return new CmsDocumentResource($unlockedDoc->load('latestVersion', 'lockedBy'));
    }
}
