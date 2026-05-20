<?php

namespace App\Tenants\Modules\EDocuments\Services;

use App\Models\Tenant\Document;
use App\Models\Tenant\Folder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DocumentService
{
    /**
     * Upload a new document to the isolated tenant storage.
     */
    public function uploadDocument(UploadedFile $file, array $data, $uploaderId): Document
    {
        // 1. Sanitize original filename and check path traversal
        $originalName = basename($file->getClientOriginalName());
        $originalName = preg_replace('/[^a-zA-Z0-9_\-\.]/', '_', $originalName);
        
        // 2. Validate file extension
        $extension = strtolower($file->getClientOriginalExtension() ?: pathinfo($originalName, PATHINFO_EXTENSION));
        $bannedExtensions = ['php', 'phtml', 'py', 'sh', 'exe', 'js', 'bat', 'cmd', 'msi', 'jar', 'vbs', 'com', 'htm', 'html', 'xhtml'];
        if (empty($extension) || in_array($extension, $bannedExtensions)) {
            throw new \Exception("File extension '.{$extension}' is not allowed.");
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
            throw new \Exception("File MIME type '{$mimeType}' is not allowed.");
        }

        return DB::transaction(function () use ($file, $data, $uploaderId, $originalName, $extension, $mimeType) {
            $filename = (string) Str::uuid() . '.' . $extension;
            
            // Relies on FilesystemTenancyBootstrapper to automatically re-root the disk
            $path = $file->storeAs("edocuments/documents", $filename, 'local');

            $document = Document::create([
                'title' => $data['title'] ?? pathinfo($originalName, PATHINFO_FILENAME),
                'filename' => $originalName,
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

            return $document;
        });
    }
    
    /**
     * Retrieve document file content.
     */
    public function getDocumentFile(Document $document)
    {
        if (!Storage::disk('local')->exists($document->path)) {
            abort(404, 'File not found in storage.');
        }
        
        return Storage::disk('local')->path($document->path);
    }
}
