<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\IAM;

use App\Models\Tenant\Application;
use App\Models\Tenant\JobVacancy;
use App\Models\Tenant\WorkflowStatus;
use App\Tenants\Modules\IAM\Services\WorkflowStatusService;
use DomainException;
use Tests\Feature\TenantTestCase;

/**
 * Phase 11 - Recruitment Candidate Pipeline Settings.
 *
 * Locks the configurable Kanban behaviour on top of the existing
 * workflow_statuses surface:
 *   - Auto-slug of `key` from `label` on store
 *   - Single-initial invariant per module
 *   - Deletion blocked while live applications still reference a status
 *   - Reorder bulk-updates sequences (rejects unknown keys)
 *   - setDefault demotes prior initial + promotes target
 *   - metadata jsonb persisted round-trip
 */
class CandidatePipelineSettingsTest extends TenantTestCase
{
    private WorkflowStatusService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(WorkflowStatusService::class);
    }

    private function authJson(string $method, string $uri, array $data = [])
    {
        return $this->actingAs($this->admin, 'api')
            ->withHeaders(['X-Tenant-Handle' => 'test'])
            ->json($method, $uri, $data);
    }

    // ---------------------------------------------------------------
    // CRUD + validation
    // ---------------------------------------------------------------

    public function test_store_auto_slugs_key_from_label_when_omitted(): void
    {
        $response = $this->authJson('POST', '/api/v1/workflow-statuses', [
            'module' => 'hrm.application',
            'label'  => 'Background Check',
            'color'  => 'info',
            'icon'   => 'ti-shield-check',
            'sequence' => 4,
            'is_initial' => false,
            'is_terminal' => false,
            'allowed_next' => ['interview'],
        ]);

        $response->assertStatus(201);
        $this->assertSame('background_check', $response->json('data.key'));
        $this->assertSame('Background Check', $response->json('data.label'));
    }

    public function test_store_slug_collision_suffixes_with_numeric(): void
    {
        // Seed already supplies `screening`; a second status labelled the
        // same way must auto-resolve to `screening_2`.
        $response = $this->authJson('POST', '/api/v1/workflow-statuses', [
            'module' => 'hrm.application',
            'label'  => 'Screening',
            'sequence' => 30,
        ]);

        $response->assertStatus(201);
        $this->assertSame('screening_2', $response->json('data.key'));
    }

    public function test_store_rejects_invalid_slug(): void
    {
        $response = $this->authJson('POST', '/api/v1/workflow-statuses', [
            'module' => 'hrm.application',
            'key'    => 'Bad Key!',
            'label'  => 'Bad Key',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['key']);
    }

    public function test_store_persists_metadata_round_trip(): void
    {
        $metadata = [
            'kanban' => [
                'visible'            => true,
                'sort'               => 'newest',
                'displayFields'      => ['candidateName', 'vacancyTitle', 'rating'],
                'conversionEligible' => false,
            ],
        ];

        $response = $this->authJson('POST', '/api/v1/workflow-statuses', [
            'module'   => 'hrm.application',
            'label'    => 'Pre Screening',
            'sequence' => 15,
            'metadata' => $metadata,
        ]);

        $response->assertStatus(201);
        $this->assertSame($metadata, $response->json('data.metadata'));

        // And reading it back returns the same shape (jsonb round-trip).
        $id = $response->json('data.id');
        $show = $this->authJson('GET', "/api/v1/workflow-statuses/{$id}");
        $this->assertSame($metadata, $show->json('data.metadata'));
    }

    // ---------------------------------------------------------------
    // Single-initial invariant
    // ---------------------------------------------------------------

    public function test_single_initial_invariant_demotes_prior_on_store(): void
    {
        $priorInitial = WorkflowStatus::where('module', 'hrm.application')
            ->where('is_initial', true)
            ->firstOrFail();

        $response = $this->authJson('POST', '/api/v1/workflow-statuses', [
            'module'     => 'hrm.application',
            'label'      => 'Inbox',
            'sequence'   => 0,
            'is_initial' => true,
        ]);

        $response->assertStatus(201);
        $this->assertTrue((bool) $response->json('data.isInitial'));

        // Old `applied` row was demoted.
        $this->assertFalse((bool) $priorInitial->fresh()->is_initial);

        // Exactly one initial remains.
        $count = WorkflowStatus::where('module', 'hrm.application')
            ->where('is_initial', true)
            ->count();
        $this->assertSame(1, $count);
    }

    public function test_single_initial_invariant_demotes_prior_on_update(): void
    {
        $screening = WorkflowStatus::where('module', 'hrm.application')
            ->where('key', 'screening')
            ->firstOrFail();

        $response = $this->authJson('PUT', "/api/v1/workflow-statuses/{$screening->id}", [
            'is_initial' => true,
        ]);

        $response->assertStatus(200);
        $this->assertTrue((bool) $screening->fresh()->is_initial);

        $count = WorkflowStatus::where('module', 'hrm.application')
            ->where('is_initial', true)
            ->count();
        $this->assertSame(1, $count, 'Promoting via PUT must demote the prior initial.');
    }

    public function test_set_default_endpoint_promotes_and_demotes(): void
    {
        $applied = WorkflowStatus::where('module', 'hrm.application')
            ->where('key', 'applied')
            ->firstOrFail();
        $screening = WorkflowStatus::where('module', 'hrm.application')
            ->where('key', 'screening')
            ->firstOrFail();

        $this->assertTrue((bool) $applied->is_initial, 'Seed should mark applied as initial.');
        $this->assertFalse((bool) $screening->is_initial);

        $response = $this->authJson('POST', "/api/v1/workflow-statuses/{$screening->id}/set-default");
        $response->assertStatus(200);

        $this->assertTrue((bool) $screening->fresh()->is_initial);
        $this->assertFalse((bool) $applied->fresh()->is_initial);
    }

    public function test_set_default_rejects_terminal_status(): void
    {
        $hired = WorkflowStatus::where('module', 'hrm.application')
            ->where('key', 'onboarding') // terminal
            ->firstOrFail();
        $this->assertTrue((bool) $hired->is_terminal);

        $response = $this->authJson('POST', "/api/v1/workflow-statuses/{$hired->id}/set-default");
        $response->assertStatus(422);
        $this->assertStringContainsString('terminal', $response->json('message'));
    }

    // ---------------------------------------------------------------
    // Delete safety
    // ---------------------------------------------------------------

    public function test_destroy_blocked_when_applications_reference_status(): void
    {
        $vacancy = JobVacancy::create([
            'title'     => 'Engineer',
            'status'    => 'open',
            'posted_at' => now(),
        ]);
        Application::create([
            'job_vacancy_id'  => $vacancy->id,
            'applicant_name'  => 'Pat Quinn',
            'applicant_email' => 'pat.quinn@example.com',
            'status'          => 'screening',
            'applied_at'      => now(),
        ]);

        $screening = WorkflowStatus::where('module', 'hrm.application')
            ->where('key', 'screening')
            ->firstOrFail();

        $response = $this->authJson('DELETE', "/api/v1/workflow-statuses/{$screening->id}");

        $response->assertStatus(422);
        $this->assertStringContainsString('still reference', $response->json('message'));
        $this->assertNull($screening->fresh()->deleted_at, 'Row must not be soft-deleted.');
    }

    public function test_destroy_succeeds_when_no_references(): void
    {
        // The seeded `assessment` status has no rows pointing at it in a
        // fresh tenant, so it can be archived without tripping the guard.
        $assessment = WorkflowStatus::where('module', 'hrm.application')
            ->where('key', 'assessment')
            ->firstOrFail();

        $response = $this->authJson('DELETE', "/api/v1/workflow-statuses/{$assessment->id}");

        $response->assertStatus(200);
        $this->assertNotNull($assessment->fresh()->deleted_at);
    }

    // ---------------------------------------------------------------
    // Reorder
    // ---------------------------------------------------------------

    public function test_reorder_updates_sequences_to_match_order(): void
    {
        $response = $this->authJson('PATCH', '/api/v1/workflow-statuses/reorder', [
            'module' => 'hrm.application',
            'orderedKeys' => ['screening', 'applied', 'shortlisted'],
        ]);

        $response->assertStatus(200);

        $sequences = WorkflowStatus::where('module', 'hrm.application')
            ->whereIn('key', ['applied', 'screening', 'shortlisted'])
            ->orderBy('sequence')
            ->pluck('sequence', 'key')
            ->all();

        $this->assertSame(1, $sequences['screening']);
        $this->assertSame(2, $sequences['applied']);
        $this->assertSame(3, $sequences['shortlisted']);
    }

    public function test_reorder_rejects_unknown_key(): void
    {
        $response = $this->authJson('PATCH', '/api/v1/workflow-statuses/reorder', [
            'module' => 'hrm.application',
            'orderedKeys' => ['applied', 'totally_made_up_stage'],
        ]);

        $response->assertStatus(422);
        $this->assertStringContainsString('totally_made_up_stage', $response->json('message'));
    }

    public function test_reorder_leaves_off_ramp_terminals_untouched(): void
    {
        $rejectedBefore = WorkflowStatus::where('module', 'hrm.application')
            ->where('key', 'rejected')
            ->firstOrFail();
        $originalSequence = (int) $rejectedBefore->sequence;

        $this->authJson('PATCH', '/api/v1/workflow-statuses/reorder', [
            'module' => 'hrm.application',
            'orderedKeys' => ['applied', 'screening'],
        ])->assertStatus(200);

        $this->assertSame(
            $originalSequence,
            (int) $rejectedBefore->fresh()->sequence,
            'Off-ramp terminals omitted from the order list must keep their sequence.'
        );
    }

    public function test_reorder_request_rejects_empty_ordered_keys(): void
    {
        $response = $this->authJson('PATCH', '/api/v1/workflow-statuses/reorder', [
            'module' => 'hrm.application',
            'orderedKeys' => [],
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['orderedKeys']);
    }

    // ---------------------------------------------------------------
    // Service-level direct paths
    // ---------------------------------------------------------------

    public function test_service_set_as_initial_throws_for_terminal(): void
    {
        $rejected = WorkflowStatus::where('module', 'hrm.application')
            ->where('key', 'rejected')
            ->firstOrFail();

        $this->expectException(DomainException::class);
        $this->service->setAsInitial($rejected);
    }

    public function test_service_assert_deletable_passes_for_unmapped_module(): void
    {
        // A custom workflow module without an entry in REFERENCE_MAP must
        // fall through without throwing — tenants can use the table for
        // custom flows we don't know about.
        $status = WorkflowStatus::create([
            'module'      => 'tenant.custom_widget',
            'key'         => 'draft',
            'label'       => 'Draft',
            'color'       => 'secondary',
            'sequence'    => 1,
            'is_initial'  => true,
            'is_terminal' => false,
            'allowed_next' => [],
        ]);

        $this->service->assertDeletable($status);
        $this->assertTrue(true); // reached without throwing
    }
}
