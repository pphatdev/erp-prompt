<?php

namespace App\Tenants\Modules\EDocuments\Services;

use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentShare;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;

class ShareLinkService
{
    public function createLink(Document $document, array $data, string $creatorId): DocumentShare
    {
        return DocumentShare::create([
            'document_id' => $document->id,
            'token' => $this->generateToken(),
            'expires_at' => !empty($data['expires_at']) ? Carbon::parse($data['expires_at']) : null,
            'password_hash' => !empty($data['password']) ? Hash::make($data['password']) : null,
            'max_downloads' => $data['max_downloads'] ?? null,
            'created_by' => $creatorId,
        ]);
    }

    /**
     * Resolve a share token for public access. Throws HTTP-shaped exceptions so
     * the controller can let them bubble straight to the standard handler:
     *   410 Gone           — expired or revoked link
     *   403 Forbidden      — wrong password
     *   429 Too Many Requests — download cap reached
     */
    public function resolve(string $token, ?string $password = null): DocumentShare
    {
        // Bypass the global tenant scope — public share resolution must work
        // when no tenant context has been initialised yet (the public route
        // sits outside the InitializeTenancyByHandle middleware group).
        $share = DocumentShare::withoutGlobalScopes()->where('token', $token)->first();

        if (!$share || $share->trashed()) {
            throw new GoneHttpException('Share link is no longer valid.');
        }

        if ($share->isExpired()) {
            throw new GoneHttpException('Share link has expired.');
        }

        if ($share->password_hash !== null) {
            if ($password === null || !Hash::check($password, $share->password_hash)) {
                throw new AccessDeniedHttpException('Invalid share password.');
            }
        }

        if ($share->isDownloadCapReached()) {
            throw new TooManyRequestsHttpException(null, 'Share download cap reached.');
        }

        return $share;
    }

    public function recordDownload(DocumentShare $share): void
    {
        $share->increment('downloads_count');
    }

    public function revoke(DocumentShare $share): void
    {
        $share->delete();
    }

    private function generateToken(): string
    {
        do {
            $token = Str::random(64);
        } while (DocumentShare::withoutGlobalScopes()->where('token', $token)->exists());

        return $token;
    }
}
