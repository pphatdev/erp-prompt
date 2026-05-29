<?php

namespace App\Tenants\Modules\Approvals\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Tenants\Modules\IAM\Resources\UserResource;

class ApprovalRequestResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'requester' => new UserResource($this->whenLoaded('requester')),
            'current_level_id' => $this->current_level_id,
            'requestable_type' => $this->requestable_type,
            'requestable_id' => $this->requestable_id,
            'status' => $this->status,
            // Map history items so each approver runs through UserResource and
            // picks up the employee sub-object (the timeline renders the code).
            'history' => $this->whenLoaded('history', fn () => $this->history->map(fn ($h) => [
                'id' => $h->id,
                'action' => $h->action,
                'comment' => $h->comment,
                'created_at' => $h->created_at?->toIso8601String(),
                'approver' => $h->approver ? new UserResource($h->approver) : null,
            ])),
            'workflow' => $this->whenLoaded('workflow'),
            'requestable' => $this->whenLoaded('requestable', function () {
                if ($this->requestable instanceof \App\Models\Tenant\Leave) {
                    return new \App\Tenants\Modules\HRM\Resources\LeaveResource($this->requestable);
                }
                if ($this->requestable instanceof \App\Models\Tenant\PurchaseOrder) {
                    return new \App\Tenants\Modules\Inventory\Resources\PurchaseOrderResource($this->requestable);
                }
                if ($this->requestable instanceof \App\Models\Tenant\EmployeeAppointment) {
                    return new \App\Tenants\Modules\HRM\Resources\EmployeeAppointmentResource($this->requestable);
                }
                if ($this->requestable instanceof \App\Models\Tenant\Appraisal) {
                    return new \App\Tenants\Modules\HRM\Resources\AppraisalResource($this->requestable);
                }
                return $this->requestable;
            }),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
