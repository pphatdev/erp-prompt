<?php

namespace App\Tenants\Modules\Inventory\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\StockMovement;
use App\Tenants\Modules\Inventory\Resources\StockMovementResource;
use App\Tenants\Modules\Inventory\Services\StockService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StockMovementController extends Controller
{
    use Paginates;

    protected $stockService;

    public function __construct(StockService $stockService)
    {
        $this->stockService = $stockService;
    }

    public function index(Request $request): JsonResponse
    {
        $query = StockMovement::query()->orderBy('created_at', 'desc');

        if ($request->has('product_id')) {
            $query->where('product_id', $request->input('product_id'));
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(StockMovementResource::class, $paginator, $request);
    }

    public function store(Request $request): StockMovementResource
    {
        $data = $request->validate([
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'type' => 'required|in:in,out',
            'quantity' => 'required|integer',
            'reference' => 'nullable|string',
            'notes' => 'nullable|string',
        ]);

        $movement = $this->stockService->recordMovement($data);
        return new StockMovementResource($movement);
    }
}
