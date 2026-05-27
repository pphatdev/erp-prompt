<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Sales\Services;

use App\Models\Tenant\CrmContact;
use App\Models\Tenant\Customer;
use App\Models\Tenant\Lead;
use App\Models\Tenant\Opportunity;
use App\Models\Tenant\Product;
use App\Models\Tenant\Quotation;
use App\Models\Tenant\QuotationItem;
use App\Tenants\Modules\Inventory\Services\PricingService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Quotation lifecycle service (target hybrid sales flow).
 *
 * Status flow:
 *   draft  --win-->  won   (terminal — Lead→Customer conversion + auto Sale Order)
 *   draft  --lose--> lost  (terminal — requires loss_reason)
 *
 * Line items snapshot product_name / product_type / unit_price at quote
 * time so subsequent catalogue edits never alter historical totals.
 */
class QuotationService
{
    public function __construct(
        private readonly OrderService $orders,
        private readonly PricingService $pricing,
    ) {}

    public function create(array $data): Quotation
    {
        return DB::transaction(function () use ($data) {
            $quote = Quotation::create([
                'quote_number'        => $this->generateQuoteNumber(),
                'customer_id'         => $data['customer_id'] ?? null,
                'from_opportunity_id' => $data['from_opportunity_id'] ?? null,
                'status'              => Quotation::STATUS_DRAFT,
                'quote_date'          => $data['quote_date'] ?? now()->toDateString(),
                'valid_until'         => $data['valid_until'] ?? null,
                'due_date'            => $data['due_date'] ?? null,
                'notes'               => $data['notes'] ?? null,
            ]);

            foreach ($data['items'] as $row) {
                $this->buildItem($quote, $row);
            }

            return $this->recalcTotals($quote->fresh('items'));
        });
    }

    public function addItem(Quotation $quote, array $row): QuotationItem
    {
        $this->assertEditable($quote);

        return DB::transaction(function () use ($quote, $row) {
            $item = $this->buildItem($quote, $row);
            $this->recalcTotals($quote->fresh('items'));

            return $item->fresh();
        });
    }

    /**
     * Win the Quotation. Atomic:
     *  - If the linked Opportunity's Lead has no Customer, create one and a
     *    primary CrmContact.
     *  - Mark quotation status=won.
     *  - Auto-create a draft Sale Order from the snapshot.
     *
     * Returns the quotation with `order` and `customer` eager-loaded.
     */
    public function win(Quotation $quote): Quotation
    {
        if ($quote->isLost()) {
            throw new DomainException('Cannot win a lost quotation.');
        }
        if ($quote->isWon()) {
            return $quote->fresh(['customer', 'items', 'order']);
        }
        if ($quote->items()->count() === 0) {
            throw new DomainException('Cannot win a quotation with no items.');
        }

        return DB::transaction(function () use ($quote) {
            $customerId = $quote->customer_id;

            if (empty($customerId)) {
                $opportunity = $quote->opportunity()->with('lead')->first();
                $lead = $opportunity?->lead;

                if (!$lead) {
                    throw new DomainException(
                        'Quotation has no customer and no originating Opportunity/Lead; cannot derive a Customer.'
                    );
                }

                // Any software line → the new account must be provisioned as a
                // tenant customer so OrderService::confirmOrder can spin up
                // its Stancl tenant on confirmation. Pure-hardware deals stay
                // on the lead's declared customer_type (or default business).
                $hasSoftware = $quote->items->contains(
                    fn ($item) => $item->product_type === Product::TYPE_SOFTWARE
                );

                $customerType = $hasSoftware
                    ? Customer::TYPE_TENANT
                    : ($lead->customer_type ?: Customer::TYPE_BUSINESS);

                $fullName = trim(($lead->first_name ?? '') . ' ' . ($lead->last_name ?? ''))
                    ?: ($lead->title ?: 'Customer from Lead');

                $payload = [
                    'name'          => $fullName,
                    'email'         => $lead->email,
                    'phone'         => $lead->phone,
                    'address'       => $lead->address,
                    'customer_type' => $customerType,
                    'status'        => 'active',
                ];

                if ($customerType === Customer::TYPE_TENANT) {
                    // Derive handle from the lead's fullName (lowercased, slug-safe).
                    // Append a 4-char suffix when the base slug is taken so two
                    // "Anna Park" leads never collide on the unique handle index.
                    $payload['tenant_handle'] = $this->deriveTenantHandle($fullName);
                }

                $customer = Customer::create($payload);
                $customerId = $customer->id;

                // Primary contact mirrors the lead's person data (no dangling
                // "Primary" placeholder anymore).
                CrmContact::firstOrCreate(
                    ['customer_id' => $customer->id, 'is_primary' => true],
                    [
                        'first_name' => $lead->first_name ?: 'Primary',
                        'last_name'  => $lead->last_name,
                        'email'      => $lead->email,
                        'phone'      => $lead->phone,
                    ]
                );

                $lead->update(['customer_id' => $customer->id, 'status' => 'qualified']);
                if ($opportunity) {
                    $opportunity->update(['customer_id' => $customer->id]);
                }

                $quote->update(['customer_id' => $customer->id]);
            }

            $quote->update([
                'customer_id' => $customerId,
                'status'      => Quotation::STATUS_WON,
                'won_by'      => Auth::id(),
                'won_at'      => now(),
            ]);

            $this->orders->createFromQuotation($quote->fresh(['items', 'customer']));

            return $quote->fresh(['customer', 'items', 'order']);
        });
    }

