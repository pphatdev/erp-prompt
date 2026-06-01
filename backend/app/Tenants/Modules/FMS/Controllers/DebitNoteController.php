<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\DebitNote;
use App\Tenants\Modules\FMS\Resources\DebitNoteResource;
use App\Tenants\Modules\FMS\Services\DebitNoteService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class DebitNoteController extends Controller
{
    use Paginates;

    public function __construct(private readonly DebitNoteService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', DebitNote::class);

        $query = $this->service->buildQuery();

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('debit_note_number', 'ilike', $like)
                ->orWhere('reason', 'ilike', $like)
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', $like))
                ->orWhereHas('invoice', fn ($i) => $i->where('invoice_number', 'ilike', $like)));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }
        if ($invoiceId = $request->query('invoice_id')) {
            $query->where('invoice_id', $invoiceId);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('issue_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('issue_date', '<=', $to);
        }

        return $this->paginatedResponse(DebitNoteResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): DebitNoteResource|JsonResponse
    {
        Gate::authorize('create', DebitNote::class);
        $data = $this->validatePayload($request);

        try {
            $note = $this->service->issue($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new DebitNoteResource($note);
    }

    public function show(DebitNote $debitNote): DebitNoteResource
    {
        Gate::authorize('view', $debitNote);
        return new DebitNoteResource(
            $debitNote->load(['customer', 'invoice', 'revenueAccount', 'arAccount', 'journalEntry'])
        );
    }

    public function cancel(DebitNote $debitNote): DebitNoteResource|JsonResponse
    {
        Gate::authorize('cancel', $debitNote);

        try {
            $note = $this->service->cancel($debitNote);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new DebitNoteResource($note);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'debit_note_number'   => 'required|string|max:64|unique:debit_notes,debit_note_number',
            'customer_id'         => 'required|uuid|exists:customers,id',
            'invoice_id'          => 'sometimes|nullable|uuid|exists:invoices,id',
            'revenue_account_id'  => 'required|uuid|exists:accounts,id',
            'ar_account_id'       => 'required|uuid|exists:accounts,id',
            'issue_date'          => 'required|date',
            'amount'              => 'required|numeric|gt:0',
            'currency'            => 'sometimes|nullable|string|size:3',
            'reason'              => 'required|string|max:500',
            'notes'               => 'sometimes|nullable|string|max:2000',
        ]);
    }
}
