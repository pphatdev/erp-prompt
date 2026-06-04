<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Employee;
use App\Tenants\Modules\HRM\Requests\StoreEmployeeRequest;
use App\Tenants\Modules\HRM\Requests\UpdateEmployeeRequest;
use App\Tenants\Modules\HRM\Requests\UpdateEmployeeSelfRequest;
use App\Tenants\Modules\HRM\Resources\EmployeeResource;
use App\Tenants\Modules\HRM\Services\EmployeeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

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

    public function uploadAvatar(Request $request, Employee $employee): EmployeeResource|JsonResponse
    {
        $this->authorize('update', $employee);

        $request->validate(['image' => 'required|image|max:2048']);

        if ($employee->image_path) {
            Storage::disk('public')->delete($employee->image_path);
        }
        $path = $request->file('image')->store($this->imageDir(), 'public');
        $employee->update(['image_path' => $path]);

        return new EmployeeResource($employee->fresh(['department', 'position']));
    }

    public function deleteAvatar(Employee $employee): EmployeeResource
    {
        $this->authorize('update', $employee);

        if ($employee->image_path) {
            Storage::disk('public')->delete($employee->image_path);
            $employee->update(['image_path' => null]);
        }

        return new EmployeeResource($employee->fresh(['department', 'position']));
    }

    public function show(Employee $employee): EmployeeResource
    {
        $this->authorize('view', $employee);
        return new EmployeeResource($employee->load(['department', 'position', 'assets']));
    }

    public function update(UpdateEmployeeRequest $request, Employee $employee): EmployeeResource
    {
        $this->authorize('update', $employee);
        $employee = $this->employees->updateEmployee($employee, $request->validated());

        return new EmployeeResource($employee);
    }

    private function imageDir(): string
    {
        // `tenant('id')` returns null because the Tenant model uses `handle` as its key.
        $key = tenant()?->getTenantKey() ?? 'default';
        return "employees/{$key}";
    }

    public function destroy(Employee $employee): JsonResponse
    {
        $this->authorize('delete', $employee);
        $this->employees->terminateEmployee($employee);

        return response()->json(['message' => 'Employee terminated.'], 200);
    }

    /**
     * Self-service: return the authenticated user's own employee record.
     * 404s if the user has no linked employee row (e.g. external admin
     * accounts) so the frontend can react without parsing 403 vs. 200-empty.
     */
    public function me(Request $request): EmployeeResource|JsonResponse
    {
        $employee = $request->user()?->employee;
        if (!$employee) {
            return response()->json(['message' => 'No employee record linked to this account.'], 404);
        }

        $this->authorize('view', $employee);

        return new EmployeeResource($employee->load(['department', 'position', 'assets']));
    }

    /**
     * Self-service: update non-sensitive fields on the caller's own employee
     * record. UpdateEmployeeSelfRequest enforces the field whitelist; the
     * policy enforces row ownership + `.self` permission. Salary, bank,
     * department, position, email, and status remain admin-only.
     */
    public function updateSelf(UpdateEmployeeSelfRequest $request): EmployeeResource|JsonResponse
    {
        $employee = $request->user()?->employee;
        if (!$employee) {
            return response()->json(['message' => 'No employee record linked to this account.'], 404);
        }

        $this->authorize('updateSelf', $employee);

        $employee = $this->employees->updateEmployee($employee, $request->validated());

        return new EmployeeResource($employee);
    }
}
