<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Lead;
use App\Tenants\Modules\Crm\Resources\LeadResource;
use App\Tenants\Modules\Crm\Resources\OpportunityResource;
use App\Tenants\Modules\Crm\Services\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LeadController extends Controller
{
    use Paginates;

    public function __construct(private readonly LeadService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Lead::class);
        $query = $this->service->buildQuery();

        if ($search = $request->query('search')) {
            $query->where('title', 'ilike', '%' . $search . '%');
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->paginatedResponse(LeadResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): LeadResource
    {
        Gate::authorize('create', Lead::class);
        $data = $request->validate([
            // Person fields — required by the new lead capture flow.
            'first_name'      => 'required|string|max:100',
            'last_name'       => 'required|string|max:100',
            'email'           => 'required|email|max:255',
            'phone'           => 'required|string|max:50',
            'customer_type'   => ['required', \Illuminate\Validation\Rule::in(Lead::TYPES)],
            'address'         => 'required|string|max:1000',
            // Optional deal-level fields.
            'title'           => 'sometimes|nullable|string|max:255',
            'customer_id'     => 'sometimes|nullable|uuid|exists:customers,id',
            'estimated_value' => 'sometimes|nullable|numeric|min:0',
            'source'          => 'sometimes|nullable|string|max:100',
            'status'          => 'sometimes|string|in:new,contacted,qualified,unqualified',
        ]);

        return new LeadResource($this->service->createLead($data)->load('customer'));
    }

    public function show(Lead $lead): LeadResource
    {
        Gate::authorize('view', $lead);
        return new LeadResource($lead->load('customer'));
    }

    public function update(Request $request, Lead $lead): LeadResource
    {
        Gate::authorize('update', $lead);
        $data = $request->validate([
            'first_name'      => 'sometimes|string|max:100',
            'last_name'       => 'sometimes|string|max:100',
            'email'           => 'sometimes|email|max:255',
            'phone'           => 'sometimes|string|max:50',
            'customer_type'   => ['sometimes', \Illuminate\Validation\Rule::in(Lead::TYPES)],
            'address'         => 'sometimes|nullable|string|max:1000',
            'title'           => 'sometimes|nullable|string|max:255',
            'customer_id'     => 'sometimes|nullable|uuid|exists:customers,id',
            'estimated_value' => 'sometimes|nullable|numeric|min:0',
            'source'          => 'sometimes|nullable|string|max:100',
            'status'          => 'sometimes|string|in:new,contacted,qualified,unqualified',
        ]);

        $lead->update($data);
        return new LeadResource($lead->fresh('customer'));
    }

    public function destroy(Lead $lead): JsonResponse
    {
        Gate::authorize('delete', $lead);
        $lead->delete();
        return response()->json(['message' => 'Lead deleted.']);
    }

    /**
     * @description Qualify a Lead and convert it to a B2B Opportunity
     * @method POST
     * @param { Request } request HTTP Request containing qualification fields
     * @param { Lead } lead Lead model instance to qualify
     * @returns { OpportunityResource } The qualified Opportunity details
     */
    public function qualify(Request $request, Lead $lead): OpportunityResource
    {
        Gate::authorize('qualify', $lead);

        $data = $request->validate([
            'customer_id'       => 'sometimes|nullable|uuid|exists:customers,id',
            'opportunity_title' => 'sometimes|nullable|string|max:255',
            'estimated_value'   => 'sometimes|nullable|numeric|min:0',
            'probability'       => 'sometimes|nullable|integer|min:0|max:100',
            'close_date'        => 'sometimes|nullable|date',
            'notes'             => 'sometimes|nullable|string',
        ]);

        $opp = $this->service->qualifyToOpportunity($lead, $data);
        return new OpportunityResource($opp);
    }
}
