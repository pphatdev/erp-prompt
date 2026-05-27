<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Customer;
use App\Tenants\Modules\Sales\Resources\CustomerResource;
use App\Tenants\Modules\Sales\Services\CrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CustomerController extends Controller
{
    use Paginates;

    public function __construct(
        private readonly CrmService $crmService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->crmService->buildCustomersQuery()->with('accountManager');

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(function ($q) use ($like) {
                $q->where('name', 'ilike', $like)
                  ->orWhere('email', 'ilike', $like)
                  ->orWhere('company_name', 'ilike', $like)
                  ->orWhere('external_code', 'ilike', $like);
            });
        }

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        if ($type = $request->query('customer_type')) {
            $query->where('customer_type', $type);
        }

        if ($tier = $request->query('tier')) {
            $query->where('tier', $tier);
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(CustomerResource::class, $paginator, $request);
    }

    public function store(Request $request): CustomerResource
    {
        $data = $request->validate($this->rules());

        $customer = Customer::create($data);

        // Tenant provisioning is no longer triggered on customer create.
        // The single trigger is Sale Order::confirm — see OrderService.

        return new CustomerResource($customer->load('accountManager'));
    }

    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource(
            $customer->load(['orders', 'leads', 'accountManager'])
        );
    }

    public function update(Request $request, Customer $customer): CustomerResource
    {
        $data = $request->validate($this->rules($customer));

        $customer->update($data);

        return new CustomerResource($customer->fresh('accountManager'));
    }

    public function destroy(Customer $customer): JsonResponse
    {
        $customer->delete();

        return response()->json(['message' => 'Customer archived.']);
    }

    /**
     * GET /customers/check-handle?handle=acme-corp[&ignore_id=uuid]
     * Returns { available: bool } — used for real-time uniqueness feedback.
     */
    public function checkHandle(Request $request): JsonResponse
    {
        $handle = (string) $request->query('handle', '');

        if ($handle === '' || ! preg_match('/^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])?$/', $handle)) {
            return response()->json(['available' => false]);
        }

        $query = Customer::withoutTrashed()
            ->where('tenant_handle', $handle);

        if ($ignoreId = $request->query('ignore_id')) {
            $query->where('id', '!=', $ignoreId);
        }

        return response()->json(['available' => ! $query->exists()]);
    }

    /**
     * Shared validation rule set. Existing-customer updates relax `required`
     * to `sometimes` and ignore self on unique checks.
     */
    private function rules(?Customer $existing = null): array
    {
        $isUpdate = $existing !== null;
        $opt = 'sometimes|nullable';

        $tenantHandleRules = ['nullable', 'string', 'max:60', 'regex:/^[a-z0-9](?:[a-z0-9-]{1,58}[a-z0-9])?$/'];
        $tenantHandleRules[] = $isUpdate
            ? Rule::unique('customers', 'tenant_handle')->ignore($existing->id)->whereNull('deleted_at')
            : Rule::unique('customers', 'tenant_handle')->whereNull('deleted_at');

        return [
            // Identity
            'name'                  => ($isUpdate ? 'sometimes' : 'required') . '|string|max:255',
            // Email is optional — early-stage leads convert to Customers via
            // QuotationService::win without one. The unique rule still applies
            // when an email is supplied (Postgres allows multiple NULLs in a
            // unique column, so unfilled rows never collide).
            'email'                 => $isUpdate
                ? ['sometimes', 'nullable', 'email', Rule::unique('customers', 'email')->ignore($existing->id)->whereNotNull('email')]
                : ['sometimes', 'nullable', 'email', Rule::unique('customers', 'email')->whereNotNull('email')],
            'phone'                 => "{$opt}|string|max:50",
            'company_name'          => "{$opt}|string|max:255",
            'status'                => ['sometimes', Rule::in(['active', 'inactive'])],

            // Classification
            'customer_type'         => ['sometimes', Rule::in(Customer::TYPES)],
            'external_code'         => "{$opt}|string|max:60",
            'tier'                  => ['sometimes', Rule::in(Customer::TIERS)],

            // Business identifiers
            'tax_id'                => "{$opt}|string|max:60",
            'industry'              => "{$opt}|string|max:80",
            'website'                => "{$opt}|url|max:255",

            // Address
            'address'               => "{$opt}|string|max:1000",
            'billing_city'          => "{$opt}|string|max:120",
            'billing_state'         => "{$opt}|string|max:120",
            'billing_postal_code'   => "{$opt}|string|max:20",
            'billing_country'       => "{$opt}|string|size:2",

            // Locale
            'currency'              => "{$opt}|string|size:3",
            'language'              => "{$opt}|string|max:8",
            'timezone'              => "{$opt}|string|max:60",

            // Account ownership
            'account_manager_id'    => "{$opt}|uuid|exists:users,id",

            // Notes
            'notes'                 => "{$opt}|string|max:5000",

            // Branding — RGB triple ("59 130 246"), matches Settings format.
            'brand_primary_color'   => "{$opt}|string|max:20",
            // Either an external URL (https://…) or an inline base64 data URL
            // (data:image/png;base64,…). 300_000 chars ≈ 220KB encoded payload.
            // Array form (not pipe string) because the regex pattern contains
            // a literal `|` that would otherwise split the rule.
            'brand_logo_url'        => ['sometimes', 'nullable', 'string', 'max:300000', 'regex:/^(https?:\/\/|data:image\/(png|jpe?g|gif|webp|svg\+xml);base64,)/i'],

            // Tenant linkage (writable only on type=tenant; provisioned_*
            // fields below are mutated by the listener, not the API).
            'tenant_handle'         => $tenantHandleRules,
        ];
    }
}
