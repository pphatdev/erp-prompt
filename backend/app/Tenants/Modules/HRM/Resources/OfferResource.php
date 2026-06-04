<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $canSeePayroll = $request->user()?->hasPermission('hrm.payroll.read') ?? false;
        $canSeeOffers  = $request->user()?->hasPermission('hrm.recruitment.read') ?? false;

        // Compensation visibility mirrors EmployeeResource — only payroll
        // readers see raw figures. Recruiters with hrm.recruitment.read
        // still see the rest of the offer (status, dates, reference).
        $compensationVisible = $canSeePayroll;

        return [
            'id'                  => $this->id,
            'applicationId'       => $this->application_id,
            'employeeId'          => $this->employee_id,
            'referenceNumber'     => $this->reference_number,
            'title'               => $this->title,
            'effectiveDate'       => optional($this->effective_date)->toDateString(),
            'expiresAt'           => optional($this->expires_at)->toDateString(),
            'baseSalary'          => $compensationVisible ? ($this->base_salary === null ? null : (float) $this->base_salary) : null,
            'signingBonus'        => $compensationVisible ? ($this->signing_bonus === null ? null : (float) $this->signing_bonus) : null,
            'currency'            => $this->currency,
            'probationMonths'     => $this->probation_months,
            'status'              => $this->status,
            'esignProvider'       => $this->esign_provider,
            'esignEnvelopeId'     => $canSeeOffers ? $this->esign_envelope_id : null,
            'sentAt'              => optional($this->sent_at)->toIso8601String(),
            'signedAt'            => optional($this->signed_at)->toIso8601String(),
            'declinedAt'          => optional($this->declined_at)->toIso8601String(),
            'declineReason'       => $this->decline_reason,
            'notes'               => $canSeeOffers ? $this->notes : null,
            'application'         => $this->whenLoaded('application', fn () => $this->application ? new ApplicationResource($this->application) : null),
            'employee'            => $this->whenLoaded('employee', fn () => $this->employee ? new EmployeeResource($this->employee) : null),
            'onboardingChecklist' => $this->whenLoaded('onboardingChecklist', fn () => $this->onboardingChecklist ? new OnboardingChecklistResource($this->onboardingChecklist) : null),
            'createdAt'           => optional($this->created_at)->toIso8601String(),
            'updatedAt'           => optional($this->updated_at)->toIso8601String(),
        ];
    }
}
