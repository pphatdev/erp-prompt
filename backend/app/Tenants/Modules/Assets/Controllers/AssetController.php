<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Assets\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Asset;
use App\Tenants\Modules\Assets\Resources\AssetResource;
use App\Tenants\Modules\Assets\Services\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AssetController extends Controller
{
    use Paginates;

    public function __construct(private readonly AssetService $service)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Asset::class);

        $query = Asset::query()->orderByDesc('purchase_date');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($condition = $request->query('condition')) {
            $query->where('condition', $condition);
        }
        if ($custodian = $request->query('custodianEmployeeId')) {
            $query->where('custodian_employee_id', $custodian);
        }
        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('asset_code', 'ilike', "%{$search}%")
                  ->orWhere('name', 'ilike', "%{$search}%")
                  ->orWhere('serial_number', 'ilike', "%{$search}%");
            });
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(AssetResource::class, $paginator, $request);
    }

    public function store(Request $request): JsonResponse
    {
        $this->authorize('create', Asset::class);

        $data = $this->validatePayload($request, false);
        $asset = $this->service->create($data);

        return (new AssetResource($asset))->response()->setStatusCode(201);
    }

    public function show(Asset $asset): AssetResource
    {
        $this->authorize('view', $asset);

        return new AssetResource(
            $asset->load(['depreciationLogs', 'revaluations', 'disposals'])
        );
    }

    public function update(Request $request, Asset $asset): AssetResource
    {
        $this->authorize('update', $asset);

        $data = $this->validatePayload($request, true);
        $asset = $this->service->update($asset, $data);

        return new AssetResource($asset);
    }

    public function destroy(Asset $asset): Response
    {
        $this->authorize('delete', $asset);

        $this->service->archive($asset);

        return response()->noContent();
    }

    /**
     * @return array<string, mixed>
     */
    private function validatePayload(Request $request, bool $isUpdate): array
    {
        // Accept camelCase from the frontend and map to model column names. The
        // request rule keys mirror the API contract; the returned array uses
        // snake_case so it matches the Eloquent fillable.
        $rules = [
            'name'                => ($isUpdate ? 'sometimes|required' : 'required') . '|string|max:255',
            'serialNumber'        => 'sometimes|nullable|string|max:255',
            'description'         => 'sometimes|nullable|string',
            'category'            => ($isUpdate ? 'sometimes|nullable' : 'required') . '|string|max:255',
            'vendorName'          => 'sometimes|nullable|string|max:255',
            'purchaseDate'        => ($isUpdate ? 'sometimes|required' : 'required') . '|date',
            'purchasePrice'       => ($isUpdate ? 'sometimes|required' : 'required') . '|numeric|min:0',
            'salvageValue'        => 'sometimes|nullable|numeric|min:0',
            'usefulLifeMonths'    => ($isUpdate ? 'sometimes|required' : 'required') . '|integer|min:1',
            'depreciationMethod'  => 'sometimes|nullable|string|in:straight_line,declining_balance,sum_of_years_digits',
            'status'              => 'sometimes|nullable|string|in:draft,active,retired',
            'condition'           => 'sometimes|nullable|string|in:Excellent,Good,Fair,Poor,Damaged',
            'notes'               => 'sometimes|nullable|string',
            'custodianEmployeeId' => 'sometimes|nullable|uuid|exists:employees,id',
            'locationId'          => 'sometimes|nullable|string|max:255',
        ];

        $validated = $request->validate($rules);

        return [
            'name'                  => $validated['name']                ?? null,
            'serial_number'         => $validated['serialNumber']        ?? null,
            'description'           => $validated['description']         ?? null,
            'category'              => $validated['category']            ?? null,
            'vendor_name'           => $validated['vendorName']          ?? null,
            'purchase_date'         => $validated['purchaseDate']        ?? null,
            'purchase_price'        => $validated['purchasePrice']       ?? null,
            'salvage_value'         => $validated['salvageValue']        ?? 0,
            'useful_life_months'    => $validated['usefulLifeMonths']    ?? null,
            'depreciation_method'   => $validated['depreciationMethod']  ?? 'straight_line',
            'status'                => $validated['status']              ?? 'active',
            'condition'             => $validated['condition']           ?? 'Good',
            'notes'                 => $validated['notes']               ?? null,
            'custodian_employee_id' => $validated['custodianEmployeeId'] ?? null,
            'location_id'           => $validated['locationId']          ?? null,
        ];
    }
}
