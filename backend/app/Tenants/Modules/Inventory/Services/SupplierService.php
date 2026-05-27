<?php

declare(strict_types=1);

namespace App\Tenants\Modules\Inventory\Services;

use App\Models\Tenant\Supplier;
use DomainException;
use Illuminate\Database\Eloquent\Builder;

class SupplierService
{
    public function buildQuery(): Builder
    {
        return Supplier::query()->orderBy('name');
    }

    public function create(array $data): Supplier
    {
        if (!empty($data['code']) && Supplier::where('code', $data['code'])->exists()) {
            throw new DomainException("A supplier with code '{$data['code']}' already exists.");
        }
        if (isset($data['rating'])) {
            $this->assertRatingInRange($data['rating']);
        }
        return Supplier::create($data);
    }

    public function update(Supplier $s, array $data): Supplier
    {
        if (!empty($data['code']) && $data['code'] !== $s->code
            && Supplier::where('code', $data['code'])->where('id', '!=', $s->id)->exists()) {
            throw new DomainException("A supplier with code '{$data['code']}' already exists.");
        }
        if (isset($data['rating'])) {
            $this->assertRatingInRange($data['rating']);
        }
        $s->update($data);
        return $s->fresh();
    }

    /**
     * Soft-archive a supplier. Blocked when any open (non-terminal) Purchase
     * Order still references the supplier — operator must cancel or fully
     * receive those POs first so AP doesn't get stranded.
     */
    public function archive(Supplier $s): Supplier
    {
        $openCount = \App\Models\Tenant\PurchaseOrder::query()
            ->where('supplier_id', $s->id)
            ->whereIn('status', \App\Models\Tenant\PurchaseOrder::OPEN_STATUSES)
            ->count();
        if ($openCount > 0) {
            throw new DomainException(
                "Cannot archive supplier '{$s->name}' — {$openCount} open purchase order(s) still reference it. " .
                'Cancel or fully receive them first.'
            );
        }

        $s->update(['is_active' => false]);
        $s->delete();
        return $s;
    }

    private function assertRatingInRange(int $rating): void
    {
        if ($rating < 1 || $rating > 5) {
            throw new DomainException('Supplier rating must be between 1 and 5.');
        }
    }
}
