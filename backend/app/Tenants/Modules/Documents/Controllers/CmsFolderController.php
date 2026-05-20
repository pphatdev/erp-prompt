<?php

namespace App\Tenants\Modules\Documents\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\CmsFolder;
use App\Tenants\Modules\Documents\Resources\CmsFolderResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CmsFolderController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $folders = CmsFolder::whereNull('parent_id')->with('children', 'documents')->get();
        return CmsFolderResource::collection($folders);
    }

    public function store(Request $request): CmsFolderResource
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'parent_id' => 'nullable|exists:cms_folders,id',
        ]);

        $folder = CmsFolder::create($data);
        return new CmsFolderResource($folder);
    }

    public function show(CmsFolder $folder): CmsFolderResource
    {
        return new CmsFolderResource($folder->load('children', 'documents.latestVersion'));
    }
}
