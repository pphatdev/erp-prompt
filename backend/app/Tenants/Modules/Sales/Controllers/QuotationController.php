<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Quotation;
use App\Tenants\Modules\Sales\Requests\AddQuotationItemRequest;
use App\Tenants\Modules\Sales\Requests\StoreQuotationRequest;
use App\Tenants\Modules\Sales\Resources\QuotationItemResource;
use App\Tenants\Modules\Sales\Resources\QuotationResource;
use App\Tenants\Modules\Sales\Services\QuotationService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuotationController extends Controller
{
    use Paginates;

    public function __construct(private readonly QuotationService $quotes)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Quotation::query()->with(['customer', 'items'])->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(QuotationResource::class, $paginator, $request);
    }

    public function store(StoreQuotationRequest $request): QuotationResource
    {
        $quote = $this->quotes->create($request->validated());

        return new QuotationResource($quote->load(['customer', 'items']));
    }

    public function show(Quotation $quotation): QuotationResource
    {
        return new QuotationResource($quotation->load(['customer', 'items', 'order']));
    }

    public function destroy(Quotation $quotation): JsonResponse
    {
        $quotation->delete();

        return response()->json(['message' => 'Quotation archived.']);
    }

    public function addItem(AddQuotationItemRequest $request, Quotation $quotation): QuotationItemResource|JsonResponse
    {
        try {
            $item = $this->quotes->addItem($quotation, $request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return (new QuotationItemResource($item))->response()->setStatusCode(201);
    }

    public function win(Quotation $quotation): QuotationResource|JsonResponse
    {
        try {
            $this->quotes->win($quotation);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new QuotationResource($quotation->fresh()->load(['customer', 'items', 'order']));
    }

    public function lose(Request $request, Quotation $quotation): QuotationResource|JsonResponse
    {
        $data = $request->validate(['loss_reason' => 'required|string|max:1000']);

        try {
            $this->quotes->lose($quotation, $data['loss_reason']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new QuotationResource($quotation->fresh()->load(['customer', 'items']));
    }
}
