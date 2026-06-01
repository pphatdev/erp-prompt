<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Expense;
use App\Tenants\Modules\FMS\Resources\ExpenseResource;
use App\Tenants\Modules\FMS\Services\ExpenseService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExpenseController extends Controller
{
    use Paginates;

    public function __construct(private readonly ExpenseService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Expense::class);

        $query = $this->service->buildQuery();

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('expense_number', 'ilike', $like)
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

        return $this->paginatedResponse(ExpenseResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): ExpenseResource|JsonResponse
    {
        Gate::authorize('create', Expense::class);
        $data = $this->validatePayload($request);

        try {
            $expense = $this->service->record($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ExpenseResource($expense);
    }

    public function show(Expense $expense): ExpenseResource
    {
        Gate::authorize('view', $expense);
        return new ExpenseResource(
            $expense->load(['bankAccount.glAccount', 'supplier', 'lines.account', 'journalEntry'])
        );
    }

    public function cancel(Expense $expense): ExpenseResource|JsonResponse
    {
        Gate::authorize('cancel', $expense);

        try {
            $expense = $this->service->cancel($expense);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ExpenseResource($expense);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'expense_number'              => 'required|string|max:64|unique:expenses,expense_number',
            'bank_account_id'             => 'required|uuid|exists:bank_accounts,id',
            'supplier_id'                 => 'sometimes|nullable|uuid|exists:suppliers,id',
            'paid_on'                     => 'required|date',
            'total'                       => 'required|numeric|gt:0',
            'currency'                    => 'sometimes|nullable|string|size:3',
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
