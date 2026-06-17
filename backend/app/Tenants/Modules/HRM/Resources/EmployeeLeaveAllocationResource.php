<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeLeaveAllocationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'employeeId'     => $this->employee_id,
            'leaveTypeId'    => $this->leave_type_id,
            'year'           => (int) $this->year,
            'allocatedDays'  => round((float) $this->allocated_days, 2),
            'usedDays'       => round((float) $this->used_days, 2),
            'pendingDays'    => round((float) $this->pending_days, 2),
            'remainingDays'  => $this->remainingDays(),
            'note'           => $this->note,
            'employee'       => $this->whenLoaded('employee', fn () => [
                'id'         => $this->employee?->id,
                'employeeId' => $this->employee?->employee_id,
                'firstName'  => $this->employee?->first_name,
                'lastName'   => $this->employee?->last_name,
            ]),
            'leaveType'      => $this->whenLoaded('leaveType', fn () => [
                'id'              => $this->leaveType?->id,
                'name'            => $this->leaveType?->name,
                'code'            => $this->leaveType?->code,
                'annualAllowance' => (int) ($this->leaveType?->annual_allowance ?? 0),
                'isPaid'          => (bool) ($this->leaveType?->is_paid ?? true),
                'isAccrued'       => (bool) ($this->leaveType?->is_accrued ?? false),
            ]),
            'createdAt'      => optional($this->created_at)->toIso8601String(),
            'updatedAt'      => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
