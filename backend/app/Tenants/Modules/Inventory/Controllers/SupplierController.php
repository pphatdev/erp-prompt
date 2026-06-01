<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Supplier;
use App\Tenants\Modules\Inventory\Resources\SupplierResource;
use App\Tenants\Modules\Inventory\Services\SupplierService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SupplierController extends Controller
{
    use Paginates;

    public function __construct(private readonly SupplierService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Supplier::class);

        $query = $this->service->buildQuery();
        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', $like)
                ->orWhere('code', 'ilike', $like)
                ->orWhere('email', 'ilike', $like)
                ->orWhere('contact_name', 'ilike', $like));
        }
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($minRating = $request->query('min_rating')) {
            $query->where('rating', '>=', (int) $minRating);
        }
        if ($request->boolean('vendor_only')) {
            $query->where('is_vendor', true);
        }

        return $this->paginatedResponse(SupplierResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): SupplierResource|JsonResponse
    {
        Gate::authorize('create', Supplier::class);
        $data = $this->validatePayload($request);

        try {
            $s = $this->service->create($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new SupplierResource($s);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        Gate::authorize('view', $supplier);
        return new SupplierResource($supplier);
    }

    public function update(Request $request, Supplier $supplier): SupplierResource|JsonResponse
    {
        Gate::authorize('update', $supplier);
        $data = $this->validatePayload($request, $supplier);

        try {
            $s = $this->service->update($supplier, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new SupplierResource($s);
    }

    public function destroy(Supplier $supplier): JsonResponse
    {
        Gate::authorize('delete', $supplier);

        try {
            $this->service->archive($supplier);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Supplier archived.']);
    }

    private function validatePayload(Request $request, ?Supplier $existing = null): array
    {
        $isUpdate = $existing !== null;
        $req = $isUpdate ? 'sometimes' : 'required';
        return $request->validate([
            'code'           => 'sometimes|nullable|string|max:40',
            'name'           => "{$req}|string|max:255",
            'contact_name'   => 'sometimes|nullable|string|max:120',
            'email'          => 'sometimes|nullable|email|max:255',
            'phone'          => 'sometimes|nullable|string|max:50',
            'address'        => 'sometimes|nullable|string|max:1000',
            'website'        => 'sometimes|nullable|url|max:255',
            'tax_id'         => 'sometimes|nullable|string|max:60',
            'payment_terms'  => 'sometimes|nullable|string|max:60',
            'lead_time_days' => 'sometimes|nullable|integer|min:0|max:365',
            'rating'         => 'sometimes|nullable|integer|min:1|max:5',
            'is_active'      => 'sometimes|boolean',
            'notes'          => 'sometimes|nullable|string|max:2000',

            // AP / Vendor extension.
            'is_vendor'                  => 'sometimes|boolean',
            'payment_method'             => 'sometimes|nullable|string|max:40',
            'bank_name'                  => 'sometimes|nullable|string|max:160',
            'bank_account_name'          => 'sometimes|nullable|string|max:160',
            'bank_account_number'        => 'sometimes|nullable|string|max:60',
            'bank_swift'                 => 'sometimes|nullable|string|max:20',
            'default_payable_account_id' => 'sometimes|nullable|uuid|exists:accounts,id',
            'default_expense_account_id' => 'sometimes|nullable|uuid|exists:accounts,id',
        ]);
    }
}
