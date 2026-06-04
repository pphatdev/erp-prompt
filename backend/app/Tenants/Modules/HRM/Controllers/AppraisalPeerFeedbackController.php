<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\Appraisal;
use App\Models\Tenant\AppraisalPeerFeedback;
use App\Models\Tenant\Employee;
use App\Tenants\Modules\HRM\Resources\AppraisalPeerFeedbackResource;
use App\Tenants\Modules\HRM\Services\PerformanceService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * 360-degree peer feedback REST surface.
 *
 *   GET    /appraisals/{appraisal}/peer-feedback                List rows + aggregate.
 *   POST   /appraisals/{appraisal}/peer-feedback/invite          Invite a peer (admin / line manager).
 *   POST   /appraisals/{appraisal}/peer-feedback/submit          Submit own feedback (reviewer).
 *
 * Authorization split:
 *   - List + Invite require `hrm.performance.peer_review` or the broader
 *     `hrm.performance.write`.
 *   - Submit is gated on the caller being the assigned reviewer (or holding
 *     `hrm.performance.write` for admin overrides).
 */
class AppraisalPeerFeedbackController extends Controller
{
    public function __construct(private readonly PerformanceService $performance)
    {
    }

    public function index(Request $request, Appraisal $appraisal): JsonResponse
    {
        $this->authorize('view', $appraisal);

        $appraisal->load(['peerFeedbacks.reviewer']);

        return response()->json([
            'data'      => AppraisalPeerFeedbackResource::collection($appraisal->peerFeedbacks)->toArray($request),
            'aggregate' => $this->performance->aggregatePeerFeedback($appraisal),
        ]);
    }

    public function invite(Request $request, Appraisal $appraisal): AppraisalPeerFeedbackResource|JsonResponse
    {
        $this->authorize('inviteReviewer', $appraisal);

        $validated = $request->validate([
            'reviewerId' => 'required|uuid|exists:employees,id',
        ]);

        $reviewer = Employee::findOrFail($validated['reviewerId']);

        try {
            $row = $this->performance->invitePeerReviewer($appraisal, $reviewer);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new AppraisalPeerFeedbackResource($row->load('reviewer'));
    }

    public function submit(Request $request, Appraisal $appraisal): AppraisalPeerFeedbackResource|JsonResponse
    {
        $validated = $request->validate([
            'rating'    => 'nullable|numeric|between:0,5',
            'strengths' => 'nullable|string|max:4000',
            'concerns'  => 'nullable|string|max:4000',
            'notes'     => 'nullable|string|max:4000',
            // Admin override - submit on behalf of a reviewer (gated below).
            'reviewerId' => 'sometimes|uuid|exists:employees,id',
        ]);

        // Resolve the reviewer: explicit body field (admin override) takes
        // priority; otherwise default to the caller's linked employee.
        $callerEmployeeId = $request->user()?->employee?->id;
        $targetReviewerId = $validated['reviewerId'] ?? $callerEmployeeId;

        if (!$targetReviewerId) {
            return response()->json([
                'message' => 'Cannot resolve a reviewer. Either supply reviewerId (admin) or link your user to an employee.',
            ], 422);
        }

        $reviewer = Employee::findOrFail($targetReviewerId);

        // Self-submit: caller IS the reviewer -> always allowed.
        // Admin override: requires hrm.performance.write.
        $callerIsReviewer = $callerEmployeeId === $reviewer->id;
        if (!$callerIsReviewer && !$request->user()?->hasPermission('hrm.performance.write')) {
            return response()->json([
                'message' => 'You do not have permission to submit on behalf of another reviewer.',
            ], 403);
        }

        try {
            $row = $this->performance->submitPeerFeedback($appraisal, $reviewer, [
                'rating'    => $validated['rating'] ?? null,
                'strengths' => $validated['strengths'] ?? null,
                'concerns'  => $validated['concerns'] ?? null,
                'notes'     => $validated['notes'] ?? null,
            ]);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new AppraisalPeerFeedbackResource($row->load('reviewer'));
    }
}
