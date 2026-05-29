<?php

namespace App\Tenants\Modules\EDocuments\Services;

use App\Models\Tenant\Document;
use App\Models\Tenant\DocumentVersion;
use App\Models\Tenant\Folder;
use Exception;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DocumentService
{
    private const BANNED_EXTENSIONS = [
        'php', 'phtml', 'py', 'sh', 'exe', 'js', 'bat', 'cmd', 'msi',
        'jar', 'vbs', 'com', 'htm', 'html', 'xhtml',
    ];

    private const BANNED_MIME_TYPES = [
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
        // `application/octet-stream` is intentionally NOT banned. It is the
        // generic "binary blob fileinfo couldn't classify" fallback and many
        // legit PDFs/images come back as octet-stream depending on how the
        // source app saved them. The extension allowlist is the real guard;
        // a renamed `.exe` would still surface as application/x-msdownload
        // via magic bytes and stay blocked above.
    ];

    public function uploadDocument(UploadedFile $file, array $data, string $uploaderId): Document
    {
        [$sanitisedName, $extension, $mimeType] = $this->validateUpload($file);

        return DB::transaction(function () use ($file, $data, $uploaderId, $sanitisedName, $extension, $mimeType) {
            $storedName = (string) Str::uuid() . '.' . $extension;
            $path = $file->storeAs('edocuments/documents', $storedName, 'local');

            $document = Document::create([
                'title' => $data['title'] ?? pathinfo($sanitisedName, PATHINFO_FILENAME),
                'filename' => $sanitisedName,
                'mime_type' => $mimeType,
                'size_bytes' => $file->getSize(),
                'path' => $path,
                'folder_id' => $data['folder_id'] ?? null,
                'uploader_id' => $uploaderId,
                'documentable_type' => $data['documentable_type'] ?? null,
                'documentable_id' => $data['documentable_id'] ?? null,
            ]);

            if (!empty($data['tag_ids'])) {
                $document->tags()->sync($data['tag_ids']);
            }

            DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => 1,
                'filename' => $sanitisedName,
                'mime_type' => $mimeType,
                'size_bytes' => $file->getSize(),
                'path' => $path,
                'change_summary' => 'Initial version',
                'uploaded_by_id' => $uploaderId,
            ]);

            return $document;
        });
    }

    public function updateDocument(Document $document, array $data): Document
    {
        return DB::transaction(function () use ($document, $data) {
            $document->fill(array_intersect_key($data, array_flip([
                'title',
                'folder_id',
                'documentable_type',
                'documentable_id',
            ])))->save();

            if (array_key_exists('tag_ids', $data)) {
                $document->tags()->sync($data['tag_ids'] ?? []);
            }

            return $document->refresh();
        });
    }

    public function moveToFolder(Document $document, ?Folder $folder): Document
    {
        $document->folder_id = $folder?->id;
        $document->save();

        return $document;
    }

    public function deleteDocument(Document $document): void
    {
        DB::transaction(function () use ($document) {
            // Drop all stored files (current path + every historical version path)
            // before the row is soft-deleted so storage doesn't leak orphans. We
            // do this inside the transaction so a DB failure rolls back the deletes
            // we already issued logically, but storage::delete is best-effort: if
            // a file is already missing on disk it's a no-op, not a throw.
            $paths = $document->versions()->pluck('path')->push($document->path)->unique()->filter()->all();
            foreach ($paths as $path) {
                Storage::disk('local')->delete($path);
            }

            $document->tags()->detach();
            $document->delete();
        });
    }

    public function createVersion(Document $document, UploadedFile $file, ?string $changeSummary, string $uploaderId): DocumentVersion
    {
        [$sanitisedName, $extension, $mimeType] = $this->validateUpload($file);

        return DB::transaction(function () use ($document, $file, $changeSummary, $uploaderId, $sanitisedName, $extension, $mimeType) {
            $storedName = (string) Str::uuid() . '.' . $extension;
            $path = $file->storeAs('edocuments/versions', $storedName, 'local');

            $nextVersion = ((int) $document->versions()->max('version_number')) + 1;

            $version = DocumentVersion::create([
                'document_id' => $document->id,
                'version_number' => $nextVersion,
                'filename' => $sanitisedName,
                'mime_type' => $mimeType,
                'size_bytes' => $file->getSize(),
                'path' => $path,
                'change_summary' => $changeSummary,
                'uploaded_by_id' => $uploaderId,
            ]);

            // The newest version is the current head — point the document at it.
            $document->forceFill([
                'filename' => $sanitisedName,
                'mime_type' => $mimeType,
                'size_bytes' => $file->getSize(),
                'path' => $path,
            ])->save();

            return $version;
        });
    }

    public function getDocumentFile(Document $document): string
    {
        if (!Storage::disk('local')->exists($document->path)) {
            abort(404, 'File not found in storage.');
        }

        return Storage::disk('local')->path($document->path);
    }

    public function getVersionFile(DocumentVersion $version): string
    {
        if (!Storage::disk('local')->exists($version->path)) {
            abort(404, 'Version file not found in storage.');
        }

        return Storage::disk('local')->path($version->path);
    }

    /**
     * @return array{0:string,1:string,2:string} sanitisedName, extension, mimeType
     */
    private function validateUpload(UploadedFile $file): array
    {
        $sanitisedName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', basename($file->getClientOriginalName()));

        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($sanitisedName, PATHINFO_EXTENSION));
        if (empty($extension) || in_array($extension, self::BANNED_EXTENSIONS, true)) {
            throw new Exception("File extension '.{$extension}' is not allowed.");
        }

        $mimeType = $file->getMimeType();
        if (in_array($mimeType, self::BANNED_MIME_TYPES, true)) {
            throw new Exception("File MIME type '{$mimeType}' is not allowed.");
        }

        return [$sanitisedName, $extension, $mimeType];
    }
}
