<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\JobVacancy;
use App\Tenants\Modules\HRM\Requests\StoreApplicationRequest;
use App\Tenants\Modules\HRM\Resources\ApplicationResource;
use App\Tenants\Modules\HRM\Resources\JobVacancyResource;
use App\Tenants\Modules\HRM\Services\RecruitmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Candidate-facing surface. Lives OUTSIDE auth:api — anyone with a valid
 * tenant handle can browse open vacancies and submit an application. The
 * tenant context still resolves via X-Tenant-Handle (InitializeTenancyByHandle
 * middleware applies to the whole api/v1 group), so each tenant's careers
 * page reads only its own vacancies.
 */
class PublicCareersController extends Controller
{
    use Paginates;

    public function __construct(private readonly RecruitmentService $recruitment)
    {
    }

    /**
     * Public listing: only vacancies with status=open. Other filters
     * (departmentId, employmentType, search) pass through.
     */
    public function listVacancies(Request $request): JsonResponse
    {
        $filters = array_merge(
            $request->only(['departmentId', 'employmentType', 'search']),
            ['status' => 'open'],
        );

        $paginator = $this->paginateQuery(
            $this->recruitment->buildVacancyQuery($filters),
            $request
        );

        return $this->paginatedResponse(JobVacancyResource::class, $paginator, $request);
    }

    /**
     * Public detail view. 404s anything not in `open` so candidates can't
     * peek at drafts or closed roles via direct ID lookup.
     */
    public function showVacancy(JobVacancy $jobVacancy): JobVacancyResource
    {
        if ($jobVacancy->status !== 'open') {
            throw new NotFoundHttpException();
        }

        return new JobVacancyResource(
            $jobVacancy->load(['department', 'position'])->loadCount('applications')
        );
    }

    /**
     * Public application submission. Reuses StoreApplicationRequest validation
     * (which already authorize()s as `return true`). Always targets an open
     * vacancy — closed/draft IDs surface as 422.
     */
    public function submitApplication(StoreApplicationRequest $request): ApplicationResource|JsonResponse
    {
        $data = $request->validated();

        $vacancy = JobVacancy::find($data['job_vacancy_id']);
        if (!$vacancy || $vacancy->status !== 'open') {
            return response()->json(
                ['message' => 'This vacancy is no longer accepting applications.'],
                422
            );
        }

        $application = $this->recruitment->submitApplication($data);

        return new ApplicationResource($application->load(['vacancy', 'referrer']));
    }
}
