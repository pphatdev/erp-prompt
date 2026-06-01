<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Reimbursement;
use App\Tenants\Modules\FMS\Resources\ReimbursementResource;
use App\Tenants\Modules\FMS\Services\ReimbursementService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ReimbursementController extends Controller
{
    use Paginates;

    public function __construct(private readonly ReimbursementService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Reimbursement::class);

        $query = $this->service->buildQuery();

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('reimbursement_number', 'ilike', $like)
                ->orWhere('reference_number', 'ilike', $like)
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
        if ($bankId = $request->query('bank_account_id')) {
            $query->where('bank_account_id', $bankId);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('paid_on', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('paid_on', '<=', $to);
        }

        return $this->paginatedResponse(ReimbursementResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): ReimbursementResource|JsonResponse
    {
        Gate::authorize('create', Reimbursement::class);
        $data = $this->validatePayload($request);

        try {
            $reimb = $this->service->record($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ReimbursementResource($reimb);
    }

    public function show(Reimbursement $reimbursement): ReimbursementResource
    {
        Gate::authorize('view', $reimbursement);
        return new ReimbursementResource(
            $reimbursement->load(['employee', 'bankAccount.glAccount', 'lines.account', 'journalEntry'])
        );
    }

    public function cancel(Reimbursement $reimbursement): ReimbursementResource|JsonResponse
    {
        Gate::authorize('cancel', $reimbursement);

        try {
            $reimb = $this->service->cancel($reimbursement);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ReimbursementResource($reimb);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'reimbursement_number'        => 'required|string|max:64|unique:reimbursements,reimbursement_number',
            'employee_id'                 => 'required|uuid|exists:employees,id',
            'bank_account_id'             => 'required|uuid|exists:bank_accounts,id',
            'paid_on'                     => 'required|date',
            'amount'                      => 'required|numeric|gt:0',
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
