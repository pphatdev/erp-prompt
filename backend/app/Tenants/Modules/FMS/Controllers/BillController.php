<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Bill;
use App\Tenants\Modules\FMS\Resources\BillResource;
use App\Tenants\Modules\FMS\Services\BillService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BillController extends Controller
{
    use Paginates;

    public function __construct(private readonly BillService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', Bill::class);

        $query = $this->service->buildQuery();

        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('bill_number', 'ilike', $like)
                ->orWhere('supplier_invoice_number', 'ilike', $like)
                ->orWhereHas('supplier', fn ($s) => $s->where('name', 'ilike', $like)));
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($supplierId = $request->query('supplier_id')) {
            $query->where('supplier_id', $supplierId);
        }
        if ($poId = $request->query('po_id')) {
            $query->where('po_id', $poId);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('issue_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('issue_date', '<=', $to);
        }
        if ($request->boolean('open_only')) {
            $query->whereIn('status', Bill::OPEN_STATUSES);
        }

        return $this->paginatedResponse(BillResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): BillResource|JsonResponse
    {
        Gate::authorize('create', Bill::class);
        $data = $this->validatePayload($request);

        try {
            $bill = $this->service->create($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new BillResource($bill);
    }

    public function show(Bill $bill): BillResource
    {
        Gate::authorize('view', $bill);
        return new BillResource($bill->load(['supplier', 'lines.account', 'payableAccount', 'journalEntry']));
    }

    public function update(Request $request, Bill $bill): BillResource|JsonResponse
    {
        Gate::authorize('update', $bill);
        $data = $this->validatePayload($request, $bill);

        try {
            $bill = $this->service->update($bill, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new BillResource($bill);
    }

    public function approve(Bill $bill): BillResource|JsonResponse
    {
        Gate::authorize('approve', $bill);

        try {
            $bill = $this->service->approve($bill);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new BillResource($bill);
    }

    public function cancel(Bill $bill): BillResource|JsonResponse
    {
        Gate::authorize('cancel', $bill);

        try {
            $bill = $this->service->cancel($bill);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new BillResource($bill);
    }

    public function destroy(Bill $bill): JsonResponse
    {
        Gate::authorize('delete', $bill);
        $bill->delete();
        return response()->json(['message' => 'Bill archived.']);
    }

    private function validatePayload(Request $request, ?Bill $existing = null): array
    {
        $isUpdate = $existing !== null;
        $req = $isUpdate ? 'sometimes' : 'required';

        $billNumberRule = "{$req}|string|max:64|unique:bills,bill_number";
        if ($isUpdate) {
            $billNumberRule .= ",{$existing->id}";
        }

        return $request->validate([
            'bill_number'             => $billNumberRule,
            'supplier_invoice_number' => 'sometimes|nullable|string|max:64',
            'supplier_id'             => "{$req}|uuid|exists:suppliers,id",
            'po_id'                   => 'sometimes|nullable|uuid|exists:purchase_orders,id',
            'issue_date'              => "{$req}|date",
            'due_date'                => 'sometimes|nullable|date',
            'currency'                => 'sometimes|string|size:3',
            'tax_amount'              => 'sometimes|nullable|numeric|min:0',
            'payable_account_id'      => 'sometimes|nullable|uuid|exists:accounts,id',
            'notes'                   => 'sometimes|nullable|string|max:2000',

            'lines'                       => $isUpdate ? 'sometimes|array|min:1' : 'required|array|min:1',
            'lines.*.account_id'          => 'required_with:lines|uuid|exists:accounts,id',
            'lines.*.description'         => 'sometimes|nullable|string|max:500',
            'lines.*.quantity'            => 'required_with:lines|numeric|gt:0',
            'lines.*.unit_price'          => 'required_with:lines|numeric|gt:0',
        ]);
    }
}
