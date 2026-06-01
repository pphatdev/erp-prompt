<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Invoice;
use App\Models\Tenant\Receipt;
use App\Tenants\Modules\FMS\Resources\ReceiptResource;
use App\Tenants\Modules\FMS\Services\ReceiptService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReceiptController extends Controller
{
    use Paginates;

    public function __construct(private readonly ReceiptService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Receipt::class);

        $query = $this->service->buildQuery();

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('receipt_number', 'ilike', $like)
                ->orWhere('reference_number', 'ilike', $like)
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'ilike', $like)));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($customerId = $request->query('customer_id')) {
            $query->where('customer_id', $customerId);
        }
        if ($bankId = $request->query('bank_account_id')) {
            $query->where('bank_account_id', $bankId);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('received_on', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('received_on', '<=', $to);
        }

        return $this->paginatedResponse(ReceiptResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): ReceiptResource|JsonResponse
    {
        Gate::authorize('create', Receipt::class);
        $data = $this->validatePayload($request);

        try {
            $receipt = $this->service->record($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ReceiptResource($receipt);
    }

    public function show(Receipt $receipt): ReceiptResource
    {
        Gate::authorize('view', $receipt);
        return new ReceiptResource(
            $receipt->load(['customer', 'bankAccount.glAccount', 'arAccount', 'applications.invoice', 'journalEntry'])
        );
    }

    public function cancel(Receipt $receipt): ReceiptResource|JsonResponse
    {
        Gate::authorize('cancel', $receipt);

        try {
            $receipt = $this->service->cancel($receipt);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ReceiptResource($receipt);
    }

    /**
     * Open invoices for a customer (confirmed status with outstanding > 0).
     * Powers the invoice-picker in the Record Receipt UI without having to
     * pull every confirmed invoice client-side.
     */
    public function openInvoicesForCustomer(Request $request, string $customer): JsonResponse
    {
        Gate::authorize('viewAny', Receipt::class);

        $invoices = Invoice::query()
            ->where('customer_id', $customer)
            ->where('status', Invoice::STATUS_CONFIRMED)
            ->orderBy('due_date')
            ->orderBy('invoice_date')
            ->get(['id', 'invoice_number', 'status', 'invoice_date', 'due_date', 'total_amount', 'paid_amount'])
            ->filter(fn (Invoice $i) => ((float) $i->total_amount - (float) $i->paid_amount) > 0.001)
            ->values()
            ->map(fn (Invoice $i) => [
                'id'                => $i->id,
                'invoiceNumber'     => $i->invoice_number,
                'status'            => $i->status,
                'invoiceDate'       => optional($i->invoice_date)->toDateString(),
                'dueDate'           => optional($i->due_date)->toDateString(),
                'totalAmount'       => (float) $i->total_amount,
                'paidAmount'        => (float) $i->paid_amount,
                'outstandingAmount' => round((float) $i->total_amount - (float) $i->paid_amount, 2),
            ]);

        return response()->json(['data' => $invoices]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'receipt_number'                  => 'required|string|max:64|unique:receipts,receipt_number',
            'customer_id'                     => 'required|uuid|exists:customers,id',
            'bank_account_id'                 => 'required|uuid|exists:bank_accounts,id',
            'ar_account_id'                   => 'required|uuid|exists:accounts,id',
            'received_on'                     => 'required|date',
            'amount'                          => 'required|numeric|gt:0',
            'currency'                        => 'sometimes|nullable|string|size:3',
            'payment_method'                  => 'sometimes|nullable|string|max:40',
            'reference_number'                => 'sometimes|nullable|string|max:64',
            'notes'                           => 'sometimes|nullable|string|max:2000',

            'applications'                    => 'required|array|min:1',
            'applications.*.invoice_id'       => 'required|uuid|exists:invoices,id',
            'applications.*.applied_amount'   => 'required|numeric|gt:0',
        ]);
    }
}
