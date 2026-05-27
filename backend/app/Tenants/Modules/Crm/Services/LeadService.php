<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Crm\Services;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Lead;
use App\Models\Tenant\Opportunity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Lead lifecycle service.
 *
 * Qualification creates an Opportunity but no longer creates the Customer.
 * Customer creation is deferred to QuotationService::win — see
 * `rules/hybrid_sales_business_flow.md`. Leads without an existing customer
 * link arrive at qualification with `customer_id = null`; the Opportunity
 * is allowed to mirror that until the deal is Won.
 */
class LeadService
{
    public function buildQuery(): Builder
    {
        return Lead::query()->with('customer')->orderByDesc('created_at');
    }

    public function createLead(array $data): Lead
    {
        return Lead::create($data);
    }

    public function qualifyToOpportunity(Lead $lead, array $data): Opportunity
    {
        return DB::transaction(function () use ($lead, $data) {
            // If the caller passes an existing customer_id, link it on the
            // Lead. Otherwise leave customer_id null — Sales will create the
            // Customer when the eventual Quotation is Won.
            $customerId = $lead->customer_id;
            if (empty($customerId) && !empty($data['customer_id'])) {
                $existing = Customer::findOrFail($data['customer_id']);
                $customerId = $existing->id;
                $lead->update(['customer_id' => $customerId]);
            }

            // New opportunity lands in the "Opportunities" Kanban column.
            // The rep moves it forward through Schedules → Contacted → Won/Lost.
            $opp = Opportunity::create([
                'lead_id'         => $lead->id,
                'customer_id'     => $customerId,
                'title'           => $data['opportunity_title'] ?? $lead->title,
                'stage'           => Opportunity::STAGE_NEW,
                'estimated_value' => $data['estimated_value'] ?? $lead->estimated_value ?? 0,
                'probability'     => $data['probability'] ?? 50,
                'close_date'      => $data['close_date'] ?? null,
                'notes'           => $data['notes'] ?? null,
            ]);

            $lead->update(['status' => 'qualified']);

            return $opp->load(['customer', 'lead']);
        });
    }
}
