<?php

namespace App\Tenants\Modules\EDocuments\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentShare;
use App\Tenants\Modules\EDocuments\Requests\CreateShareLinkRequest;
use App\Tenants\Modules\EDocuments\Resources\DocumentShareResource;
use App\Tenants\Modules\EDocuments\Services\DocumentService;
use App\Tenants\Modules\EDocuments\Services\ShareLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ShareController extends Controller
{
    public function __construct(
        private readonly ShareLinkService $shareLinkService,
        private readonly DocumentService $documentService,
    ) {
    }

    public function index(Document $document): JsonResponse
    {
        $this->authorize('share', $document);

        return response()->json([
            'data' => DocumentShareResource::collection($document->shares()->latest()->get()),
        ]);
    }

    public function store(CreateShareLinkRequest $request, Document $document): DocumentShareResource
    {
        $this->authorize('share', $document);

        $share = $this->shareLinkService->createLink(
            $document,
            $request->validated(),
            $request->user()->id,
        );

        return new DocumentShareResource($share);
    }

    public function destroy(DocumentShare $share): JsonResponse
    {
        $this->authorize('share', $share->document);

        $this->shareLinkService->revoke($share);

        return response()->json(null, 204);
    }

    /**
     * Public — no auth, no tenant context. Resolution looks the token up
     * directly; ShareLinkService throws 410/403/429 as appropriate.
     */
    public function publicShow(Request $request, string $token): JsonResponse
    {
        $share = $this->shareLinkService->resolve($token, $request->query('password'));

        $document = Document::withoutGlobalScopes()->findOrFail($share->document_id);

        return response()->json([
            'data' => [
                'title' => $document->title,
                'filename' => $document->filename,
                'mimeType' => $document->mime_type,
                'sizeBytes' => (int) $document->size_bytes,
                'expiresAt' => optional($share->expires_at)->toIso8601String(),
                'downloadsRemaining' => $share->max_downloads !== null
                    ? max(0, $share->max_downloads - $share->downloads_count)
                    : null,
            ],
        ]);
    }

    public function publicDownload(Request $request, string $token): BinaryFileResponse
    {
        $share = $this->shareLinkService->resolve($token, $request->query('password'));

        $document = Document::withoutGlobalScopes()->findOrFail($share->document_id);

        $this->shareLinkService->recordDownload($share);

        return response()->download(
            $this->documentService->getDocumentFile($document),
            $document->filename,
        );
    }
}
