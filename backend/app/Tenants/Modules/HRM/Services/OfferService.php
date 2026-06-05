<?php

declare(strict_types=1);

namespace App\Tenants\Modules\HRM\Services;

use App\Models\Tenant\Application;
use App\Models\Tenant\Offer;
use App\Support\GenerationRetry;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use App\Tenants\Modules\Settings\Services\SettingService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Lifecycle owner for {@see Offer}.
 *
 * Generates the OFR-YYYYMM-NNN reference (per-month bucket, settings-driven
 * prefix) and validates state transitions through `WorkflowStatusService`.
 * On acceptance the service flips the offer status and advances the parent
 * Application from `offer` → `hired`; Employee creation + onboarding seeding
 * is deferred to {@see SyncEmployeeAppointmentFromApproval} so HR's
 * appointment-request approval stays the single conversion gate (Phase 8.5).
 */
class OfferService
{
    public const REFERENCE_PREFIX_DEFAULT = 'OFR-';

    public function __construct(
        private readonly WorkflowStatusService $statuses,
        private readonly SettingService $settings,
        private readonly ESignatureService $esign,
    ) {
    }

    public function createOffer(Application $application, array $data): Offer
    {
        // Offers belong to the `offer` funnel stage. Acceptance is what
        // promotes the application to `hired`; HR governance (eApprovals)
        // is what promotes `hired` to `onboarding`. See
        // skills/hrm/rules.md §13 for the canonical flow.
        if ($application->status !== 'offer') {
            throw new DomainException('Offers can only be drafted while the application is at the Job Offer stage.');
        }

        return GenerationRetry::handle(function () use ($application, $data) {
            $data['application_id']   = $application->id;
            $data['reference_number'] = $this->generateReferenceNumber();
            $data['status']           = Offer::STATUS_DRAFT;
            // Default probation_months from tenant setting when caller didn't
            // override. Phase 9 makes this settings-driven so HR can tune the
            // global default once without editing every offer template.
            if (!isset($data['probation_months'])) {
                $raw = $this->settings->get('hrm.recruitment.probation_period_default');
                $data['probation_months'] = is_numeric($raw) ? (int) $raw : 3;
            }

            return DB::transaction(fn () => Offer::create($data));
        });
    }

    /**
     * Move an Offer from draft to sent and ship it through the eSignature
     * provider. The provider call lives outside the DB transaction so a
     * 5xx from the provider cannot orphan us mid-state — we update the row
     * after the provider returns an envelope id.
     */
    public function sendOffer(Offer $offer, array $providerOptions = []): Offer
    {
        $this->statuses->validateTransition('hrm.offer', $offer->status, Offer::STATUS_SENT);

        if ($offer->isTerminal()) {
            throw new DomainException('Cannot resend an offer in a terminal state.');
        }

        $envelope = $this->esign->createEnvelope($offer, $providerOptions);

        $offer->update([
            'status'            => Offer::STATUS_SENT,
            'esign_provider'    => $envelope['provider'],
            'esign_envelope_id' => $envelope['envelopeId'],
            'sent_at'           => now(),
        ]);

        return $offer->fresh();
    }

    /**
     * Mark the offer accepted and advance the Application to `hired`.
     *
     * As of Phase 8.5 this method NO LONGER calls `convertToEmployee` or
     * `seedDefaultChecklist`. The Employee row + onboarding checklist are
     * materialised in {@see SyncEmployeeAppointmentFromApproval} when HR's
     * Employee Appointment request is approved through eApprovals. This
     * keeps the HR governance gate in front of payroll provisioning.
     *
     * Called from {@see ESignatureService::handleWebhookPayload()} when the
     * provider tells us the candidate signed, AND from the admin manual-mark
     * endpoint for mock/manual workflows. Idempotent — a duplicate webhook
     * returns the existing accepted offer without re-running the application
     * transition.
     */
    public function markAccepted(Offer $offer, ?array $providerPayload = null): Offer
    {
        if ($offer->status === Offer::STATUS_ACCEPTED) {
            return $offer; // idempotent — the webhook may fire twice
        }
        $this->statuses->validateTransition('hrm.offer', $offer->status, Offer::STATUS_ACCEPTED);

        return DB::transaction(function () use ($offer, $providerPayload) {
            $offer->update([
                'status'        => Offer::STATUS_ACCEPTED,
                'signed_at'     => now(),
                'esign_payload' => $providerPayload ?? $offer->esign_payload,
            ]);

            // Advance the Application from `offer` → `hired`. The Application
            // is loaded via the offer relation; if a sibling offer for the
            // same application already moved the app past `offer` (race),
            // validateTransition will throw and the DB::transaction rolls back.
            $application = $offer->application;
            if ($application && $application->status === 'offer') {
                $this->statuses->validateTransition('hrm.application', $application->status, 'hired');
                $application->update(['status' => 'hired']);
            }

            return $offer->fresh();
        });
    }

    public function markDeclined(Offer $offer, ?string $reason = null, ?array $providerPayload = null): Offer
    {
        if ($offer->status === Offer::STATUS_DECLINED) {
            return $offer;
        }
        $this->statuses->validateTransition('hrm.offer', $offer->status, Offer::STATUS_DECLINED);

        $offer->update([
            'status'         => Offer::STATUS_DECLINED,
            'declined_at'    => now(),
            'decline_reason' => $reason,
            'esign_payload'  => $providerPayload ?? $offer->esign_payload,
        ]);

        return $offer->fresh();
    }

    /**
     * Sweep open offers whose `expires_at` is in the past. Designed to be
     * called from a daily scheduler. Returns the number of rows flipped.
     */
    public function expireStaleOffers(): int
    {
        $candidates = Offer::query()
            ->whereIn('status', [Offer::STATUS_DRAFT, Offer::STATUS_SENT])
            ->whereNotNull('expires_at')
            ->whereDate('expires_at', '<', now()->toDateString())
            ->get();

        $count = 0;
        foreach ($candidates as $offer) {
            try {
                $this->statuses->validateTransition('hrm.offer', $offer->status, Offer::STATUS_EXPIRED);
            } catch (DomainException) {
                continue; // tenant removed `expired` — skip silently.
            }
            $offer->update(['status' => Offer::STATUS_EXPIRED]);
            $count++;
        }

        return $count;
    }

    /**
     * Reference format: `<prefix><YYYYMM>-<NNN>` (per-month sequence).
     * Prefix comes from `numbering.offer_reference_prefix` setting (Phase 9
     * registry — defaults to `OFR-` when missing). Collision retry sits on
     * the unique `(tenant_id, reference_number)` index.
     */
    private function generateReferenceNumber(): string
    {
        $prefix = $this->settings->get('numbering.offer_reference_prefix');
        $prefix = is_string($prefix) && $prefix !== '' ? $prefix : self::REFERENCE_PREFIX_DEFAULT;

        $month = CarbonImmutable::now()->format('Ym');
        $like  = $prefix . $month . '-%';

        $last = Offer::query()
            ->withTrashed()
            ->where('reference_number', 'like', $like)
            ->orderByDesc('reference_number')
            ->value('reference_number');

        $next = 1;
        if (is_string($last) && preg_match('/-(\d+)$/', $last, $m)) {
            $next = ((int) $m[1]) + 1;
        }

        return sprintf('%s%s-%03d', $prefix, $month, $next);
    }
}
