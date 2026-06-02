<?php

declare(strict_types=1);

namespace App\Tenants\Modules\POS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\PosShift;
use App\Models\Tenant\PosTerminal;
use App\Tenants\Modules\POS\Resources\PosShiftResource;
use App\Tenants\Modules\POS\Services\PosShiftService;
use App\Tenants\Modules\POS\Services\PosShiftSupervisorService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PosShiftController extends Controller
{
    use Paginates;

    public function __construct(
        private readonly PosShiftService $shifts,
        private readonly PosShiftSupervisorService $supervisor,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', PosShift::class);
        $query = PosShift::query()
            ->with(['terminal', 'cashier'])
            ->orderByDesc('opened_at');
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($terminalId = $request->query('terminal_id')) {
            $query->where('terminal_id', $terminalId);
        }
        if ($cashierId = $request->query('cashier_id')) {
            $query->where('cashier_id', $cashierId);
        }
        return $this->paginatedResponse(PosShiftResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function show(PosShift $shift): PosShiftResource|JsonResponse
    {
        $this->authorize('view', $shift);
        return new PosShiftResource($shift->load(['terminal', 'cashier', 'orders']));
    }

    /**
     * Cashier-side "what register am I on?" helper.
     *
     * Resolution order:
     *   1. The actor's own open shift (cashier_id = Auth::id()).
     *   2. Admin override: when the actor holds `pos.shift.approve` and has
     *      no shift of their own, fall through to the most recent open
     *      shift in the tenant. The response carries `isOverride: true`
     *      so the UI can surface "managing on behalf of <cashier>".
     *
     * This unblocks the case where a cashier opens a shift, walks off, and
     * a supervisor needs to close / reconcile it without impersonating the
     * original user.
     */
    public function me(): JsonResponse
    {
        $user = Auth::user();
        $shift = $this->shifts->activeShiftForCashier($user);
        $isOverride = false;

        if (!$shift && $user?->hasPermission('pos.shift.approve')) {
            $shift = $this->shifts->latestOpenShift();
            $isOverride = $shift !== null;
        }

        if (!$shift) {
            return response()->json(['data' => null]);
        }

        $payload = (new PosShiftResource($shift))->toArray(request());
        $payload['isOverride'] = $isOverride;
        return response()->json(['data' => $payload]);
    }

    public function open(Request $request): PosShiftResource|JsonResponse
    {
        $data = $request->validate([
            'terminal_id' => 'required|exists:pos_terminals,id',
            'opening_float' => 'required|numeric|min:0',
        ]);
        $this->authorize('create', PosShift::class);

        $terminal = PosTerminal::findOrFail($data['terminal_id']);
        try {
            $shift = $this->shifts->openShift($terminal, Auth::user(), (float) $data['opening_float']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new PosShiftResource($shift->load(['terminal', 'cashier']));
    }

    public function close(Request $request, PosShift $shift): PosShiftResource|JsonResponse
    {
        $this->authorize('update', $shift);
        $data = $request->validate([
            'closing_cash' => 'required|numeric|min:0',
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);
        try {
            $shift = $this->shifts->closeShift($shift, (float) $data['closing_cash'], $data['notes'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new PosShiftResource($shift->load(['terminal', 'cashier']));
    }

    public function reconcile(Request $request, PosShift $shift): PosShiftResource|JsonResponse
    {
        $this->authorize('approve', $shift);
        $data = $request->validate([
            'notes' => 'sometimes|nullable|string|max:1000',
        ]);
        try {
            $shift = $this->supervisor->reconcileVariance($shift, $data['notes'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new PosShiftResource($shift->load(['terminal', 'cashier']));
    }
}
