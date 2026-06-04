<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeePayroll = $request->user()?->can('hrm.payroll.read') ?? false;

        $prefix = app(\App\Tenants\Modules\Settings\Services\SettingService::class)->get('numbering.employee_id_prefix') ?: (\App\Tenants\Modules\HRM\Services\RecruitmentService::EMPLOYEE_ID_PREFIX . '-');
        $employeeId = $this->employee_id;
        if ($employeeId && preg_match('/(\d+)$/', $employeeId, $matches)) {
            $employeeId = $prefix . $matches[1];
        }

        return [
            'id' => $this->id,
            'employeeId' => $employeeId,
            'firstName' => $this->first_name,
            'lastName' => $this->last_name,
            'fullName' => trim("{$this->first_name} {$this->last_name}"),
            'email' => $this->email,
            'gender' => $this->gender,
            'phone' => $this->phone,
            'imageUrl' => $this->image_path ? asset('storage/' . $this->image_path) : null,
            'status' => $this->status,
            'hiredAt' => optional($this->hired_at)->toDateString(),
            'baseSalary' => $canSeePayroll ? ($this->base_salary === null ? null : (float) $this->base_salary) : null,
            'bankName' => $canSeePayroll ? $this->bank_name : null,
            'bankAccountName' => $canSeePayroll ? $this->bank_account_name : null,
            'bankAccountNumber' => $canSeePayroll ? $this->maskedAccount($this->bank_account_number) : null,
            'department' => $this->whenLoaded('department', fn () => $this->department ? new DepartmentResource($this->department) : null),
            'position' => $this->whenLoaded('position', fn () => $this->position ? new PositionResource($this->position) : null),
            'applications' => $this->whenLoaded('applications', fn () => ApplicationResource::collection($this->applications)),
            'assets' => $this->whenLoaded('assets', fn () => \App\Tenants\Modules\Assets\Resources\AssetResource::collection($this->assets)),
            'createdAt' => optional($this->created_at)->toIso8601String(),
            'updatedAt' => optional($this->updated_at)->toIso8601String(),
        ];
    }

    private function maskedAccount(?string $account): ?string
    {
        if ($account === null || $account === '') {
            return $account;
        }
        $len = strlen($account);
        if ($len <= 4) {
            return str_repeat('•', $len);
        }
        return str_repeat('•', $len - 4) . substr($account, -4);
    }
}
