<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceLogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeeRaw = $request->user()?->hasPermission('hrm.attendance.read') ?? false;

        return [
            'id' => $this->id,
            'employeeId' => $this->employee_id,
            'date' => optional($this->date)->toDateString(),
            'checkIn' => optional($this->check_in)->toIso8601String(),
            'checkOut' => optional($this->check_out)->toIso8601String(),
            'status' => $this->status,
            // Raw IP + coordinates only flow to admins. Self-service callers
            // already know their own GPS and shouldn't see other employees' anyway.
            'checkInIp' => $canSeeRaw ? $this->check_in_ip : null,
            'checkOutIp' => $canSeeRaw ? $this->check_out_ip : null,
            'checkInLat' => $canSeeRaw ? $this->check_in_lat : null,
            'checkInLon' => $canSeeRaw ? $this->check_in_lon : null,
            'checkOutLat' => $canSeeRaw ? $this->check_out_lat : null,
            'checkOutLon' => $canSeeRaw ? $this->check_out_lon : null,
            'employee' => $this->whenLoaded('employee', fn () => new EmployeeResource($this->employee)),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
