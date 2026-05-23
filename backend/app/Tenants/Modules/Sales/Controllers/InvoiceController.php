<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Invoice;
use App\Tenants\Modules\Sales\Resources\InvoiceResource;
use App\Tenants\Modules\Sales\Services\InvoiceService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    use Paginates;

    public function __construct(private readonly InvoiceService $invoices)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $query = Invoice::query()->with(['customer', 'items'])->orderByDesc('created_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }

        $paginator = $this->paginateQuery($query, $request);

        return $this->paginatedResponse(InvoiceResource::class, $paginator, $request);
    }

    public function show(Invoice $invoice): InvoiceResource
    {
        return new InvoiceResource($invoice->load(['customer', 'items', 'journalEntry']));
    }

    public function confirm(Invoice $invoice): InvoiceResource|JsonResponse
    {
        try {
            $this->invoices->confirm($invoice);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new InvoiceResource($invoice->fresh(['customer', 'items', 'journalEntry']));
    }

    public function cancel(Request $request, Invoice $invoice): InvoiceResource|JsonResponse
    {
        $data = $request->validate(['reason' => 'sometimes|nullable|string|max:500']);

        try {
            $this->invoices->cancel($invoice, $data['reason'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new InvoiceResource($invoice->fresh(['customer', 'items', 'journalEntry']));
    }
}
