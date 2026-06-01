<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\BankAccount;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BankAccountService
{
    public function buildQuery(): Builder
    {
        return BankAccount::query()
            ->with('glAccount')
            ->orderByDesc('is_default')
            ->orderBy('name');
    }

    public function create(array $data): BankAccount
    {
        $this->assertGlAccountIsAsset($data['account_id'] ?? null);

        return DB::transaction(function () use ($data) {
            $bank = BankAccount::create($data);
            if (!empty($data['is_default'])) {
                $this->demoteOtherDefaults($bank);
            }
            return $bank->fresh('glAccount');
        });
    }

    public function update(BankAccount $bank, array $data): BankAccount
    {
        if (array_key_exists('account_id', $data) && $data['account_id'] !== $bank->account_id) {
            $this->assertGlAccountIsAsset($data['account_id']);
        }

        return DB::transaction(function () use ($bank, $data) {
            $bank->update($data);
            if (!empty($data['is_default'])) {
                $this->demoteOtherDefaults($bank);
            }
            return $bank->fresh('glAccount');
        });
    }

    /**
     * Soft-archive a bank account. Refuses while it is still the tenant's
     * default — operator must promote another account first so the AP/AR
     * flows don't lose their fallback bank.
     */
    public function archive(BankAccount $bank): BankAccount
    {
        if ($bank->is_default) {
            throw new DomainException(
                "Cannot archive '{$bank->name}' — it is the default bank account. " .
                'Promote another bank to default first.'
            );
        }

        $bank->update(['is_active' => false]);
        $bank->delete();
        return $bank;
    }

    /**
     * GL accounts linked to a bank must be of type `asset` (typical: Cash,
     * Cash at Bank, Petty Cash). Anything else would break ledger semantics
     * the moment a Payment posts.
     */
    private function assertGlAccountIsAsset(?string $accountId): void
    {
        if ($accountId === null) return;
        $account = Account::query()->find($accountId);
        if (!$account) {
            throw new DomainException('GL account not found.');
        }
        if ($account->type !== 'asset') {
            throw new DomainException(
                "GL account '{$account->code} · {$account->name}' must be type 'asset' " .
                "(got '{$account->type}'). Bank accounts can only be linked to asset accounts."
            );
        }
    }

    /**
     * Only one bank can be flagged `is_default = true` at a time. Promoting
     * a new default automatically demotes everyone else.
     */
    private function demoteOtherDefaults(BankAccount $bank): void
    {
        BankAccount::query()
            ->where('id', '!=', $bank->id)
            ->where('is_default', true)
            ->update(['is_default' => false]);
    }
}
