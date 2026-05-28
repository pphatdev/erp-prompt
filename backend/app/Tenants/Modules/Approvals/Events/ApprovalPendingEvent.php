<?php

namespace App\Tenants\Modules\Approvals\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;
use App\Models\Tenant\ApprovalRequest;

class ApprovalPendingEvent
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ApprovalRequest $request;

    public function __construct(ApprovalRequest $request)
    {
        $this->request = $request;
    }
}
