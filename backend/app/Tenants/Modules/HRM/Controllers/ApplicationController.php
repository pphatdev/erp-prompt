<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Application;
use App\Tenants\Modules\HRM\Requests\StoreApplicationRequest;
use App\Tenants\Modules\HRM\Requests\TransitionApplicationRequest;
use App\Tenants\Modules\HRM\Requests\UpdateApplicationRequest;
use App\Tenants\Modules\HRM\Resources\ApplicationResource;
use App\Tenants\Modules\HRM\Resources\EmployeeResource;
use App\Tenants\Modules\HRM\Services\RecruitmentService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ApplicationController extends Controller
{
    use Paginates;

    public function __construct(private readonly RecruitmentService $recruitment)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Application::class);

        $filters = $request->only(['jobVacancyId', 'status', 'search']);
        $paginator = $this->paginateQuery($this->recruitment->buildApplicationQuery($filters), $request);

        return $this->paginatedResponse(ApplicationResource::class, $paginator, $request);
    }

    public function store(StoreApplicationRequest $request): ApplicationResource
    {
        $this->authorize('create', Application::class);

        $application = $this->recruitment->submitApplication($request->validated());

        return new ApplicationResource($application->load(['vacancy', 'referrer']));
    }

    /**
     * Upload a candidate resume ahead of submitting the full application.
     * Returns the storage path that the client should send back as
     * `resume_path` in the subsequent POST /applications call.
     */
    public function storeResume(Request $request): JsonResponse
    {
        $this->authorize('create', Application::class);

        $request->validate([
            'file' => 'required|file|extensions:pdf,doc,docx|max:10240',
        ], [
            'file.extensions' => 'Resume must be a PDF, DOC, or DOCX file.',
        ]);

        try {
            $payload = $this->recruitment->storeResume($request->file('file'));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json($payload, 201);
    }

    public function show(Application $application): ApplicationResource
    {
        $this->authorize('view', $application);

        $relations = ['vacancy', 'referrer'];
        if (\Illuminate\Support\Facades\Schema::hasTable('employee_appointments')) {
            $relations[] = 'pendingAppointments';
        }

        return new ApplicationResource($application->load($relations));
    }

    public function update(UpdateApplicationRequest $request, Application $application): ApplicationResource
    {
        $this->authorize('update', $application);

        $updated = $this->recruitment->updateApplication($application, $request->validated());

        return new ApplicationResource($updated);
    }

    public function transition(TransitionApplicationRequest $request, Application $application): ApplicationResource|JsonResponse
    {
        $this->authorize('transition', $application);

        try {
            $next = $this->recruitment->transitionApplication($application, $request->validated()['status']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ApplicationResource($next);
    }

    /**
     * Convert a hired application into an Employee record. Idempotent — if
     * the application is already linked to an employee, returns that one
     * rather than creating a duplicate.
     *
     * Response shape: `{ data: <employee>, created: bool, linkedExisting: bool }`.
     * The `linkedExisting` flag is true when an `Employee` already existed
     * under the same email and we linked to it instead of creating a new row
     * — surfacing that explicitly so the UI can toast accurately and the
     * user doesn't think a new employee silently disappeared.
     */
    public function convertToEmployee(Application $application): JsonResponse
    {
        $this->authorize('convert', $application);

        try {
            $result = $this->recruitment->convertToEmployee($application);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $resource = new EmployeeResource($result['employee']->load(['department', 'position']));

        return $resource
            ->additional([
                'created'        => $result['created'],
                'linkedExisting' => $result['linkedExisting'],
            ])
            ->response();
    }

    /**
     * Undo a recent hire→employee conversion. Soft-deletes the linked
     * Employee and nulls the application's link fields. Refuses if the
     * conversion is older than the 7-day window (enforced by the service).
     */
    public function revertEmployeeConversion(Application $application): ApplicationResource|JsonResponse
    {
        $this->authorize('revertConversion', $application);

        try {
            $reverted = $this->recruitment->revertEmployeeConversion($application);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ApplicationResource($reverted);
    }

    /**
     * Bulk-convert hired applications to employees. Each id is gated by the
     * same `convert` policy as the single-row variant. Returns a structured
     * outcome so the UI can show partial completion.
     */
    public function bulkConvertToEmployees(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1|max:200',
            'ids.*' => 'required|uuid|exists:applications,id',
        ]);

        $apps = Application::whereIn('id', $data['ids'])->get();
        foreach ($apps as $app) {
            $this->authorize('convert', $app);
        }

        $result = $this->recruitment->bulkConvertToEmployees($data['ids']);

        return response()->json($result, 200);
    }

    public function destroy(Application $application): JsonResponse
    {
        $this->authorize('delete', $application);

        $application->delete();

        return response()->json(['message' => 'Application withdrawn.'], 200);
    }

    /**
     * Bulk-withdraw applications. Each id is gated by the same policy as
     * single destroy. Rows in non-withdrawable statuses are reported back
     * to the client rather than silently dropped.
     */
    public function bulkDestroy(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids'   => 'required|array|min:1|max:200',
            'ids.*' => 'required|uuid|exists:applications,id',
        ]);

        $apps = Application::whereIn('id', $data['ids'])->get();
        foreach ($apps as $app) {
            $this->authorize('delete', $app);
        }

        $result = $this->recruitment->bulkDeleteApplications($data['ids']);

        return response()->json($result, 200);
    }
}
