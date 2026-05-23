<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hybrid Sales — Phase 5: Subscriptions (software fulfillment).
 *
 * One Subscription per Sales Order that contains at least one software line.
 * Subscription items mirror the software-typed OrderItem rows.
 *
 * On confirm() the SubscriptionConfirmed event is fired. A listener (out of
 * scope for this phase — currently a no-op log) will eventually:
 *   - provision the customer's Stancl tenant (if not yet provisioned)
 *   - create the customer's first admin User
 *   - email them an activation link
 *
 * `provisioned_tenant_id` is the eventual link to the Central tenants table
 * once provisioning lands.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->string('subscription_number')->unique();
                $table->uuid('order_id');
                $table->uuid('customer_id');
                $table->string('status', 20)->default('new');   // new|confirmed|cancelled|active|expired
                $table->date('start_date')->nullable();
                $table->date('end_date')->nullable();
                $table->string('billing_cycle', 20)->default('monthly'); // monthly|annual|one_time
                $table->decimal('total_amount', 14, 2)->default(0);
                // Filled in by the provisioning listener once it runs.
                $table->string('provisioned_tenant_id')->nullable();
                $table->timestamp('provisioned_at')->nullable();
                $table->uuid('confirmed_by')->nullable();
                $table->timestamp('confirmed_at')->nullable();
                $table->uuid('cancelled_by')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('cancel_reason')->nullable();

                $table->string('tenant_id');
                $table->timestamps();
                $table->softDeletes();

                $table->unique('order_id', 'subscriptions_order_id_unique');
                $table->foreign('order_id')->references('id')->on('orders')->onDelete('restrict');
                $table->foreign('customer_id')->references('id')->on('customers')->onDelete('restrict');
                $table->index(['tenant_id', 'status'], 'subscriptions_status_idx');
            });
        }

        if (!Schema::hasTable('subscription_items')) {
            Schema::create('subscription_items', function (Blueprint $table) {
                $table->uuid('id')->primary();
                $table->uuid('subscription_id');
                $table->uuid('order_item_id')->nullable();
                $table->uuid('product_id')->nullable();
                $table->uuid('variant_id')->nullable();
                $table->string('product_name');
                $table->string('variant_sku')->nullable();
                $table->decimal('quantity', 12, 2);
                $table->decimal('unit_price', 14, 2);
                $table->decimal('line_total', 14, 2);

                $table->string('tenant_id');
                $table->timestamps();

                $table->foreign('subscription_id')->references('id')->on('subscriptions')->onDelete('cascade');
                $table->foreign('order_item_id')->references('id')->on('order_items')->onDelete('set null');
                $table->index(['tenant_id', 'subscription_id'], 'subscription_items_sub_idx');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_items');
        Schema::dropIfExists('subscriptions');
    }
};
