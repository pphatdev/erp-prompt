<?php

namespace App\Tenants\Modules\Sales\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Lead;
use App\Tenants\Modules\Sales\Resources\LeadResource;
use App\Tenants\Modules\Sales\Services\CrmService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LeadController extends Controller
{
    use Paginates;

    protected $crmService;

    public function __construct(CrmService $crmService)
    {
        $this->crmService = $crmService;
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery($this->crmService->buildLeadsQuery(), $request);

        return $this->paginatedResponse(LeadResource::class, $paginator, $request);
    }

    public function store(Request $request): LeadResource
    {
        $data = $request->validate([
            'title' => 'required|string|max:255',
            'customer_id' => 'nullable|exists:customers,id',
            'estimated_value' => 'nullable|numeric',
            'source' => 'nullable|string',
        ]);

        $lead = $this->crmService->createLead($data);
        return new LeadResource($lead->load('customer'));
    }

    public function win(Lead $lead): LeadResource
    {
        $this->crmService->winLead($lead);
        return new LeadResource($lead);
    }
}
