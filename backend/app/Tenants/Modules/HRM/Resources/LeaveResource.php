<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $activeApproval = $this->activeApprovalRequest();

        return [
            'id' => $this->id,
            'employeeId' => $this->employee_id,
            'leaveTypeId' => $this->leave_type_id,
            'startDate' => optional($this->start_date)->toDateString(),
            'endDate' => optional($this->end_date)->toDateString(),
            'days' => (float) $this->days,
            'leaveSession' => $this->leave_session ?? 'full_day',
            'reason' => $this->reason,
            'status' => $this->status,
            'approvalRequestId' => $activeApproval?->id,
            'approvalStatus' => $activeApproval?->status,
            'employee' => $this->whenLoaded('employee', fn () => $this->employee ? new EmployeeResource($this->employee) : null),
            'leaveType' => $this->whenLoaded('leaveType', fn () => $this->leaveType ? new LeaveTypeResource($this->leaveType) : null),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
