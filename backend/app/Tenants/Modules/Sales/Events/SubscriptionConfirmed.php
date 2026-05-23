<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Events;

use App\Models\Tenant\Subscription;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Emitted when a software Subscription transitions to confirmed.
 *
 * Listener responsibilities (out of scope for this implementation phase):
 *   - Provision a Stancl Central\Tenant for the customer if none exists.
 *   - Create the first admin User on the new tenant's DB.
 *   - Email the customer their activation/login link.
 *   - Update `subscription.provisioned_tenant_id` + `provisioned_at`.
 *
 * Until that listener ships, the registered listener is a no-op + log entry
 * so the flow is observable end-to-end without blocking the sale.
 */
class SubscriptionConfirmed
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Subscription $subscription)
    {
    }
}
