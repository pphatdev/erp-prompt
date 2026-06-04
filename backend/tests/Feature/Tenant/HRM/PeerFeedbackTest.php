<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\HRM;

use App\Models\Tenant\Appraisal;
use App\Models\Tenant\AppraisalPeerFeedback;
use App\Models\Tenant\Employee;
use App\Models\Tenant\Role;
use App\Models\Tenant\User;
use App\Tenants\Modules\HRM\Services\PerformanceService;
use App\Tenants\Modules\Settings\Services\SettingService;
use Tests\Feature\TenantTestCase;

/**
 * Phase 4 - 360-degree peer feedback lifecycle.
 *
 * Covers:
 *   - Invite is idempotent (re-invite returns the same row).
 *   - Employees can't peer-review themselves.
 *   - Self-submit flips status to `submitted` + stamps submitted_at.
 *   - Aggregate computes average across submitted rows only.
 *   - PerformanceService::applyWeightedRating blends the peer average
 *     when `hrm.appraisal.peer_evaluation_weight` is set.
 *   - 403 when a non-reviewer tries to submit-as-other through the HTTP
 *     endpoint without admin override.
 */
class PeerFeedbackTest extends TenantTestCase
{
    private PerformanceService $service;
    private Appraisal $appraisal;
    private Employee $reviewee;
    private Employee $peerA;
    private Employee $peerB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = app(PerformanceService::class);

        $this->reviewee = Employee::create([
            'first_name' => 'Rev', 'last_name' => 'Iewee',
            'email' => 'reviewee@peer.example', 'employee_id' => 'PEER-001', 'status' => 'active',
        ]);
        $this->peerA = Employee::create([
            'first_name' => 'Peer', 'last_name' => 'Alpha',
            'email' => 'peer.a@peer.example', 'employee_id' => 'PEER-A', 'status' => 'active',
        ]);
        $this->peerB = Employee::create([
            'first_name' => 'Peer', 'last_name' => 'Beta',
            'email' => 'peer.b@peer.example', 'employee_id' => 'PEER-B', 'status' => 'active',
        ]);

        $this->appraisal = Appraisal::create([
            'employee_id'  => $this->reviewee->id,
            'reviewer_id'  => $this->peerA->id, // line manager, also can act as a peer? policy disallows self-review only
            'cycle'        => '2026-H1',
            'period_start' => '2026-01-01',
            'period_end'   => '2026-06-30',
            'status'       => 'draft',
        ]);
    }

    public function test_invite_peer_reviewer_is_idempotent(): void
    {
        $first  = $this->service->invitePeerReviewer($this->appraisal, $this->peerB);
        $second = $this->service->invitePeerReviewer($this->appraisal, $this->peerB);

        $this->assertSame($first->id, $second->id, 'Invite must be idempotent.');
        $this->assertSame(1, AppraisalPeerFeedback::query()
            ->where('appraisal_id', $this->appraisal->id)
            ->where('reviewer_id', $this->peerB->id)
            ->count());
        $this->assertSame(AppraisalPeerFeedback::STATUS_INVITED, $first->status);
    }

    public function test_employee_cannot_peer_review_themselves(): void
    {
        $this->expectException(\DomainException::class);
        $this->service->invitePeerReviewer($this->appraisal, $this->reviewee);
    }

    public function test_submit_flips_status_and_stamps_submitted_at(): void
    {
        $row = $this->service->submitPeerFeedback($this->appraisal, $this->peerB, [
            'rating'    => 4.5,
            'strengths' => 'Strong collaborator.',
            'concerns'  => null,
        ]);

        $this->assertSame(AppraisalPeerFeedback::STATUS_SUBMITTED, $row->status);
        $this->assertNotNull($row->submitted_at);
        $this->assertSame(4.5, (float) $row->rating);
    }

    public function test_aggregate_returns_average_over_submitted_only(): void
    {
        $this->service->submitPeerFeedback($this->appraisal, $this->peerB, ['rating' => 4.0]);
        // Invite (not submit) peerA — should NOT contribute to average.
        $this->service->invitePeerReviewer($this->appraisal, $this->peerA);

        $agg = $this->service->aggregatePeerFeedback($this->appraisal->fresh());

        $this->assertSame(4.0, $agg['average']);
        $this->assertSame(1, $agg['submittedCount']);
        $this->assertSame(1, $agg['pendingCount']);
        $this->assertSame(0, $agg['declinedCount']);
    }

    public function test_apply_weighted_rating_blends_peer_average_when_setting_present(): void
    {
        // Configure 60 manager / 20 self / 20 peer (peer reallocated from manager).
        $settings = app(SettingService::class);
        $settings->set('hrm.appraisal.self_evaluation_weight', 20);
        $settings->set('hrm.appraisal.manager_evaluation_weight', 80);
        $settings->set('hrm.appraisal.peer_evaluation_weight', 20);
        $settings->flushCache();

        $this->service->submitPeerFeedback($this->appraisal, $this->peerB, ['rating' => 5.0]);

        // self=3.0, manager=4.0, peer=5.0 ; effective weights 20/60/20
        //   = 0.6 + 2.4 + 1.0 = 4.00
        $final = $this->service->applyWeightedRating($this->appraisal->fresh(), 3.0, 4.0);

        $this->assertSame(4.00, $final);
        $this->assertSame(4.00, (float) $this->appraisal->fresh()->overall_rating);
    }

    public function test_apply_weighted_rating_falls_back_to_two_component_without_peer_setting(): void
    {
        // No peer_evaluation_weight setting and no peer feedback ->
        // 20/80 self/manager blend.
        $final = $this->service->applyWeightedRating($this->appraisal, 3.0, 4.0);
        // 3.0 * 0.2 + 4.0 * 0.8 = 3.80
        $this->assertSame(3.80, $final);
    }

    public function test_http_submit_403_for_caller_who_is_not_the_reviewer_without_admin_grant(): void
    {
        // peerB is the target reviewer but caller is a different employee
        // user with no admin grant -> HTTP layer must 403.
        $strangerEmp = Employee::create([
            'first_name' => 'Stranger', 'last_name' => 'Caller',
            'email' => 'stranger@peer.example', 'employee_id' => 'STR-001', 'status' => 'active',
        ]);
        $strangerUser = User::create([
            'name' => 'Stranger', 'email' => 'stranger@peer.example', 'password' => 'password',
        ]);
        $strangerEmp->update(['user_id' => $strangerUser->id]);
        $strangerUser->roles()->attach(Role::where('slug', 'employee')->firstOrFail());

        $response = $this->actingAs($strangerUser, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->postJson("/api/v1/appraisals/{$this->appraisal->id}/peer-feedback/submit", [
                'reviewerId' => $this->peerB->id,
                'rating'     => 4.0,
            ]);

        $response->assertStatus(403);
        $this->assertSame(0, AppraisalPeerFeedback::query()
            ->where('appraisal_id', $this->appraisal->id)
            ->where('reviewer_id', $this->peerB->id)
            ->where('status', AppraisalPeerFeedback::STATUS_SUBMITTED)
            ->count(),
            'Forbidden submit must not persist a row.');
    }
}
