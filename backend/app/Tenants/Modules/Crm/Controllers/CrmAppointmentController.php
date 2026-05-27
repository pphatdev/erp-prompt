<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\CrmAppointment;
use App\Tenants\Modules\Crm\Resources\CrmAppointmentResource;
use App\Tenants\Modules\Crm\Services\CrmAppointmentService;
use Carbon\Carbon;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class CrmAppointmentController extends Controller
{
    use Paginates;

    public function __construct(private readonly CrmAppointmentService $service) {}

    /**
     * GET /crm-appointments
     *   ?from=YYYY-MM-DD&to=YYYY-MM-DD  → calendar window (returns all in
     *                                     range, not paginated)
     *   ?status=scheduled               → filter
     *   otherwise                       → paginated list
     */
    public function index(Request $request): AnonymousResourceCollection|JsonResponse
    {
        Gate::authorize('viewAny', CrmAppointment::class);

        if ($request->filled('from') && $request->filled('to')) {
            $start = Carbon::parse($request->query('from'))->startOfDay();
            $end   = Carbon::parse($request->query('to'))->endOfDay();
            return CrmAppointmentResource::collection(
                $this->service->listInWindow($start, $end)
            );
        }

        $query = $this->service->buildQuery();
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($opportunityId = $request->query('opportunity_id')) {
            $query->where('opportunity_id', $opportunityId);
        }
        if ($leadId = $request->query('lead_id')) {
            $query->where('lead_id', $leadId);
        }

        return $this->paginatedResponse(CrmAppointmentResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): CrmAppointmentResource|JsonResponse
    {
        Gate::authorize('create', CrmAppointment::class);

        $data = $request->validate([
            'subject'        => 'required|string|max:255',
            'starts_at'      => 'required|date',
            'ends_at'        => 'required|date',
            'location'       => 'sometimes|nullable|string|max:255',
            'attendees'      => 'sometimes|array',
            'attendees.*.name'  => 'required_with:attendees|string|max:120',
            'attendees.*.email' => 'sometimes|nullable|email|max:255',
            'attendees.*.role'  => 'sometimes|nullable|string|max:60',
            'notes'          => 'sometimes|nullable|string|max:2000',
            'opportunity_id' => 'sometimes|nullable|uuid|exists:opportunities,id',
            'lead_id'        => 'sometimes|nullable|uuid|exists:leads,id',
            'actor_id'       => 'sometimes|nullable|uuid|exists:users,id',
        ]);

        try {
            $appt = $this->service->schedule($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new CrmAppointmentResource($appt);
    }

    public function show(CrmAppointment $crmAppointment): CrmAppointmentResource
    {
        Gate::authorize('view', $crmAppointment);
        return new CrmAppointmentResource($crmAppointment->load(['opportunity', 'lead', 'actor']));
    }

    public function update(Request $request, CrmAppointment $crmAppointment): CrmAppointmentResource|JsonResponse
    {
        Gate::authorize('update', $crmAppointment);

        $data = $request->validate([
            'subject'   => 'sometimes|string|max:255',
            'starts_at' => 'sometimes|date',
            'ends_at'   => 'sometimes|date',
            'location'  => 'sometimes|nullable|string|max:255',
            'attendees' => 'sometimes|array',
            'notes'     => 'sometimes|nullable|string|max:2000',
        ]);

        try {
            $appt = $this->service->reschedule($crmAppointment, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new CrmAppointmentResource($appt);
    }

    public function destroy(CrmAppointment $crmAppointment): JsonResponse
    {
        Gate::authorize('delete', $crmAppointment);
        $crmAppointment->delete();
        return response()->json(['message' => 'Appointment removed.']);
    }

    public function complete(CrmAppointment $crmAppointment): CrmAppointmentResource|JsonResponse
    {
        Gate::authorize('update', $crmAppointment);
        try {
            $appt = $this->service->complete($crmAppointment);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new CrmAppointmentResource($appt);
    }

    public function cancel(Request $request, CrmAppointment $crmAppointment): CrmAppointmentResource|JsonResponse
    {
        Gate::authorize('update', $crmAppointment);
        $data = $request->validate(['reason' => 'sometimes|nullable|string|max:500']);
        try {
            $appt = $this->service->cancel($crmAppointment, $data['reason'] ?? null);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new CrmAppointmentResource($appt);
    }

    public function markNoShow(CrmAppointment $crmAppointment): CrmAppointmentResource|JsonResponse
    {
        Gate::authorize('update', $crmAppointment);
        try {
            $appt = $this->service->markNoShow($crmAppointment);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new CrmAppointmentResource($appt);
    }
}