    /**
     * Derive a unique `tenant_handle` from a display name.
     *
     *   "Anna Park"     → "anna-park"
     *   "Anna O'Brien"  → "anna-obrien"
     *   ""              → "tenant" (last-resort safety)
     *
     * Truncates to 56 chars to leave room for a `-XXXX` collision suffix
     * within the 60-char column limit.
     */
    private function deriveTenantHandle(string $fullName): string
    {
        $base = Str::slug($fullName, '-') ?: 'tenant';
        $base = Str::substr($base, 0, 56);

        $candidate = $base;
        $attempt = 0;
        while (Customer::where('tenant_handle', $candidate)->exists()) {
            $attempt++;
            $candidate = $base . '-' . Str::lower(Str::random(4));
            if ($attempt > 10) {
                // Fall back to a guaranteed-unique handle if we somehow keep
                // colliding (shouldn't happen — 36^4 = 1.6M possible suffixes).
                $candidate = $base . '-' . Str::lower(Str::random(8));
                break;
            }
        }

        return $candidate;
    }

    /**
     * Mark the Quotation lost. Requires a non-empty loss_reason. If the
     * Quotation was originated from a Lead, that Lead is closed as
     * unqualified.
     */
    public function lose(Quotation $quote, string $lossReason): Quotation
    {
        if (trim($lossReason) === '') {
            throw new DomainException('A loss_reason is required when marking a quotation lost.');
        }
        if ($quote->isWon()) {
            throw new DomainException('Cannot lose a won quotation.');
        }
        if ($quote->isLost()) {
            return $quote->fresh(['customer', 'items']);
        }

        return DB::transaction(function () use ($quote, $lossReason) {
            $quote->update([
                'status'      => Quotation::STATUS_LOST,
                'loss_reason' => $lossReason,
                'lost_by'     => Auth::id(),
                'lost_at'     => now(),
            ]);

            // Close the originating Lead as unqualified, if any.
            $opportunity = $quote->opportunity()->with('lead')->first();
            $opportunity?->lead?->update(['status' => 'unqualified']);

            return $quote->fresh(['customer', 'items']);
        });
    }

    private function buildItem(Quotation $quote, array $row): QuotationItem
    {
        $resolved = $this->pricing->resolveLine($row);
        $product  = $resolved['product'];
        $unitPrice = $resolved['unit_price'];

        $quantity = (float) $row['quantity'];

        return QuotationItem::create([
            'quotation_id' => $quote->id,
            'product_id'   => $product->id,
            'variant_id'   => $resolved['variant']?->id,
            'product_name' => $product->name,
            'product_type' => $product->product_type,
            'variant_sku'  => $resolved['variant_sku'],
            'quantity'     => $quantity,
            'unit_price'   => $unitPrice,
            'line_total'   => round($unitPrice * $quantity, 2),
            'due_date'     => $row['due_date'] ?? null,
            'notes'        => $row['notes'] ?? null,
        ]);
    }

    private function recalcTotals(Quotation $quote): Quotation
    {
        $subtotal = $quote->items->sum('line_total');
        $quote->update([
            'subtotal'     => $subtotal,
            'tax_amount'   => 0,
            'total_amount' => $subtotal,
        ]);

        return $quote->fresh('items');
    }

    private function assertEditable(Quotation $quote): void
    {
        if ($quote->status !== Quotation::STATUS_DRAFT) {
            throw new DomainException(
                "Quotation {$quote->quote_number} is {$quote->status}; only draft quotes are editable."
            );
        }
    }

    private function generateQuoteNumber(): string
    {
        $prefix = app(\App\Tenants\Modules\Settings\Services\SettingService::class)
            ->get('numbering.quotation_prefix');
        if (empty($prefix)) {
            $prefix = 'QT-';
        }
        return $prefix . now()->format('Ymd') . '-' . strtoupper(Str::random(6));
    }
}
