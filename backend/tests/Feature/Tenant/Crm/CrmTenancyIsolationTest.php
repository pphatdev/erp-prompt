<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Crm;

use App\Models\Central\Tenant;
use App\Models\Tenant\CrmActivity;
use App\Models\Tenant\CrmContact;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Lead;
use App\Models\Tenant\Opportunity;
use Database\Seeders\TenantDatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * P0 — verifies that CRM data created under one tenant context is
 * structurally invisible to a second tenant context. Each model uses the
 * BelongsToTenant trait, so the global scope should filter by tenant_id.
 */
class CrmTenancyIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;
    private Tenant $tenantB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenantA = Tenant::create(['id' => 'tenant-a', 'handle' => 'tenant-a', 'name' => 'Tenant A']);
        $this->tenantB = Tenant::create(['id' => 'tenant-b', 'handle' => 'tenant-b', 'name' => 'Tenant B']);
    }

    protected function tearDown(): void
    {
        tenancy()->end();
        parent::tearDown();
    }

    public function test_tenant_b_cannot_see_tenant_a_leads_or_opportunities(): void
    {
        // Seed Tenant A with a full CRM record set
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);

        $customerA = Customer::create([
            'name'          => 'Alpha Inc',
            'customer_type' => 'business',
            'status'        => 'active',
        ]);
        $leadA = Lead::create([
            'title'       => 'Alpha Pipeline',
            'customer_id' => $customerA->id,
            'status'      => 'new',
        ]);
        $oppA = Opportunity::create([
            'title'       => 'Alpha Big Deal',
            'customer_id' => $customerA->id,
            'lead_id'     => $leadA->id,
            'stage'       => Opportunity::STAGE_QUALIFIED,
        ]);

        // Switch to Tenant B and seed its own DB
        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $this->seed(TenantDatabaseSeeder::class);

        $this->assertSame(0, Lead::count(), 'Tenant B must not see Tenant A leads.');
        $this->assertSame(0, Opportunity::count(), 'Tenant B must not see Tenant A opportunities.');
        $this->assertNull(Lead::find($leadA->id));
        $this->assertNull(Opportunity::find($oppA->id));
    }

    public function test_tenant_b_cannot_see_tenant_a_contacts_or_activities(): void
    {
        tenancy()->initialize($this->tenantA);
        $this->seed(TenantDatabaseSeeder::class);

        $customerA = Customer::create([
            'name'          => 'Alpha Inc',
            'customer_type' => 'business',
            'status'        => 'active',
        ]);
        $contactA = CrmContact::create([
            'customer_id' => $customerA->id,
            'first_name'  => 'Anna',
            'last_name'   => 'Alpha',
        ]);
        $activityA = CrmActivity::create([
            'trackable_type' => Customer::class,
            'trackable_id'   => $customerA->id,
            'activity_type'  => 'note',
            'subject'        => 'A confidential meeting note',
            'status'         => 'pending',
        ]);

        tenancy()->end();
        tenancy()->initialize($this->tenantB);
        $this->seed(TenantDatabaseSeeder::class);

        $this->assertSame(0, CrmContact::count(), 'Tenant B must not see Tenant A contacts.');
        $this->assertSame(0, CrmActivity::count(), 'Tenant B must not see Tenant A activities.');
        $this->assertNull(CrmContact::find($contactA->id));
        $this->assertNull(CrmActivity::find($activityA->id));
    }
}
