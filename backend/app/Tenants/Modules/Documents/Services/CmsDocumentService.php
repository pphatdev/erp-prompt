<?php

namespace App\Tenants\Modules\Documents\Services;

use App\Models\Tenant\CmsDocument;
use App\Models\Tenant\CmsDocumentVersion;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Exception;

class CmsDocumentService
{
    /**
     * Create a new document with its first version.
     */
    public function createDocument(array $data, UploadedFile $file, string $userId): CmsDocument
    {
        return DB::transaction(function () use ($data, $file, $userId) {
            $document = CmsDocument::create([
                'title' => $data['title'] ?? pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
                'cms_folder_id' => $data['cms_folder_id'] ?? null,
                'retention_expiry' => $data['retention_expiry'] ?? null,
            ]);

            $this->addVersion($document, $file, $userId, 'Initial version');

            return $document;
        });
    }

    /**
     * Lock a document for editing.
     */
    public function checkout(CmsDocument $document, string $userId): CmsDocument
    {
        if ($document->locked_by_id) {
            throw new Exception("Document is already locked by another user.");
        }

        $document->update([
            'locked_by_id' => $userId,
            'locked_at' => now(),
        ]);

        return $document;
    }

    /**
     * Release lock and optionally add a new version.
     */
    public function checkin(CmsDocument $document, string $userId, ?UploadedFile $file = null, ?string $changeSummary = null): CmsDocument
    {
        if ($document->locked_by_id !== $userId) {
            throw new Exception("You cannot check in a document locked by someone else.");
        }

        return DB::transaction(function () use ($document, $userId, $file, $changeSummary) {
            if ($file) {
                $this->addVersion($document, $file, $userId, $changeSummary);
            }

            $document->update([
                'locked_by_id' => null,
                'locked_at' => null,
            ]);

            return $document;
        });
    }

    /**
     * Internal method to add a version.
     */
    protected function addVersion(CmsDocument $document, UploadedFile $file, string $userId, ?string $changeSummary = null): CmsDocumentVersion
    {
        // 1. Sanitize original filename and check path traversal
        $originalName = basename($file->getClientOriginalName());
        $originalName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $originalName);
        
        // 2. Validate file extension
        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION));
        $bannedExtensions = ['php', 'phtml', 'py', 'sh', 'exe', 'js', 'bat', 'cmd', 'msi', 'jar', 'vbs', 'com', 'htm', 'html', 'xhtml'];
        if (empty($extension) || in_array($extension, $bannedExtensions)) {
            throw new Exception("File extension '.{$extension}' is not allowed.");
        }

        // 3. Validate MIME type
        $mimeType = $file->getMimeType(); // verified server-side via fileinfo magic bytes
        $bannedMimeTypes = [
            'text/x-php',
            'application/x-php',
            'application/x-httpd-php',
            'application/x-sh',
            'application/x-bash',
            'text/javascript',
            'application/javascript',
            'text/html',
            'application/xhtml+xml',
            'application/x-msdownload',
            'application/octet-stream',
        ];
        if (in_array($mimeType, $bannedMimeTypes)) {
            throw new Exception("File MIME type '{$mimeType}' is not allowed.");
        }

        $filename = (string) Str::uuid() . '.' . $extension;
        
        // Relies on FilesystemTenancyBootstrapper to automatically re-root the disk
        $path = $file->storeAs("documents/cms", $filename, 'local');

        $nextVersion = $document->versions()->max('version_number') + 1;

        return CmsDocumentVersion::create([
            'cms_document_id' => $document->id,
            'version_number' => $nextVersion,
            'filename' => $originalName,
            'mime_type' => $mimeType,
            'size_bytes' => $file->getSize(),
            'path' => $path,
            'change_summary' => $changeSummary,
            'uploaded_by_id' => $userId,
        ]);
    }
}
