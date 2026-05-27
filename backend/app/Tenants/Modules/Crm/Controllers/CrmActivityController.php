<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\CrmActivity;
use App\Tenants\Modules\Crm\Resources\CrmActivityResource;
use App\Tenants\Modules\Crm\Services\ActivityService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CrmActivityController extends Controller
{
    use Paginates;

    public function __construct(private readonly ActivityService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', CrmActivity::class);
        $query = $this->service->buildQuery();

        if ($trackableType = $request->query('trackable_type')) {
            $query->where('trackable_type', $trackableType);
        }
        if ($trackableId = $request->query('trackable_id')) {
            $query->where('trackable_id', $trackableId);
        }
        if ($type = $request->query('activity_type')) {
            $query->where('activity_type', $type);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }

        return $this->paginatedResponse(CrmActivityResource::class, $this->paginateQuery($query, $request), $request);
    }

    /**
     * @description Log a new polymorphic activity, enforcing cross-tenant boundaries
     * @method POST
     * @param { Request } request HTTP Request containing activity parameters
     * @returns { CrmActivityResource } The newly created activity details
     */
    public function store(Request $request): CrmActivityResource
    {
        Gate::authorize('create', CrmActivity::class);
        $data = $request->validate([
            'trackable_type' => 'required|string',
            'trackable_id'   => 'required|uuid',
            'activity_type'  => ['required', Rule::in(CrmActivity::TYPES)],
            'subject'        => 'required|string|max:255',
            'description'    => 'nullable|string',
            'due_date'       => 'nullable|date',
            'status'         => ['sometimes', Rule::in(CrmActivity::STATUSES)],
            'actor_id'       => 'nullable|uuid|exists:users,id',
        ]);

        try {
            $activity = $this->service->logActivity($data);
        } catch (\DomainException $e) {
            abort(422, $e->getMessage());
        }

        return new CrmActivityResource($activity);
    }

    public function show(CrmActivity $crmActivity): CrmActivityResource
    {
        Gate::authorize('view', $crmActivity);
        return new CrmActivityResource($crmActivity->load('actor'));
    }

    public function update(Request $request, CrmActivity $crmActivity): CrmActivityResource
    {
        Gate::authorize('update', $crmActivity);
        $data = $request->validate([
            'subject'     => 'sometimes|string|max:255',
            'description' => 'sometimes|nullable|string',
            'due_date'    => 'sometimes|nullable|date',
            'status'      => ['sometimes', Rule::in(CrmActivity::STATUSES)],
        ]);

        $crmActivity->update($data);
        return new CrmActivityResource($crmActivity->fresh('actor'));
    }

    public function destroy(CrmActivity $crmActivity): JsonResponse
    {
        Gate::authorize('delete', $crmActivity);
        $crmActivity->delete();
        return response()->json(['message' => 'Activity deleted.']);
    }

    public function complete(CrmActivity $crmActivity): CrmActivityResource
    {
        Gate::authorize('update', $crmActivity);
        try {
            $activity = $this->service->completeActivity($crmActivity);
        } catch (\DomainException $e) {
            abort(422, $e->getMessage());
        }

        return new CrmActivityResource($activity);
    }
}
