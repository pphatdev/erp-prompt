<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Listeners;

use App\Models\Tenant\Customer;
use App\Tenants\Modules\Sales\Events\SubscriptionConfirmed;
use App\Tenants\Modules\Sales\Services\TenantProvisioningService;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProvisionSubscriptionTenant
{
    public function __construct(private readonly TenantProvisioningService $provisioner) {}

    public function handle(SubscriptionConfirmed $event): void
    {
        $sub = $event->subscription->fresh();
        if (!$sub) {
            return;
        }

        $customer = Customer::find($sub->customer_id);
        if (!$customer) {
            Log::warning('SubscriptionConfirmed fired with missing customer.', [
                'subscription_id' => $sub->id,
                'customer_id'     => $sub->customer_id,
            ]);
            return;
        }

        if (!$customer->isTenantCustomer()) {
            Log::info('Subscription confirmed for non-tenant customer — skipping provisioning.', [
                'subscription_id' => $sub->id,
                'customer_type'   => $customer->customer_type,
            ]);
            return;
        }

        try {
            $this->provisioner->provisionForCustomer($customer, $sub);
        } catch (Throwable $e) {
            Log::error('Tenant provisioning failed on subscription confirm.', [
                'subscription_id' => $sub->id,
                'customer_id'     => $customer->id,
                'error'           => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
