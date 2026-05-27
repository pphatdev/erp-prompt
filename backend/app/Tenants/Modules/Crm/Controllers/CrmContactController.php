<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\CrmContact;
use App\Tenants\Modules\Crm\Resources\CrmContactResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CrmContactController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CrmContact::class);
        $query = CrmContact::query()->with('customer')->orderByDesc('created_at');

        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }
        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('first_name', 'ilike', $like)
                ->orWhere('last_name', 'ilike', $like)
                ->orWhere('email', 'ilike', $like));
        }

        return $this->paginatedResponse(CrmContactResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): CrmContactResource
    {
        Gate::authorize('create', CrmContact::class);
        $data = $request->validate([
            'customer_id' => 'required|uuid|exists:customers,id',
            'first_name'  => 'required|string|max:100',
            'last_name'   => 'nullable|string|max:100',
            'email'       => 'nullable|email|max:255',
            'phone'       => 'nullable|string|max:50',
            'job_title'   => 'nullable|string|max:100',
            'is_primary'  => 'sometimes|boolean',
        ]);

        $contact = CrmContact::create($data);
        return new CrmContactResource($contact->load('customer'));
    }

    public function show(CrmContact $crmContact): CrmContactResource
    {
        Gate::authorize('view', $crmContact);
        return new CrmContactResource($crmContact->load('customer'));
    }

    public function update(Request $request, CrmContact $crmContact): CrmContactResource
    {
        Gate::authorize('update', $crmContact);
        $data = $request->validate([
            'first_name' => 'sometimes|string|max:100',
            'last_name'  => 'sometimes|nullable|string|max:100',
            'email'      => 'sometimes|nullable|email|max:255',
            'phone'      => 'sometimes|nullable|string|max:50',
            'job_title'  => 'sometimes|nullable|string|max:100',
            'is_primary' => 'sometimes|boolean',
        ]);

        $crmContact->update($data);
        return new CrmContactResource($crmContact->fresh('customer'));
    }

    public function destroy(CrmContact $crmContact): JsonResponse
    {
        Gate::authorize('delete', $crmContact);
        $crmContact->delete();
        return response()->json(['message' => 'Contact deleted.']);
    }
}
