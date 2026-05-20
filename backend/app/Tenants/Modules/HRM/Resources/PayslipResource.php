<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayslipResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeeOthers = $request->user()?->can('hrm.payroll.read') ?? false;
        $isOwner = $request->user()?->employee?->id === $this->employee_id;

        $visible = $canSeeOthers || $isOwner;

        return [
            'id' => $this->id,
            'payrollPeriodId' => $this->payroll_period_id,
            'employeeId' => $this->employee_id,
            'grossSalary' => $visible ? (float) $this->gross_salary : null,
            'netSalary' => $visible ? (float) $this->net_salary : null,
            'earnings' => $visible ? $this->earnings : null,
            'deductions' => $visible ? $this->deductions : null,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? new EmployeeResource($this->employee) : null),
            'payrollPeriod' => $this->whenLoaded('payrollPeriod', fn () => $this->payrollPeriod ? new PayrollPeriodResource($this->payrollPeriod) : null),
            'createdAt' => optional($this->created_at)->toIso8601String(),
        ];
    }
}
