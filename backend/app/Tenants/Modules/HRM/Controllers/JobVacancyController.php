<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\JobVacancy;
use App\Tenants\Modules\HRM\Requests\StoreJobVacancyRequest;
use App\Tenants\Modules\HRM\Resources\JobVacancyResource;
use App\Tenants\Modules\HRM\Services\RecruitmentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class JobVacancyController extends Controller
{
    use Paginates;

    public function __construct(private readonly RecruitmentService $recruitment)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', JobVacancy::class);

        $filters = $request->only(['status', 'departmentId', 'employmentType', 'search']);
        $paginator = $this->paginateQuery($this->recruitment->buildVacancyQuery($filters), $request);

        return $this->paginatedResponse(JobVacancyResource::class, $paginator, $request);
    }

    public function store(StoreJobVacancyRequest $request): JobVacancyResource
    {
        $this->authorize('create', JobVacancy::class);

        $vacancy = $this->recruitment->createVacancy($request->validated());

        return new JobVacancyResource($vacancy->load(['department', 'position']));
    }

    public function show(JobVacancy $jobVacancy): JobVacancyResource
    {
        $this->authorize('view', $jobVacancy);

        return new JobVacancyResource($jobVacancy->load(['department', 'position'])->loadCount('applications'));
    }

    public function update(StoreJobVacancyRequest $request, JobVacancy $jobVacancy): JobVacancyResource
    {
        $this->authorize('update', $jobVacancy);

        $vacancy = $this->recruitment->updateVacancy($jobVacancy, $request->validated());

        return new JobVacancyResource($vacancy);
    }

    public function destroy(JobVacancy $jobVacancy): JsonResponse
    {
        $this->authorize('delete', $jobVacancy);

        $jobVacancy->delete();

        return response()->json(['message' => 'Vacancy archived.'], 200);
    }

    public function publish(JobVacancy $jobVacancy): JobVacancyResource|JsonResponse
    {
        $this->authorize('publish', $jobVacancy);

        try {
            $vacancy = $this->recruitment->publishVacancy($jobVacancy);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new JobVacancyResource($vacancy);
    }

    public function close(Request $request, JobVacancy $jobVacancy): JobVacancyResource|JsonResponse
    {
        $this->authorize('close', $jobVacancy);

        $reason = $request->input('reason', 'closed');

        try {
            $vacancy = $this->recruitment->closeVacancy($jobVacancy, $reason);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new JobVacancyResource($vacancy);
    }
}
