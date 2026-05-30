<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Asset;
use App\Models\Tenant\AssetDisposal;
use App\Tenants\Modules\Assets\Resources\AssetDisposalResource;
use App\Tenants\Modules\Assets\Services\DisposalService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DisposalController extends Controller
{
    use Paginates;

    public function __construct(private readonly DisposalService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $query = AssetDisposal::query()->orderByDesc('disposal_date');
        if ($assetId = $request->query('assetId')) {
            $query->where('asset_id', $assetId);
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(AssetDisposalResource::class, $paginator, $request);
    }

    public function store(Request $request, Asset $asset): JsonResponse
    {
        $this->authorize('dispose', $asset);

        $validated = $request->validate([
            'disposalType' => 'required|string|in:sale,scrap,writeoff',
            'salePrice'    => 'sometimes|nullable|numeric|min:0',
            'disposalDate' => 'sometimes|nullable|date',
            'notes'        => 'sometimes|nullable|string',
        ]);

        $disposal = $this->service->dispose(
            asset: $asset,
            disposalType: $validated['disposalType'],
            salePrice: (float) ($validated['salePrice'] ?? 0),
            disposalDate: isset($validated['disposalDate']) ? Carbon::parse($validated['disposalDate']) : null,
            extra: ['notes' => $validated['notes'] ?? null],
        );

        return (new AssetDisposalResource($disposal))->response()->setStatusCode(201);
    }
}
