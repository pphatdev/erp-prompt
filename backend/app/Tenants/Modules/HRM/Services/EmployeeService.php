<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\Department;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Position;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class EmployeeService
{
    public function __construct(private readonly WorkflowStatusService $statuses)
    {
    }

    public function buildIndexQuery(array $filters = []): Builder
    {
        $query = Employee::query()->with(['department', 'position']);

        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['departmentId'])) {
            $query->where('department_id', $filters['departmentId']);
        }
        if (!empty($filters['search'])) {
            $term = '%' . $filters['search'] . '%';
            $query->where(function ($q) use ($term) {
                $q->where('first_name', 'ilike', $term)
                  ->orWhere('last_name', 'ilike', $term)
                  ->orWhere('email', 'ilike', $term)
                  ->orWhere('employee_id', 'ilike', $term);
            });
        }

        $query->orderBy('created_at', 'desc');

        return $query;
    }

    public function createEmployee(array $data): Employee
    {
        $data['status'] ??= $this->statuses->initialFor('hrm.employee');

        if (empty($data['employee_id'])) {
            $data['employee_id'] = app(\App\Tenants\Modules\HRM\Services\RecruitmentService::class)->generateNextEmployeeId();
        }

        return DB::transaction(function () use ($data) {
            return Employee::create($data);
        });
    }

    public function updateEmployee(Employee $employee, array $data): Employee
    {
        return DB::transaction(function () use ($employee, $data) {
            $employee->update($data);
            return $employee->fresh(['department', 'position']);
        });
    }

    public function terminateEmployee(Employee $employee): Employee
    {
        $this->statuses->validateTransition('hrm.employee', $employee->status, 'terminated');

        return DB::transaction(function () use ($employee) {
            $employee->update(['status' => 'terminated']);
            $employee->delete();
            return $employee;
        });
    }

    public function createDepartment(array $data): Department
    {
        return Department::create($data);
    }

    public function createPosition(array $data): Position
    {
        return Position::create($data);
    }
}
