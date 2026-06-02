<?php

declare(strict_types=1);

namespace App\Tenants\Modules\POS\Services;

use App\Models\Tenant\PosTerminal;
use DomainException;
use Illuminate\Support\Facades\DB;

/**
 * Admin lifecycle for register stations. Cashiers never touch this -
 * they pick from active terminals at shift open.
 */
class PosTerminalService
{
    public function create(array $data): PosTerminal
    {
        if (PosTerminal::where('code', $data['code'])->exists()) {
            throw new DomainException("Terminal code '{$data['code']}' is already taken.");
        }

        return PosTerminal::create([
            'code' => $data['code'],
            'name' => $data['name'],
            'warehouse_id' => $data['warehouse_id'],
            'petty_cash_account_id' => $data['petty_cash_account_id'] ?? null,
            'location' => $data['location'] ?? null,
            'status' => $data['status'] ?? PosTerminal::STATUS_ACTIVE,
            'notes' => $data['notes'] ?? null,
        ]);
    }

    public function update(PosTerminal $terminal, array $data): PosTerminal
    {
        return DB::transaction(function () use ($terminal, $data) {
            if (isset($data['code']) && $data['code'] !== $terminal->code) {
                if (PosTerminal::where('code', $data['code'])->where('id', '!=', $terminal->id)->exists()) {
                    throw new DomainException("Terminal code '{$data['code']}' is already taken.");
                }
            }
            $terminal->update(array_filter([
                'code' => $data['code'] ?? null,
                'name' => $data['name'] ?? null,
                'warehouse_id' => $data['warehouse_id'] ?? null,
                'petty_cash_account_id' => array_key_exists('petty_cash_account_id', $data)
                    ? $data['petty_cash_account_id']
                    : null,
                'location' => $data['location'] ?? null,
                'status' => $data['status'] ?? null,
                'notes' => $data['notes'] ?? null,
            ], static fn ($v) => $v !== null));
            return $terminal->fresh();
        });
    }

    public function disable(PosTerminal $terminal): PosTerminal
    {
        if ($terminal->shifts()->where('status', \App\Models\Tenant\PosShift::STATUS_OPEN)->exists()) {
            throw new DomainException("Cannot disable terminal '{$terminal->code}' while a shift is open.");
        }
        $terminal->update(['status' => PosTerminal::STATUS_DISABLED]);
        return $terminal;
    }

    public function destroy(PosTerminal $terminal): void
    {
        if ($terminal->orders()->exists()) {
            throw new DomainException("Cannot delete terminal '{$terminal->code}'; it has order history. Disable instead.");
        }
        $terminal->delete();
    }
}
