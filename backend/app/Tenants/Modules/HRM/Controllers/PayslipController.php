<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Payslip;
use App\Tenants\Modules\HRM\Resources\PayslipResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PayslipController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Payslip::class);

        $query = Payslip::query()->with(['employee', 'payrollPeriod']);

        $user = $request->user();
        $isAdmin = $user?->hasPermission('hrm.payroll.read');

        // Self-service caller (`.self` only) — force-filter to their own
        // employee_id so they can't enumerate other employees' payslips by
        // dropping or rewriting the `employeeId` query param.
        if (!$isAdmin) {
            $selfId = $user?->employee?->id;
            if ($selfId === null) {
                return response()->json(['data' => [], 'pagination' => ['page' => 1, 'limit' => 0, 'total' => 0, 'totalPages' => 0]], 200);
            }
            $query->where('employee_id', $selfId);
        } elseif ($employeeId = $request->query('employeeId')) {
            $query->where('employee_id', $employeeId);
        }

        if ($periodId = $request->query('payrollPeriodId')) {
            $query->where('payroll_period_id', $periodId);
        }

        $paginator = $this->paginateQuery($query->orderBy('created_at', 'desc'), $request);

        return $this->paginatedResponse(PayslipResource::class, $paginator, $request);
    }

    public function show(Payslip $payslip): PayslipResource
    {
        $this->authorize('view', $payslip);

        return new PayslipResource($payslip->load(['employee', 'payrollPeriod']));
    }
}
