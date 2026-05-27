<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\LowStockAlert;
use App\Tenants\Modules\Inventory\Resources\LowStockAlertResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LowStockAlertController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', LowStockAlert::class);

        $query = LowStockAlert::query()->with('product');

        if ($status = $request->query('status'))         $query->where('status', $status);
        if ($productId = $request->query('product_id'))  $query->where('product_id', $productId);

        $query->orderByDesc('created_at');

        return $this->paginatedResponse(LowStockAlertResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function show(LowStockAlert $lowStockAlert): LowStockAlertResource
    {
        Gate::authorize('view', $lowStockAlert);
        return new LowStockAlertResource($lowStockAlert->load(['product', 'acknowledger']));
    }

    public function acknowledge(Request $request, LowStockAlert $lowStockAlert): LowStockAlertResource
    {
        Gate::authorize('acknowledge', $lowStockAlert);

        if ($lowStockAlert->status === LowStockAlert::STATUS_OPEN) {
            $lowStockAlert->update([
                'status'          => LowStockAlert::STATUS_ACKNOWLEDGED,
                'acknowledged_at' => now(),
                'acknowledged_by' => $request->user()?->id,
            ]);
        }

        return new LowStockAlertResource($lowStockAlert->load(['product', 'acknowledger']));
    }

    public function resolve(LowStockAlert $lowStockAlert): LowStockAlertResource
    {
        Gate::authorize('resolve', $lowStockAlert);

        if ($lowStockAlert->status !== LowStockAlert::STATUS_RESOLVED) {
            $lowStockAlert->update([
                'status'      => LowStockAlert::STATUS_RESOLVED,
                'resolved_at' => now(),
            ]);
        }

        return new LowStockAlertResource($lowStockAlert->load(['product', 'acknowledger']));
    }
}
