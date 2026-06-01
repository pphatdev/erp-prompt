<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Account;
use App\Models\Tenant\FiscalPeriod;
use App\Tenants\Modules\FMS\Resources\FiscalPeriodResource;
use App\Tenants\Modules\FMS\Services\PeriodClosingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class FiscalPeriodController extends Controller
{
    use Paginates;

    public function __construct(private readonly PeriodClosingService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', FiscalPeriod::class);

        $query = $this->service->buildQuery();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('period_number', 'ilike', $like)
                ->orWhere('name', 'ilike', $like));
        }

        return $this->paginatedResponse(FiscalPeriodResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): FiscalPeriodResource|JsonResponse
    {
        Gate::authorize('create', FiscalPeriod::class);
        $data = $request->validate([
            'period_number' => 'required|string|max:64|unique:fiscal_periods,period_number',
            'name'          => 'required|string|max:200',
            'start_date'    => 'required|date',
            'end_date'      => 'required|date|after_or_equal:start_date',
            'notes'         => 'sometimes|nullable|string|max:2000',
        ]);
        try {
            $period = $this->service->create($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new FiscalPeriodResource($period);
    }

    public function show(FiscalPeriod $fiscalPeriod): FiscalPeriodResource
    {
        Gate::authorize('view', $fiscalPeriod);
        return new FiscalPeriodResource(
            $fiscalPeriod->load(['retainedEarningsAccount', 'closingJournalEntry'])
        );
    }

    public function update(Request $request, FiscalPeriod $fiscalPeriod): FiscalPeriodResource|JsonResponse
    {
        Gate::authorize('update', $fiscalPeriod);
        $data = $request->validate([
            'name'       => 'sometimes|string|max:200',
            'start_date' => 'sometimes|date',
            'end_date'   => 'sometimes|date|after_or_equal:start_date',
            'notes'      => 'sometimes|nullable|string|max:2000',
        ]);
        try {
            $period = $this->service->update($fiscalPeriod, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new FiscalPeriodResource($period);
    }

    public function destroy(FiscalPeriod $fiscalPeriod): JsonResponse
    {
        Gate::authorize('delete', $fiscalPeriod);
        try {
            $this->service->delete($fiscalPeriod);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => ['deleted' => true]]);
    }

    public function closingPreview(Request $request, FiscalPeriod $fiscalPeriod): JsonResponse
    {
        Gate::authorize('view', $fiscalPeriod);
        $reId = $request->query('retained_earnings_account_id');
        if (!$reId) {
            return response()->json(['message' => 'retained_earnings_account_id is required.'], 422);
        }
        try {
            $plan = $this->service->preview($fiscalPeriod, $reId);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json([
            'data' => [
                'revenue' => array_map(fn ($r) => [
                    'account' => [
                        'id'   => $r['account']->id,
                        'code' => $r['account']->code,
                        'name' => $r['account']->name,
                        'type' => $r['account']->type,
                    ],
                    'amount' => round((float) $r['amount'], 2),
                ], $plan['revenue']),
                'expense' => array_map(fn ($r) => [
                    'account' => [
                        'id'   => $r['account']->id,
                        'code' => $r['account']->code,
                        'name' => $r['account']->name,
                        'type' => $r['account']->type,
                    ],
                    'amount' => round((float) $r['amount'], 2),
                ], $plan['expense']),
                'net'        => $plan['net'],
                'retainedDr' => $plan['retainedDr'],
                'retainedCr' => $plan['retainedCr'],
            ],
        ]);
    }

    public function close(Request $request, FiscalPeriod $fiscalPeriod): FiscalPeriodResource|JsonResponse
    {
        Gate::authorize('close', $fiscalPeriod);
        $data = $request->validate([
            'retained_earnings_account_id' => 'required|uuid|exists:accounts,id',
            'notes'                        => 'sometimes|nullable|string|max:2000',
        ]);
        try {
            $period = $this->service->close($fiscalPeriod, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new FiscalPeriodResource($period);
    }

    public function reopen(FiscalPeriod $fiscalPeriod): FiscalPeriodResource|JsonResponse
    {
        Gate::authorize('reopen', $fiscalPeriod);
        try {
            $period = $this->service->reopen($fiscalPeriod);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new FiscalPeriodResource($period);
    }
}
