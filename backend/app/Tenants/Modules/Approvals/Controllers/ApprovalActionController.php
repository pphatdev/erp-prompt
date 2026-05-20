<?php

namespace App\Tenants\Modules\Approvals\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Tenant\ApprovalRequest;
use App\Tenants\Modules\Approvals\Resources\ApprovalRequestResource;
use App\Tenants\Modules\Approvals\Services\ApprovalService;
use Illuminate\Http\Request;

class ApprovalActionController extends Controller
{
    protected $approvalService;

    public function __construct(ApprovalService $approvalService)
    {
        $this->approvalService = $approvalService;
    }

    /**
     * Process an action (Approve/Reject/Send Back) on a request.
     */
    public function process(Request $request, ApprovalRequest $approvalRequest): ApprovalRequestResource
    {
        $data = $request->validate([
            'action' => 'required|in:approved,rejected,sent_back',
            'comment' => 'nullable|string',
        ]);

        $processedRequest = $this->approvalService->processAction(
            $approvalRequest,
            $request->user(),
            $data['action'],
            $data['comment'] ?? null
        );

        return new ApprovalRequestResource($processedRequest->load('requester', 'history'));
    }
}
