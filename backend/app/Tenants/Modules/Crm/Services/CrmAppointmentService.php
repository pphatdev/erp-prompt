<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Services;

use App\Models\Tenant\CrmAppointment;
use App\Models\Tenant\Lead;
use App\Models\Tenant\Opportunity;
use Carbon\CarbonInterface;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;

class CrmAppointmentService
{
    public function buildQuery(): Builder
    {
        return CrmAppointment::query()
            ->with(['opportunity', 'lead', 'actor'])
            ->orderBy('starts_at');
    }

    public function listInWindow(CarbonInterface $start, CarbonInterface $end): Collection
    {
        return $this->buildQuery()
            ->where('starts_at', '<', $end)
            ->where('ends_at', '>', $start)
            ->get();
    }

    public function schedule(array $data): CrmAppointment
    {
        $this->assertTimeRange($data['starts_at'], $data['ends_at']);

        if (!empty($data['opportunity_id'])) {
            // BelongsToTenant scope enforces tenant isolation on the lookup.
            Opportunity::findOrFail($data['opportunity_id']);
        }
        if (!empty($data['lead_id'])) {
            Lead::findOrFail($data['lead_id']);
        }

        return CrmAppointment::create([
            'subject'        => $data['subject'],
            'starts_at'      => $data['starts_at'],
            'ends_at'        => $data['ends_at'],
            'location'       => $data['location'] ?? null,
            'attendees'      => $data['attendees'] ?? [],
            'notes'          => $data['notes'] ?? null,
            'opportunity_id' => $data['opportunity_id'] ?? null,
            'lead_id'        => $data['lead_id'] ?? null,
            'actor_id'       => $data['actor_id'] ?? Auth::id(),
            'status'         => CrmAppointment::STATUS_SCHEDULED,
        ])->load(['opportunity', 'lead', 'actor']);
    }

    public function reschedule(CrmAppointment $appt, array $data): CrmAppointment
    {
        $this->assertNotTerminal($appt);
        $this->assertTimeRange($data['starts_at'] ?? $appt->starts_at, $data['ends_at'] ?? $appt->ends_at);

        $appt->update(array_filter([
            'subject'   => $data['subject']   ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at'   => $data['ends_at']   ?? null,
            'location'  => array_key_exists('location', $data) ? $data['location'] : null,
            'attendees' => array_key_exists('attendees', $data) ? $data['attendees'] : null,
            'notes'     => array_key_exists('notes', $data) ? $data['notes'] : null,
        ], fn ($v) => $v !== null));

        return $appt->fresh(['opportunity', 'lead', 'actor']);
    }

    public function complete(CrmAppointment $appt): CrmAppointment
    {
        $this->assertNotTerminal($appt);
        $appt->update([
            'status'       => CrmAppointment::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
        return $appt->fresh(['opportunity', 'lead', 'actor']);
    }

    public function cancel(CrmAppointment $appt, ?string $reason = null): CrmAppointment
    {
        if ($appt->status === CrmAppointment::STATUS_CANCELLED) {
            return $appt;
        }
        $this->assertNotTerminal($appt);
        $appt->update([
            'status'        => CrmAppointment::STATUS_CANCELLED,
            'cancel_reason' => $reason,
            'cancelled_at'  => now(),
        ]);
        return $appt->fresh(['opportunity', 'lead', 'actor']);
    }

    public function markNoShow(CrmAppointment $appt): CrmAppointment
    {
        $this->assertNotTerminal($appt);
        $appt->update(['status' => CrmAppointment::STATUS_NO_SHOW]);
        return $appt->fresh(['opportunity', 'lead', 'actor']);
    }

    private function assertNotTerminal(CrmAppointment $appt): void
    {
        if ($appt->isTerminal()) {
            throw new DomainException("Appointment is already {$appt->status}.");
        }
    }

    private function assertTimeRange($starts, $ends): void
    {
        $s = \Carbon\Carbon::parse($starts);
        $e = \Carbon\Carbon::parse($ends);
        if ($e->lessThanOrEqualTo($s)) {
            throw new DomainException('ends_at must be after starts_at.');
        }
    }
}
