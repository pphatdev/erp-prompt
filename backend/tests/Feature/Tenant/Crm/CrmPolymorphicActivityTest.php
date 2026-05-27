<?php

declare(strict_types=1);

namespace Tests\Feature\Tenant\Crm;

use App\Models\Tenant\CrmActivity;
use App\Models\Tenant\Customer;
use App\Tenants\Modules\Crm\Services\ActivityService;
use Tests\Feature\TenantTestCase;

class CrmPolymorphicActivityTest extends TenantTestCase
{
    private ActivityService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(ActivityService::class);
    }

    public function test_log_activity_throws_when_trackable_class_does_not_exist(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('does not exist');

        $this->service->logActivity([
            'trackable_type' => 'App\\Models\\Tenant\\NotARealModel',
            'trackable_id'   => '00000000-0000-0000-0000-000000000000',
            'activity_type'  => 'note',
            'subject'        => 'Should never persist',
        ]);
    }

    public function test_log_activity_throws_when_trackable_id_not_found(): void
    {
        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('not found');

        $this->service->logActivity([
            'trackable_type' => Customer::class,
            'trackable_id'   => '00000000-0000-0000-0000-000000000000',
            'activity_type'  => 'call',
            'subject'        => 'Phantom customer',
        ]);
    }

    public function test_log_activity_persists_when_trackable_exists_in_tenant(): void
    {
        $customer = Customer::create([
            'name'          => 'Acme Corp',
            'customer_type' => 'business',
            'status'        => 'active',
        ]);

        $activity = $this->service->logActivity([
            'trackable_type' => Customer::class,
            'trackable_id'   => $customer->id,
            'activity_type'  => 'meeting',
            'subject'        => 'Discovery call',
            'status'         => 'pending',
        ]);

        $this->assertNotNull($activity->id);
        $this->assertSame('pending', $activity->status);
        $this->assertSame($customer->id, $activity->trackable_id);
    }

    public function test_complete_activity_flips_status(): void
    {
        $customer = Customer::create([
            'name'          => 'Acme Corp',
            'customer_type' => 'business',
            'status'        => 'active',
        ]);
        $activity = CrmActivity::create([
            'trackable_type' => Customer::class,
            'trackable_id'   => $customer->id,
            'activity_type'  => 'task',
            'subject'        => 'Send proposal',
            'status'         => 'pending',
        ]);

        $completed = $this->service->completeActivity($activity);
        $this->assertSame('completed', $completed->status);
    }

    public function test_complete_activity_blocks_double_completion(): void
    {
        $customer = Customer::create([
            'name'          => 'Acme Corp',
            'customer_type' => 'business',
            'status'        => 'active',
        ]);
        $activity = CrmActivity::create([
            'trackable_type' => Customer::class,
            'trackable_id'   => $customer->id,
            'activity_type'  => 'task',
            'subject'        => 'Send proposal',
            'status'         => 'completed',
        ]);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('already completed');
        $this->service->completeActivity($activity);
    }
}
