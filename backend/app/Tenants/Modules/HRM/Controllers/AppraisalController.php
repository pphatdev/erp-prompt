<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Appraisal;
use App\Tenants\Modules\HRM\Requests\StoreAppraisalRequest;
use App\Tenants\Modules\HRM\Requests\UpdateAppraisalRequest;
use App\Tenants\Modules\HRM\Resources\AppraisalResource;
use App\Tenants\Modules\HRM\Services\PerformanceService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppraisalController extends Controller
{
    use Paginates;

    public function __construct(private readonly PerformanceService $performance)
    {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Appraisal::class);

        $filters = $request->only(['employeeId', 'reviewerId', 'cycle', 'status']);
        $paginator = $this->paginateQuery($this->performance->buildIndexQuery($filters), $request);

        return $this->paginatedResponse(AppraisalResource::class, $paginator, $request);
    }

    public function store(StoreAppraisalRequest $request): AppraisalResource
    {
        $this->authorize('create', Appraisal::class);

        $appraisal = $this->performance->createAppraisal($request->validated());

        return new AppraisalResource($appraisal->load(['employee', 'reviewer']));
    }

    public function show(Appraisal $appraisal): AppraisalResource
    {
        $this->authorize('view', $appraisal);

        return new AppraisalResource($appraisal->load(['employee', 'reviewer']));
    }

    public function update(UpdateAppraisalRequest $request, Appraisal $appraisal): AppraisalResource|JsonResponse
    {
        $this->authorize('update', $appraisal);

        try {
            $next = $this->performance->updateAppraisal($appraisal, $request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new AppraisalResource($next);
    }

    public function submit(Appraisal $appraisal): AppraisalResource|JsonResponse
    {
        $this->authorize('submit', $appraisal);

        try {
            return new AppraisalResource($this->performance->submit($appraisal));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function review(UpdateAppraisalRequest $request, Appraisal $appraisal): AppraisalResource|JsonResponse
    {
        $this->authorize('review', $appraisal);

        try {
            return new AppraisalResource($this->performance->review($appraisal, $request->validated()));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function close(Appraisal $appraisal): AppraisalResource|JsonResponse
    {
        $this->authorize('close', $appraisal);

        try {
            return new AppraisalResource($this->performance->close($appraisal));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function destroy(Appraisal $appraisal): JsonResponse
    {
        $this->authorize('delete', $appraisal);

        if ($appraisal->status === 'closed') {
            return response()->json(['message' => 'Closed appraisals cannot be deleted.'], 422);
        }

        $appraisal->delete();

        return response()->json(['message' => 'Appraisal archived.'], 200);
    }
}
