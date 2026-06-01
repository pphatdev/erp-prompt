<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\CashAdvanceSettlement;
use App\Tenants\Modules\FMS\Resources\CashAdvanceSettlementResource;
use App\Tenants\Modules\FMS\Services\CashAdvanceSettlementService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CashAdvanceSettlementController extends Controller
{
    use Paginates;

    public function __construct(private readonly CashAdvanceSettlementService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CashAdvanceSettlement::class);

        $query = $this->service->buildQuery();

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('settlement_number', 'ilike', $like)
                ->orWhere('reference_number', 'ilike', $like)
                ->orWhereHas('cashAdvance', fn ($a) => $a
                    ->where('advance_number', 'ilike', $like))
                ->orWhereHas('cashAdvance.employee', fn ($e) => $e
                    ->where('first_name', 'ilike', $like)
                    ->orWhere('last_name', 'ilike', $like)
                    ->orWhere('employee_id', 'ilike', $like)));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($advanceId = $request->query('cash_advance_id')) {
            $query->where('cash_advance_id', $advanceId);
        }
        if ($employeeId = $request->query('employee_id')) {
            $query->whereHas('cashAdvance', fn ($a) => $a->where('employee_id', $employeeId));
        }
        if ($bankId = $request->query('bank_account_id')) {
            $query->where('bank_account_id', $bankId);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('settled_on', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('settled_on', '<=', $to);
        }

        return $this->paginatedResponse(CashAdvanceSettlementResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): CashAdvanceSettlementResource|JsonResponse
    {
        Gate::authorize('create', CashAdvanceSettlement::class);
        $data = $this->validatePayload($request);

        try {
            $settlement = $this->service->record($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new CashAdvanceSettlementResource($settlement);
    }

    public function show(CashAdvanceSettlement $cashAdvanceSettlement): CashAdvanceSettlementResource
    {
        Gate::authorize('view', $cashAdvanceSettlement);
        return new CashAdvanceSettlementResource(
            $cashAdvanceSettlement->load([
                'cashAdvance.employee',
                'cashAdvance.receivableAccount',
                'bankAccount.glAccount',
                'lines.account',
                'journalEntry',
            ])
        );
    }

    public function cancel(CashAdvanceSettlement $cashAdvanceSettlement): CashAdvanceSettlementResource|JsonResponse
    {
        Gate::authorize('cancel', $cashAdvanceSettlement);

        try {
            $settlement = $this->service->cancel($cashAdvanceSettlement);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new CashAdvanceSettlementResource($settlement);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'settlement_number'           => 'required|string|max:64|unique:cash_advance_settlements,settlement_number',
            'cash_advance_id'             => 'required|uuid|exists:cash_advances,id',
            'bank_account_id'             => 'sometimes|nullable|uuid|exists:bank_accounts,id',
            'settled_on'                  => 'required|date',
            'actual_amount'               => 'required|numeric|gt:0',
            'unused_returned'             => 'sometimes|nullable|numeric|gte:0',
            'payment_method'              => 'sometimes|nullable|string|max:40',
            'reference_number'            => 'sometimes|nullable|string|max:64',
            'notes'                       => 'sometimes|nullable|string|max:2000',

            'lines'                       => 'required|array|min:1',
            'lines.*.account_id'          => 'required|uuid|exists:accounts,id',
            'lines.*.description'         => 'sometimes|nullable|string|max:500',
            'lines.*.amount'              => 'required|numeric|gt:0',
            'lines.*.receipt_attachment'  => 'sometimes|nullable|string|max:500',
        ]);
    }
}
