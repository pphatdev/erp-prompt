<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\BillPayment;
use App\Tenants\Modules\FMS\Resources\BillPaymentResource;
use App\Tenants\Modules\FMS\Services\BillPaymentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BillPaymentController extends Controller
{
    use Paginates;

    public function __construct(private readonly BillPaymentService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BillPayment::class);

        $query = $this->service->buildQuery();

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('payment_number', 'ilike', $like)
                ->orWhere('reference_number', 'ilike', $like)
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'ilike', $like)));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }
        if ($bankId = $request->query('bank_account_id')) {
            $query->where('bank_account_id', $bankId);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('paid_on', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('paid_on', '<=', $to);
        }

        return $this->paginatedResponse(BillPaymentResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): BillPaymentResource|JsonResponse
    {
        Gate::authorize('create', BillPayment::class);
        $data = $this->validatePayload($request);

        try {
            $payment = $this->service->record($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new BillPaymentResource($payment);
    }

    public function show(BillPayment $billPayment): BillPaymentResource
    {
        Gate::authorize('view', $billPayment);
        return new BillPaymentResource(
            $billPayment->load(['supplier', 'bankAccount.glAccount', 'applications.bill', 'journalEntry'])
        );
    }

    public function cancel(BillPayment $billPayment): BillPaymentResource|JsonResponse
    {
        Gate::authorize('cancel', $billPayment);

        try {
            $payment = $this->service->cancel($billPayment);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new BillPaymentResource($payment);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'payment_number'              => 'required|string|max:64|unique:bill_payments,payment_number',
            'bank_account_id'             => 'required|uuid|exists:bank_accounts,id',
            'supplier_id'                 => 'required|uuid|exists:suppliers,id',
            'paid_on'                     => 'required|date',
            'amount'                      => 'required|numeric|gt:0',
            'currency'                    => 'sometimes|nullable|string|size:3',
            'payment_method'              => 'sometimes|nullable|string|max:40',
            'reference_number'            => 'sometimes|nullable|string|max:64',
            'notes'                       => 'sometimes|nullable|string|max:2000',

            'applications'                => 'required|array|min:1',
            'applications.*.bill_id'      => 'required|uuid|exists:bills,id',
            'applications.*.applied_amount' => 'required|numeric|gt:0',
        ]);
    }
}
