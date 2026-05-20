<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\Interview;
use App\Tenants\Modules\HRM\Requests\StoreInterviewRequest;
use App\Tenants\Modules\HRM\Requests\SubmitInterviewFeedbackRequest;
use App\Tenants\Modules\HRM\Requests\UpdateInterviewRequest;
use App\Tenants\Modules\HRM\Resources\InterviewFeedbackResource;
use App\Tenants\Modules\HRM\Resources\InterviewResource;
use App\Tenants\Modules\HRM\Services\CalendarSyncService;
use App\Tenants\Modules\HRM\Services\InterviewService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InterviewController extends Controller
{
    use Paginates;

    public function __construct(
        private readonly InterviewService $interviews,
        private readonly CalendarSyncService $calendar,
    ) {
    }

    public function index(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Interview::class);

        $filters = $request->only(['applicationId', 'status', 'from', 'to']);
        $paginator = $this->paginateQuery($this->interviews->buildIndexQuery($filters), $request);

        return $this->paginatedResponse(InterviewResource::class, $paginator, $request);
    }

    public function store(StoreInterviewRequest $request): InterviewResource
    {
        $this->authorize('create', Interview::class);

        $data = $request->validated();
        $interviewers = $data['interviewer_ids'] ?? [];
        unset($data['interviewer_ids']);

        $interview = $this->interviews->schedule($data, $interviewers);

        return new InterviewResource($interview);
    }

    public function show(Interview $interview): InterviewResource
    {
        $this->authorize('view', $interview);

        return new InterviewResource(
            $interview->load(['application', 'interviewers', 'feedback'])
        );
    }

    public function update(UpdateInterviewRequest $request, Interview $interview): InterviewResource|JsonResponse
    {
        $this->authorize('update', $interview);

        try {
            $interview = $this->interviews->reschedule($interview, $request->validated());
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new InterviewResource($interview);
    }

    public function destroy(Interview $interview): JsonResponse
    {
        $this->authorize('delete', $interview);

        $interview->delete();
        return response()->json(['message' => 'Interview archived.'], 200);
    }

    public function cancel(Request $request, Interview $interview): InterviewResource|JsonResponse
    {
        $this->authorize('cancel', $interview);

        try {
            $interview = $this->interviews->cancel($interview, $request->input('reason'));
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new InterviewResource($interview);
    }

    public function complete(Interview $interview): InterviewResource|JsonResponse
    {
        $this->authorize('complete', $interview);

        try {
            $interview = $this->interviews->complete($interview);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new InterviewResource($interview);
    }

    public function submitFeedback(SubmitInterviewFeedbackRequest $request, Interview $interview): InterviewFeedbackResource|JsonResponse
    {
        $this->authorize('submitFeedback', $interview);

        $data = $request->validated();
        $interviewerId = $data['interviewer_id'];
        unset($data['interviewer_id']);

        try {
            $feedback = $this->interviews->submitFeedback($interview, $interviewerId, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new InterviewFeedbackResource($feedback);
    }

    public function scorecard(Interview $interview): JsonResponse
    {
        $this->authorize('view', $interview);

        return response()->json(['data' => $this->interviews->scorecardFor($interview)]);
    }

    /**
     * Stream the interview's RFC 5545 ICS payload for download. Calendar
     * clients (Outlook, Google, Apple) parse this directly.
     */
    public function downloadInvite(Interview $interview): Response
    {
        $this->authorize('view', $interview);

        $payload = $this->calendar->buildInvite($interview);

        return new Response(
            $payload,
            200,
            [
                'Content-Type'        => 'text/calendar; charset=utf-8',
                'Content-Disposition' => 'attachment; filename="interview-' . $interview->id . '.ics"',
            ]
        );
    }
}
