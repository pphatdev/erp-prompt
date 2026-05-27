<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Opportunity;
use App\Tenants\Modules\Crm\Resources\OpportunityResource;
use App\Tenants\Modules\Crm\Services\OpportunityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OpportunityController extends Controller
{
    use Paginates;

    public function __construct(private readonly OpportunityService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Opportunity::class);
        $query = $this->service->buildQuery();

        if ($stage = $request->query('stage')) {
            $query->where('stage', $stage);
        }
        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }
        if ($search = $request->query('search')) {
            $query->where('title', 'ilike', '%' . $search . '%');
        }

        return $this->paginatedResponse(OpportunityResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): OpportunityResource
    {
        Gate::authorize('create', Opportunity::class);
        // Accept either snake_case or camelCase keys (frontend write payloads
        // are inconsistent across modules) before validating.
        $request->merge(array_filter([
            'customer_id'     => $request->input('customer_id') ?? $request->input('customerId'),
            'lead_id'         => $request->input('lead_id') ?? $request->input('leadId'),
            'estimated_value' => $request->input('estimated_value') ?? $request->input('estimatedValue'),
            'close_date'      => $request->input('close_date') ?? $request->input('projectedCloseDate'),
        ], fn ($v) => $v !== null));

        $data = $request->validate([
            'title'           => 'required|string|max:255',
            // Customer is optional — Opportunity may exist without a Customer
            // until the originating Quotation is Won (see QuotationService::win).
            'customer_id'     => 'sometimes|nullable|uuid|exists:customers,id',
            'lead_id'         => 'sometimes|nullable|uuid|exists:leads,id',
            'stage'           => ['sometimes', Rule::in(Opportunity::STAGES)],
            'estimated_value' => 'sometimes|nullable|numeric|min:0',
            'probability'     => 'sometimes|nullable|integer|min:0|max:100',
            'close_date'      => 'sometimes|nullable|date',
            'notes'           => 'sometimes|nullable|string',
        ]);

        return new OpportunityResource($this->service->create($data));
    }

    public function show(Opportunity $opportunity): OpportunityResource
    {
        Gate::authorize('view', $opportunity);
        return new OpportunityResource($opportunity->load(['customer', 'lead']));
    }

    public function update(Request $request, Opportunity $opportunity): OpportunityResource
    {
        Gate::authorize('update', $opportunity);

        $request->merge(array_filter([
            'estimated_value' => $request->input('estimated_value') ?? $request->input('estimatedValue'),
            'close_date'      => $request->input('close_date') ?? $request->input('projectedCloseDate'),
        ], fn ($v) => $v !== null));

        $data = $request->validate([
            'title'           => 'sometimes|string|max:255',
            'estimated_value' => 'sometimes|numeric|min:0',
            'probability'     => 'sometimes|integer|min:0|max:100',
            'close_date'      => 'sometimes|nullable|date',
            'notes'           => 'sometimes|nullable|string',
        ]);

        return new OpportunityResource($this->service->update($opportunity, $data));
    }

    public function destroy(Opportunity $opportunity): JsonResponse
    {
        Gate::authorize('delete', $opportunity);
        $opportunity->delete();
        return response()->json(['message' => 'Opportunity archived.']);
    }

    public function updateStage(Request $request, Opportunity $opportunity): OpportunityResource
    {
        Gate::authorize('update', $opportunity);
        $data = $request->validate([
            'stage'       => ['required', Rule::in(Opportunity::STAGES)],
            'loss_reason' => 'nullable|string|max:1000',
        ]);

        try {
            $opp = $this->service->updateStage($opportunity, $data['stage'], $data['loss_reason'] ?? null);
        } catch (\DomainException $e) {
            abort(422, $e->getMessage());
        }

        return new OpportunityResource($opp);
    }
}
