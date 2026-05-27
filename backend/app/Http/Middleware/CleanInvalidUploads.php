<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Response;

class CleanInvalidUploads
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->files->count() > 0) {
            $cleanedFiles = $this->sanitizeFiles($request->files->all());
            $request->files->replace($cleanedFiles);
        }

        return $next($request);
    }

    /**
     * Recursively traverse and clean invalid uploaded files from the request.
     */
    private function sanitizeFiles(array $files): array
    {
        foreach ($files as $key => $value) {
            if (is_array($value)) {
                $files[$key] = $this->sanitizeFiles($value);
            } elseif ($value instanceof UploadedFile) {
                // If there's an error during PHP file upload (e.g. exceeds upload_max_filesize)
                // or the real path is unresolvable (e.g. empty tmp_name on Windows),
                // remove it from the request to prevent Nyholm/PSR-7 factories from crashing 
                // with a 500 error when converting the request (e.g. during Passport OAuth validation).
                if ($value->getError() !== UPLOAD_ERR_OK || !$value->getRealPath()) {
                    unset($files[$key]);
                }
            }
        }

        return $files;
    }
}
