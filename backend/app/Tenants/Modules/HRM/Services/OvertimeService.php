<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\OvertimeRequest;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;

class OvertimeService
{
    /**
     * Weekend multiplier per spec §3.B (1.5x weekday baseline, 2.0x weekend,
     * 3.0x holiday — holiday calendar is deferred, so callers can override
     * via the explicit `rate_multiplier` field when needed).
     */
    public const WEEKEND_MULTIPLIER = 2.0;
    public const HOLIDAY_MULTIPLIER = 3.0;
    public const DEFAULT_MULTIPLIER = 1.5;

    public function buildIndexQuery(array $filters = []): Builder
    {
        $query = OvertimeRequest::query()->with(['employee']);

        if (!empty($filters['employeeId'])) {
            $query->where('employee_id', $filters['employeeId']);
        }
        if (!empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (!empty($filters['from'])) {
            $query->where('date', '>=', $filters['from']);
        }
        if (!empty($filters['to'])) {
            $query->where('date', '<=', $filters['to']);
        }

        return $query->orderByDesc('date');
    }

    public function submit(array $data): OvertimeRequest
    {
        $date = CarbonImmutable::parse($data['date']);

        if (!isset($data['rate_multiplier'])) {
            // Auto-promote weekend dates to the 2.0x baseline. Holiday-bump
            // ships with the holiday calendar feature (future slice).
            $data['rate_multiplier'] = $date->isWeekend()
                ? self::WEEKEND_MULTIPLIER
                : self::DEFAULT_MULTIPLIER;
        }

        $data['status'] = OvertimeRequest::STATUS_PENDING;

        return OvertimeRequest::create($data);
    }

    /**
     * Sum approved overtime equivalents over a date range — consumed by
     * PayrollService::computeFor() to add overtime earnings to the gross.
     * Returns weighted hours (hours × multiplier) so callers only need to
     * multiply by the hourly rate.
     */
    public function approvedWeightedHoursFor(string $employeeId, string $from, string $to): float
    {
        $rows = OvertimeRequest::query()
            ->where('employee_id', $employeeId)
            ->where('status', OvertimeRequest::STATUS_APPROVED)
            ->whereBetween('date', [$from, $to])
            ->get(['hours', 'rate_multiplier']);

        return (float) $rows->sum(fn (OvertimeRequest $r) => (float) $r->hours * (float) $r->rate_multiplier);
    }

    public function approve(OvertimeRequest $request): OvertimeRequest
    {
        $this->assertStatus($request, OvertimeRequest::STATUS_PENDING);

        $request->update([
            'status' => OvertimeRequest::STATUS_APPROVED,
            'processed_by' => Auth::id(),
            'processed_at' => now(),
        ]);

        return $request->fresh();
    }

    public function reject(OvertimeRequest $request, ?string $reason = null): OvertimeRequest
    {
        $this->assertStatus($request, OvertimeRequest::STATUS_PENDING);

        $update = [
            'status' => OvertimeRequest::STATUS_REJECTED,
            'processed_by' => Auth::id(),
            'processed_at' => now(),
        ];
        if ($reason !== null && $reason !== '') {
            // Append the rejection reason after the requester's original
            // reason rather than overwriting it — preserves intent.
            $update['reason'] = trim(($request->reason ?? '') . "\n\nRejected: {$reason}");
        }

        $request->update($update);

        return $request->fresh();
    }

    public function cancel(OvertimeRequest $request): OvertimeRequest
    {
        $this->assertStatus($request, OvertimeRequest::STATUS_PENDING);

        $request->update([
            'status' => OvertimeRequest::STATUS_CANCELLED,
            'processed_at' => now(),
            'processed_by' => Auth::id(),
        ]);

        return $request->fresh();
    }

    private function assertStatus(OvertimeRequest $request, string $expected): void
    {
        if ($request->status !== $expected) {
            throw new DomainException("Only {$expected} overtime requests can be transitioned. Current status: {$request->status}.");
        }
    }
}
