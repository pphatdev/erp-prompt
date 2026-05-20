<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Approvals\Events;

use App\Models\Tenant\ApprovalRequest;
use Illuminate\Foundation\Events\Dispatchable;

class ApprovalRequestFinalized
{
    use Dispatchable;

    public function __construct(
        public readonly ApprovalRequest $request,
        public readonly string $finalStatus,
    ) {
    }
}
