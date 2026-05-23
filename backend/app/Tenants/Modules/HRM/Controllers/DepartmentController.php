<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Department;
use App\Tenants\Modules\HRM\Requests\StoreDepartmentRequest;
use App\Tenants\Modules\HRM\Resources\DepartmentResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DepartmentController extends Controller
{
    use Paginates;

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Department::class);

        $paginator = $this->paginateQuery(Department::query()->orderBy('name'), $request);

        return $this->paginatedResponse(DepartmentResource::class, $paginator, $request);
    }

    public function store(StoreDepartmentRequest $request): DepartmentResource
    {
        $this->authorize('create', Department::class);

        return new DepartmentResource(Department::create($request->validated()));
    }

    public function show(Department $department): DepartmentResource
    {
        $this->authorize('view', $department);

        return new DepartmentResource($department);
    }

    public function update(StoreDepartmentRequest $request, Department $department): DepartmentResource
    {
        $this->authorize('update', $department);

        $department->update($request->validated());

        return new DepartmentResource($department);
    }

    public function destroy(Department $department): JsonResponse
    {
        $this->authorize('delete', $department);

        $department->delete();

        return response()->json(['message' => 'Department archived.'], 200);
    }
}
