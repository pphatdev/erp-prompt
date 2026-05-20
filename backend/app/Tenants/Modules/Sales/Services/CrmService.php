<?php

namespace App\Tenants\Modules\Sales\Services;

use App\Models\Tenant\Customer;
use App\Models\Tenant\Lead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class CrmService
{
    public function buildCustomersQuery(): Builder
    {
        $query = Customer::query();
        $query->orderBy('created_at', 'desc');
        return $query;
    }

    public function buildLeadsQuery(): Builder
    {
        $query = Lead::query()->with('customer');
        $query->orderBy('created_at', 'desc');
        return $query;
    }

    /**
     * Create a new lead.
     */
    public function createLead(array $data): Lead
    {
        return Lead::create($data);
    }

    /**
     * Convert a lead to a won status and potentially create a customer.
     */
    public function winLead(Lead $lead): Lead
    {
        return DB::transaction(function () use ($lead) {
            $lead->update(['status' => 'won']);
            
            // Log audit change (handled by Auditable trait)
            
            return $lead;
        });
    }
}
