<?php

namespace App\Tenants\Modules\Assets\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Asset;
use App\Tenants\Modules\Assets\Resources\AssetResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AssetController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $query = Asset::query()->orderBy('purchase_date', 'desc');

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(AssetResource::class, $paginator, $request);
    }

    public function store(Request $request): AssetResource
    {
        $data = $request->validate([
            'asset_tag' => 'required|string|unique:assets,asset_tag',
            'name' => 'required|string|max:255',
            'category' => 'required|string',
            'purchase_date' => 'required|date',
            'purchase_cost' => 'required|numeric|min:0',
            'salvage_value' => 'nullable|numeric|min:0',
            'useful_life_years' => 'required|integer|min:1',
            'depreciation_method' => 'nullable|string',
            'custodian_id' => 'nullable|exists:employees,id',
            'location_id' => 'nullable|string',
        ]);
        
        $data['current_value'] = $data['purchase_cost'];

        $asset = Asset::create($data);
        return new AssetResource($asset);
    }

    public function show(Asset $asset): AssetResource
    {
        return new AssetResource($asset->load('depreciationLogs'));
    }
}
