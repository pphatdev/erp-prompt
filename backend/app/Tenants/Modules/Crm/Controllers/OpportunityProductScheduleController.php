<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Opportunity;
use App\Models\Tenant\OpportunityProductSchedule;
use App\Tenants\Modules\Crm\Resources\OpportunityProductScheduleResource;
use App\Tenants\Modules\Crm\Services\OpportunityProductScheduleService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OpportunityProductScheduleController extends Controller
{
    public function __construct(private readonly OpportunityProductScheduleService $service) {}

    public function index(Opportunity $opportunity): AnonymousResourceCollection
    {
        Gate::authorize('view', $opportunity);
        return OpportunityProductScheduleResource::collection($this->service->listFor($opportunity));
    }

    public function store(Request $request, Opportunity $opportunity): OpportunityProductScheduleResource|JsonResponse
    {
        Gate::authorize('update', $opportunity);

        $data = $request->validate([
            'product_id'           => 'required|uuid|exists:products,id',
            'variant_id'           => 'sometimes|nullable|uuid|exists:product_variants,id',
            'quantity'             => 'sometimes|numeric|min:0.01',
            'estimated_unit_price' => 'sometimes|nullable|numeric|min:0',
            'cadence'              => ['sometimes', Rule::in(OpportunityProductSchedule::CADENCES)],
            'notes'                => 'sometimes|nullable|string|max:1000',
        ]);

        try {
            $line = $this->service->addLine($opportunity, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new OpportunityProductScheduleResource($line->load(['product', 'variant']));
    }

    public function update(Request $request, Opportunity $opportunity, OpportunityProductSchedule $line): OpportunityProductScheduleResource|JsonResponse
    {
        Gate::authorize('update', $opportunity);

        if ($line->opportunity_id !== $opportunity->id) {
            abort(404);
        }

        $data = $request->validate([
            'variant_id'           => 'sometimes|nullable|uuid|exists:product_variants,id',
            'quantity'             => 'sometimes|numeric|min:0.01',
            'estimated_unit_price' => 'sometimes|nullable|numeric|min:0',
            'cadence'              => ['sometimes', Rule::in(OpportunityProductSchedule::CADENCES)],
            'notes'                => 'sometimes|nullable|string|max:1000',
        ]);

        try {
            $line = $this->service->updateLine($line, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new OpportunityProductScheduleResource($line);
    }

    public function destroy(Opportunity $opportunity, OpportunityProductSchedule $line): JsonResponse
    {
        Gate::authorize('update', $opportunity);

        if ($line->opportunity_id !== $opportunity->id) {
            abort(404);
        }

        try {
            $this->service->removeLine($line);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Schedule line removed.']);
    }
}
