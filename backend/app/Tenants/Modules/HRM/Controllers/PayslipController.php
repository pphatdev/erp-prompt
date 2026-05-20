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
        $query = Payslip::query()->with(['employee', 'payrollPeriod']);

        if ($employeeId = $request->query('employeeId')) {
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
        return new PayslipResource($payslip->load(['employee', 'payrollPeriod']));
    }
}
