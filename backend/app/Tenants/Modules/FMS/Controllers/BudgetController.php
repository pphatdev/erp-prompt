<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Budget;
use App\Models\Tenant\BudgetLine;
use App\Tenants\Modules\FMS\Resources\BudgetLineResource;
use App\Tenants\Modules\FMS\Resources\BudgetResource;
use App\Tenants\Modules\FMS\Services\BudgetService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BudgetController extends Controller
{
    use Paginates;

    public function __construct(private readonly BudgetService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Budget::class);

        $query = $this->service->buildQuery();

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('budget_number', 'ilike', $like)
                ->orWhere('name', 'ilike', $like));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('end_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('start_date', '<=', $to);
        }

        return $this->paginatedResponse(BudgetResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): BudgetResource|JsonResponse
    {
        Gate::authorize('create', Budget::class);
        $data = $this->validatePayload($request);

        try {
            $budget = $this->service->create($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new BudgetResource($budget);
    }

    public function show(Budget $budget): BudgetResource
    {
        Gate::authorize('view', $budget);
        return new BudgetResource($budget->load('lines.account'));
    }

    public function update(Request $request, Budget $budget): BudgetResource|JsonResponse
    {
        Gate::authorize('update', $budget);
        $data = $request->validate([
            'name'       => 'sometimes|string|max:200',
            'start_date' => 'sometimes|date',
            'end_date'   => 'sometimes|date|after_or_equal:start_date',
            'notes'      => 'sometimes|nullable|string|max:2000',
        ]);
        try {
            $budget = $this->service->update($budget, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new BudgetResource($budget);
    }

    public function destroy(Budget $budget): JsonResponse
    {
        Gate::authorize('delete', $budget);
        try {
            $this->service->delete($budget);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => ['deleted' => true]]);
    }

    public function activate(Budget $budget): BudgetResource|JsonResponse
    {
        Gate::authorize('activate', $budget);
        try {
            $budget = $this->service->activate($budget);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new BudgetResource($budget);
    }

    public function archive(Budget $budget): BudgetResource|JsonResponse
    {
        Gate::authorize('archive', $budget);
        try {
            $budget = $this->service->archive($budget);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new BudgetResource($budget);
    }

    public function variance(Budget $budget): BudgetResource
    {
        Gate::authorize('view', $budget);
        $result = $this->service->computeVariance($budget);
        return (new BudgetResource($result['budget']))
            ->additional(['variance' => $result['variance']]);
    }

    // ----- Lines (separate endpoints to keep payloads small while drafting)

    public function addLine(Request $request, Budget $budget): BudgetLineResource|JsonResponse
    {
        Gate::authorize('update', $budget);
        $data = $request->validate([
            'account_id'      => 'required|uuid|exists:accounts,id',
            'expected_amount' => 'required|numeric|min:0',
            'notes'           => 'sometimes|nullable|string|max:500',
        ]);
        try {
            $line = $this->service->addLine($budget, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new BudgetLineResource($line->load('account'));
    }

    public function updateLine(Request $request, BudgetLine $line): BudgetLineResource|JsonResponse
    {
        Gate::authorize('update', $line->budget);
        $data = $request->validate([
            'expected_amount' => 'sometimes|numeric|min:0',
            'notes'           => 'sometimes|nullable|string|max:500',
        ]);
        try {
            $line = $this->service->updateLine($line, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new BudgetLineResource($line->load('account'));
    }

    public function removeLine(BudgetLine $line): JsonResponse
    {
        Gate::authorize('update', $line->budget);
        try {
            $this->service->removeLine($line);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => ['deleted' => true]]);
    }

    private function validatePayload(Request $request): array
    {
        return $request->validate([
            'budget_number'         => 'required|string|max:64|unique:budgets,budget_number',
            'name'                  => 'required|string|max:200',
            'start_date'            => 'required|date',
            'end_date'              => 'required|date|after_or_equal:start_date',
            'notes'                 => 'sometimes|nullable|string|max:2000',

            'lines'                 => 'sometimes|array',
            'lines.*.account_id'    => 'required|uuid|exists:accounts,id',
            'lines.*.expected_amount' => 'required|numeric|min:0',
            'lines.*.notes'         => 'sometimes|nullable|string|max:500',
        ]);
    }
}
