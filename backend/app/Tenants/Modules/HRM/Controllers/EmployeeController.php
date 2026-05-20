<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Employee;
use App\Tenants\Modules\HRM\Requests\StoreEmployeeRequest;
use App\Tenants\Modules\HRM\Requests\UpdateEmployeeRequest;
use App\Tenants\Modules\HRM\Resources\EmployeeResource;
use App\Tenants\Modules\HRM\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmployeeController extends Controller
{
    use Paginates;

    public function __construct(private readonly EmployeeService $employees)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);
        $filters = $request->only(['status', 'departmentId', 'search']);
        $paginator = $this->paginateQuery($this->employees->buildIndexQuery($filters), $request);

        return $this->paginatedResponse(EmployeeResource::class, $paginator, $request);
    }

    public function store(StoreEmployeeRequest $request): EmployeeResource
    {
        $this->authorize('create', Employee::class);
        $employee = $this->employees->createEmployee($request->validated());

        return new EmployeeResource($employee->load(['department', 'position']));
    }

    public function show(Employee $employee): EmployeeResource
    {
        $this->authorize('view', $employee);
        return new EmployeeResource($employee->load(['department', 'position']));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $this->authorize('update', $employee);
        $employee = $this->employees->updateEmployee($employee, $request->validated());

        return new EmployeeResource($employee);
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $this->authorize('delete', $employee);
        $this->employees->terminateEmployee($employee);

        return response()->json(['message' => 'Employee terminated.'], 200);
    }
}
