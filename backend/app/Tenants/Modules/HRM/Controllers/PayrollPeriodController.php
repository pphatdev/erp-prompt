<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\PayrollPeriod;
use App\Tenants\Modules\HRM\Requests\StorePayrollPeriodRequest;
use App\Tenants\Modules\HRM\Resources\PayrollPeriodResource;
use App\Tenants\Modules\HRM\Resources\PayslipResource;
use App\Tenants\Modules\HRM\Services\PayrollService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PayrollPeriodController extends Controller
{
    use Paginates;

    public function __construct(private readonly PayrollService $payroll)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery(
            PayrollPeriod::query()->withCount('payslips')->orderBy('start_date', 'desc'),
            $request
        );

        return $this->paginatedResponse(PayrollPeriodResource::class, $paginator, $request);
    }

    public function store(StorePayrollPeriodRequest $request): PayrollPeriodResource
    {
        return new PayrollPeriodResource($this->payroll->createPeriod($request->validated()));
    }

    public function show(PayrollPeriod $payrollPeriod): PayrollPeriodResource
    {
        return new PayrollPeriodResource($payrollPeriod->loadCount('payslips'));
    }

    public function process(PayrollPeriod $payrollPeriod): AnonymousResourceCollection|JsonResponse
    {
        try {
            $payslips = $this->payroll->processPeriod($payrollPeriod);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return PayslipResource::collection($payslips);
    }

    public function close(PayrollPeriod $payrollPeriod): PayrollPeriodResource|JsonResponse
    {
        try {
            $period = $this->payroll->closePeriod($payrollPeriod);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new PayrollPeriodResource($period);
    }
}
