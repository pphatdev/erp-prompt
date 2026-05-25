# Testing Strategy: CRM Module

## 1. Priority Matrix (P0-P2)

| Priority | Category | Requirement / Test Case |
| :--- | :--- | :--- |
| **P0** | **Tenancy Isolation** | Leads, Opportunities, Contacts, and Activities must be strictly scoped to `tenant_id`. |
| **P0** | **Transaction Integrity** | Lead qualification (Lead update + Customer create + Opportunity create) must be fully atomic. |
| **P0** | **Polymorphic Validation** | Polymorphic activity target (`trackable_id`) must belong to the logged-in tenant database. |
| **P0** | **Controller Responses** | All controllers must return API resources directly to prevent `MissingValue` leaks. |
| **P1** | **FSM Enforcements** | Moving opportunities out of terminal states (`won`/`lost`) must be rejected with `422 Unprocessable Content`. |
| **P1** | **Validation Requirements** | Transitioning opportunity stage to `lost` must require a non-empty `loss_reason` string. |
| **P1** | **Audit Trail Logging** | Lead conversions and opportunity status updates must write to the `audit_logs` table. |
| **P2** | **Forecasting Logic** | Weighted value calculations (`estimated_value * probability`) must match mathematical models. |
| **P2** | **Filters & Search** | Pipeline boards must filter correctly by source, stage, and value ranges. |

---

## 2. Backend Testing (Pest PHP Templates)

### Tenancy Isolation (P0)
```php
it('cannot access or query another tenant\'s opportunity', function () {
    // Set up Tenant A and Tenant B context
    $oppTenantB = Opportunity::factory()->create(['tenant_id' => 'tenant-b']);
    
    // Attempt to access via Tenant A's route
    $this->getJson("/api/v1/opportunities/{$oppTenantB->id}")
         ->assertStatus(404);
});
```

### Atomic Lead Conversion (P0)
```php
it('rolls back customer and opportunity creation if status update fails', function () {
    $lead = Lead::factory()->create(['customer_id' => null, 'status' => 'new']);

    // Mock Lead model's update to throw an exception
    $this->mock(LeadService::class)
         ->shouldReceive('qualifyToOpportunity')
         ->andThrow(new \RuntimeException('Database connection failure'));

    try {
        $this->postJson("/api/v1/leads/{$lead->id}/qualify", [
            'title' => 'Convert Deal',
            'estimated_value' => 5000,
        ]);
    } catch (\Exception $e) {}

    // Assert database is rolled back
    expect(Customer::where('email', $lead->email)->exists())->toBeFalse();
    expect(Opportunity::where('title', 'Convert Deal')->exists())->toBeFalse();
    expect($lead->fresh()->status)->toBe('new');
});
```

### Opportunity Lost Validation (P1)
```php
it('requires a loss reason when transitioning to lost stage', function () {
    $opp = Opportunity::factory()->create(['stage' => 'discovery']);

    $this->patchJson("/api/v1/opportunities/{$opp->id}/stage", [
        'stage' => 'lost',
        'loss_reason' => '', // Empty
    ])->assertStatus(422)
      ->assertJsonValidationErrors(['loss_reason']);
});
```

### Polymorphic Target Verification (P0)
```php
it('rejects logging an activity against an unauthorized tenant\'s lead', function () {
    $foreignLead = Lead::factory()->create(['tenant_id' => 'tenant-b']);

    $this->postJson('/api/v1/activities', [
        'activity_type' => 'call',
        'subject' => 'Call discussion',
        'trackable_type' => Lead::class,
        'trackable_id' => $foreignLead->id, // Tenant B's Lead
    ])->assertStatus(403); // Forbidden
});
```

---

## 3. Frontend E2E / Vitest Verification (Nuxt)
* **Kanban Drag-and-Drop Action:** Verify drop fires a debounced PATCH request to `/api/v1/opportunities/{id}/stage` with correct payloads.
* **Loss Reason Modal:** Dragging a deal card to the "Lost" column must trigger a popup demanding a non-empty reason before confirming the action.
* **Polymorphic Timeline Icons:** Verify calls, emails, and meetings render the correct PrimeVue timeline templates.
