<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Account;
use App\Tenants\Modules\FMS\Resources\AccountResource;
use App\Tenants\Modules\FMS\Services\AccountService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AccountController extends Controller
{
    use Paginates;

    public function __construct(private readonly AccountService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Account::class);

        if ($request->boolean('tree')) {
            return response()->json(['data' => $this->service->tree()]);
        }

        $query = $this->service->buildQuery();

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', $like)
                ->orWhere('code', 'ilike', $like));
        }
        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }
        if ($request->has('parent_id')) {
            $parent = $request->query('parent_id');
            if ($parent === '' || $parent === 'null') {
                $query->whereNull('parent_id');
            } else {
                $query->where('parent_id', $parent);
            }
        }

        return $this->paginatedResponse(AccountResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): AccountResource|JsonResponse
    {
        Gate::authorize('create', Account::class);
        $data = $this->validatePayload($request);

        try {
            $account = $this->service->create($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new AccountResource($account);
    }

    public function show(Account $account): AccountResource
    {
        Gate::authorize('view', $account);
        return new AccountResource($account->load('parent'));
    }

    public function update(Request $request, Account $account): AccountResource|JsonResponse
    {
        Gate::authorize('update', $account);
        $data = $this->validatePayload($request, $account);

        try {
            $a = $this->service->update($account, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new AccountResource($a);
    }

    public function destroy(Account $account): JsonResponse
    {
        Gate::authorize('delete', $account);

        try {
            $this->service->archive($account);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['message' => 'Account archived.']);
    }

    private function validatePayload(Request $request, ?Account $existing = null): array
    {
        $isUpdate = $existing !== null;
        $req = $isUpdate ? 'sometimes' : 'required';
        return $request->validate([
            'code'      => "{$req}|string|max:32",
            'name'      => "{$req}|string|max:160",
            'type'      => "{$req}|in:asset,liability,equity,revenue,expense",
            'parent_id' => 'sometimes|nullable|uuid|exists:accounts,id',
        ]);
    }
}
