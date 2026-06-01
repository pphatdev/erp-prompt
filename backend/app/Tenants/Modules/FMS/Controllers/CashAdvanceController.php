<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\CashAdvance;
use App\Tenants\Modules\FMS\Resources\CashAdvanceResource;
use App\Tenants\Modules\FMS\Services\CashAdvanceService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class CashAdvanceController extends Controller
{
    use Paginates;

    public function __construct(private readonly CashAdvanceService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CashAdvance::class);

        $query = $this->service->buildQuery();

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('advance_number', 'ilike', $like)
                ->orWhere('reference_number', 'ilike', $like)
                ->orWhere('purpose', 'ilike', $like)
                ->orWhereHas('employee', fn ($e) => $e
                    ->where('first_name', 'ilike', $like)
                    ->orWhere('last_name', 'ilike', $like)
                    ->orWhere('employee_id', 'ilike', $like)));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($employeeId = $request->query('employee_id')) {
            $query->where('employee_id', $employeeId);
        }
        if ($request->boolean('open_only')) {
            $query->whereIn('status', CashAdvance::OPEN_STATUSES);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('issued_on', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('issued_on', '<=', $to);
        }

        return $this->paginatedResponse(CashAdvanceResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): CashAdvanceResource|JsonResponse
    {
        Gate::authorize('create', CashAdvance::class);
        $data = $this->validatePayload($request);

        try {
            $advance = $this->service->issue($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new CashAdvanceResource($advance);
    }

    public function show(CashAdvance $cashAdvance): CashAdvanceResource
    {
        Gate::authorize('view', $cashAdvance);
        return new CashAdvanceResource(
            $cashAdvance->load(['employee', 'bankAccount.glAccount', 'receivableAccount', 'journalEntry'])
        );
    }

    public function cancel(CashAdvance $cashAdvance): CashAdvanceResource|JsonResponse
    {
        Gate::authorize('cancel', $cashAdvance);

        try {
            $advance = $this->service->cancel($cashAdvance);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new CashAdvanceResource($advance);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'advance_number'         => 'required|string|max:64|unique:cash_advances,advance_number',
            'employee_id'            => 'required|uuid|exists:employees,id',
            'bank_account_id'        => 'required|uuid|exists:bank_accounts,id',
            'receivable_account_id'  => 'required|uuid|exists:accounts,id',
            'issued_on'              => 'required|date',
            'amount'                 => 'required|numeric|gt:0',
            'currency'               => 'sometimes|nullable|string|size:3',
            'payment_method'         => 'sometimes|nullable|string|max:40',
            'reference_number'       => 'sometimes|nullable|string|max:64',
            'purpose'                => 'sometimes|nullable|string|max:500',
            'notes'                  => 'sometimes|nullable|string|max:2000',
        ]);
    }
}
