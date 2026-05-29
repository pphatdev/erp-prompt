<?php

namespace App\Tenants\Modules\EDocuments\Services;

use App\Models\Tenant\Folder;
use Exception;
use Illuminate\Support\Facades\DB;

class FolderService
{
    public function createFolder(array $data): Folder
    {
        return Folder::create([
            'name' => $data['name'],
            'parent_id' => $data['parent_id'] ?? null,
        ]);
    }

    public function renameFolder(Folder $folder, string $name): Folder
    {
        $folder->name = $name;
        $folder->save();

        return $folder;
    }

    public function moveFolder(Folder $folder, ?Folder $newParent): Folder
    {
        if ($newParent !== null) {
            if ($newParent->id === $folder->id) {
                throw new Exception('A folder cannot be its own parent.');
            }

            // Walk the ancestor chain on the proposed parent. If we hit the
            // current folder anywhere up the tree the move would create a cycle
            // (folder would become a descendant of itself). Cheap loop because
            // folder trees are typically shallow.
            $ancestor = $newParent;
            while ($ancestor !== null) {
                if ($ancestor->id === $folder->id) {
                    throw new Exception('Cannot move a folder into one of its descendants.');
                }
                $ancestor = $ancestor->parent;
            }
        }

        $folder->parent_id = $newParent?->id;
        $folder->save();

        return $folder;
    }

    public function deleteFolder(Folder $folder, bool $force = false): void
    {
        if (!$force) {
            if ($folder->children()->exists() || $folder->documents()->exists()) {
                throw new Exception('Folder is not empty. Pass force=true to delete its contents.');
            }
        }

        DB::transaction(function () use ($folder) {
            // Cascade through children + documents inside the transaction so a
            // failure rolls everything back. Soft deletes on the descendants are
            // fine — physical file cleanup happens via the per-document service.
            foreach ($folder->children as $child) {
                $this->deleteFolder($child, true);
            }
            foreach ($folder->documents as $document) {
                app(DocumentService::class)->deleteDocument($document);
            }

            $folder->delete();
        });
    }
}
