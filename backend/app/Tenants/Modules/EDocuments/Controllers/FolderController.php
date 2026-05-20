<?php

namespace App\Tenants\Modules\EDocuments\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Folder;
use App\Tenants\Modules\EDocuments\Resources\FolderResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FolderController extends Controller
{
    /**
     * Display a listing of root folders.
     */
    public function index(): AnonymousResourceCollection
    {
        $folders = Folder::whereNull('parent_id')->with('children', 'documents')->get();
        return FolderResource::collection($folders);
    }

    /**
     * Store a newly created folder.
     */
    public function store(Request $request): FolderResource
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:folders,id',
        ]);

        $folder = Folder::create($data);
        return new FolderResource($folder);
    }

    /**
     * Display the specified folder and its contents.
     */
    public function show(Folder $folder): FolderResource
    {
        return new FolderResource($folder->load('children', 'documents'));
    }
}
