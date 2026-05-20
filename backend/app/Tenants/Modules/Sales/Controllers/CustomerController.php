<?php

namespace App\Tenants\Modules\Sales\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Customer;
use App\Tenants\Modules\Sales\Resources\CustomerResource;
use App\Tenants\Modules\Sales\Services\CrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CustomerController extends Controller
{
    use Paginates;

    protected $crmService;

    public function __construct(CrmService $crmService)
    {
        $this->crmService = $crmService;
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery($this->crmService->buildCustomersQuery(), $request);

        return $this->paginatedResponse(CustomerResource::class, $paginator, $request);
    }

    public function store(Request $request): CustomerResource
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:customers,email',
            'phone' => 'nullable|string',
            'company_name' => 'nullable|string',
            'address' => 'nullable|string',
        ]);

        $customer = Customer::create($data);
        return new CustomerResource($customer);
    }

    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource($customer->load('orders', 'leads'));
    }
}
