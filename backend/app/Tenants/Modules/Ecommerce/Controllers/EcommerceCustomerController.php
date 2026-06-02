<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Ecommerce\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\EcomCustomer;
use App\Tenants\Modules\Ecommerce\Resources\EcomCustomerResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EcommerceCustomerController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', EcomCustomer::class);

        $query = EcomCustomer::query()
            ->withCount('orders')
            ->orderByDesc('created_at');

        if ($search = $request->query('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('email', 'ilike', "%{$search}%")
                    ->orWhere('first_name', 'ilike', "%{$search}%")
                    ->orWhere('last_name', 'ilike', "%{$search}%");
            });
        }
        if ($request->boolean('exclude_guests')) {
            $query->where('is_guest', false);
        }

        return $this->paginatedResponse(EcomCustomerResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function show(EcomCustomer $customer): EcomCustomerResource
    {
        $this->authorize('view', $customer);
        return new EcomCustomerResource($customer->load(['addresses', 'orders']));
    }
}
