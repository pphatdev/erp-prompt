<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\BankAccount;
use App\Tenants\Modules\FMS\Resources\BankAccountResource;
use App\Tenants\Modules\FMS\Services\BankAccountService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BankAccountController extends Controller
{
    use Paginates;

    public function __construct(private readonly BankAccountService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BankAccount::class);

        $query = $this->service->buildQuery();

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', $like)
                ->orWhere('bank_name', 'ilike', $like)
                ->orWhere('account_number', 'ilike', $like)
                ->orWhere('account_holder', 'ilike', $like));
        }
        if ($currency = $request->query('currency')) {
            $query->where('currency', strtoupper($currency));
        }
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }
        if ($request->boolean('default_only')) {
            $query->where('is_default', true);
        }

        return $this->paginatedResponse(BankAccountResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): BankAccountResource|JsonResponse
    {
        Gate::authorize('create', BankAccount::class);
        $data = $this->validatePayload($request);

        try {
            $bank = $this->service->create($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new BankAccountResource($bank);
    }

    public function show(BankAccount $bankAccount): BankAccountResource
    {
        Gate::authorize('view', $bankAccount);
        return new BankAccountResource($bankAccount->load('glAccount'));
    }

    public function update(Request $request, BankAccount $bankAccount): BankAccountResource|JsonResponse
    {
        Gate::authorize('update', $bankAccount);
        $data = $this->validatePayload($request, $bankAccount);

        try {
            $bank = $this->service->update($bankAccount, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new BankAccountResource($bank);
    }

    public function destroy(BankAccount $bankAccount): JsonResponse
    {
        Gate::authorize('delete', $bankAccount);

        try {
            $this->service->archive($bankAccount);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Bank account archived.']);
    }

    private function validatePayload(Request $request, ?BankAccount $existing = null): array
    {
        $isUpdate = $existing !== null;
        $req = $isUpdate ? 'sometimes' : 'required';
        return $request->validate([
            'account_id'              => 'sometimes|nullable|uuid|exists:accounts,id',
            'name'                    => "{$req}|string|max:160",
            'bank_name'               => "{$req}|string|max:160",
            'branch'                  => 'sometimes|nullable|string|max:160',
            'account_number'          => 'sometimes|nullable|string|max:60',
            'account_holder'          => 'sometimes|nullable|string|max:160',
            'swift'                   => 'sometimes|nullable|string|max:20',
            'iban'                    => 'sometimes|nullable|string|max:40',
            'currency'                => 'sometimes|string|size:3',
            'opening_balance'         => 'sometimes|nullable|numeric',
            'last_reconciled_at'      => 'sometimes|nullable|date',
            'last_reconciled_balance' => 'sometimes|nullable|numeric',
            'notes'                   => 'sometimes|nullable|string|max:2000',
            'is_active'               => 'sometimes|boolean',
            'is_default'              => 'sometimes|boolean',
        ]);
    }
}
