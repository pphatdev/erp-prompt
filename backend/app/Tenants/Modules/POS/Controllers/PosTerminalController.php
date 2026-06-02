<?php

declare(strict_types=1);

namespace App\Tenants\Modules\POS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\PosTerminal;
use App\Tenants\Modules\POS\Resources\PosTerminalResource;
use App\Tenants\Modules\POS\Services\PosTerminalService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PosTerminalController extends Controller
{
    use Paginates;

    public function __construct(private readonly PosTerminalService $terminals)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PosTerminal::class);
        $query = PosTerminal::query()
            ->with(['warehouse', 'pettyCashAccount'])
            ->orderBy('code');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        return $this->paginatedResponse(PosTerminalResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function show(PosTerminal $terminal): PosTerminalResource
    {
        $this->authorize('view', $terminal);
        return new PosTerminalResource($terminal->load(['warehouse', 'pettyCashAccount']));
    }

    public function store(Request $request): PosTerminalResource|JsonResponse
    {
        $this->authorize('create', PosTerminal::class);
        $data = $request->validate([
            'code' => 'required|string|max:32',
            'name' => 'required|string|max:120',
            'warehouse_id' => 'required|exists:warehouses,id',
            'petty_cash_account_id' => 'sometimes|nullable|exists:accounts,id',
            'location' => 'sometimes|nullable|string|max:255',
            'status' => 'sometimes|in:active,disabled',
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);
        try {
            $terminal = $this->terminals->create($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new PosTerminalResource($terminal->load(['warehouse', 'pettyCashAccount']));
    }

    public function update(Request $request, PosTerminal $terminal): PosTerminalResource|JsonResponse
    {
        $this->authorize('update', $terminal);
        $data = $request->validate([
            'code' => 'sometimes|string|max:32',
            'name' => 'sometimes|string|max:120',
            'warehouse_id' => 'sometimes|exists:warehouses,id',
            'petty_cash_account_id' => 'sometimes|nullable|exists:accounts,id',
            'location' => 'sometimes|nullable|string|max:255',
            'status' => 'sometimes|in:active,disabled',
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);
        try {
            $terminal = $this->terminals->update($terminal, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new PosTerminalResource($terminal->load(['warehouse', 'pettyCashAccount']));
    }

    public function destroy(PosTerminal $terminal): JsonResponse
    {
        $this->authorize('delete', $terminal);
        try {
            $this->terminals->destroy($terminal);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['message' => 'Terminal deleted.']);
    }
}
