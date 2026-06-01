<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\Account;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

class AccountService
{
    private const TYPES = ['asset', 'liability', 'equity', 'revenue', 'expense'];

    public function buildQuery(): Builder
    {
        return Account::query()
            ->orderBy('code');
    }

    public function create(array $data): Account
    {
        $data['code'] = trim((string) $data['code']);
        $this->assertCodeUnique($data['code']);
        $this->assertParentReachable($data['parent_id'] ?? null);
        $this->assertParentTypeMatches($data['parent_id'] ?? null, $data['type']);

        return Account::create($data)->fresh(['parent']);
    }

    public function update(Account $a, array $data): Account
    {
        if (array_key_exists('code', $data)) {
            $data['code'] = trim((string) $data['code']);
            $this->assertCodeUnique($data['code'], $a->id);
        }

        if (array_key_exists('parent_id', $data) && $data['parent_id'] !== $a->parent_id) {
            if ($data['parent_id'] === $a->id) {
                throw new DomainException('An account cannot be its own parent.');
            }
            $this->assertParentReachable($data['parent_id']);
            $this->assertNotMovingUnderDescendant($a, $data['parent_id']);
            $this->assertParentTypeMatches($data['parent_id'], $data['type'] ?? $a->type);
        }

        // Changing `type` away from children's type would break the hierarchy invariant.
        if (array_key_exists('type', $data) && $data['type'] !== $a->type && $a->children()->exists()) {
            throw new DomainException("Cannot change type — '{$a->name}' has sub-accounts. Re-parent or archive them first.");
        }

        $a->update($data);
        return $a->fresh(['parent']);
    }

    public function archive(Account $a): Account
    {
        if ($a->children()->exists()) {
            throw new DomainException(
                "Cannot archive '{$a->name}' — it still has sub-accounts. Re-parent or archive them first."
            );
        }
        if ($a->ledgerEntries()->exists()) {
            throw new DomainException(
                "Cannot archive '{$a->name}' — it has posted ledger entries. Accounts with history must remain for audit traceability."
            );
        }

        $a->delete();
        return $a;
    }

    /**
     * Return roots with a rolled-up balance per node:
     *   aggregatedBalance = own balance + sum of descendants' balances.
     */
    public function tree(): array
    {
        $all = Account::query()->orderBy('code')->get();
        $byParent = $all->groupBy(fn (Account $a) => $a->parent_id ?? '__root');

        return $this->mapTree($byParent->get('__root', new Collection()), $byParent);
    }

    private function mapTree(Collection $nodes, $byParent): array
    {
        return $nodes->map(function (Account $a) use ($byParent) {
            $children = $this->mapTree($byParent->get($a->id, new Collection()), $byParent);
            $aggregated = array_sum(array_column($children, 'aggregatedBalance')) + (float) $a->balance;

            return [
                'id'                 => $a->id,
                'code'               => $a->code,
                'name'               => $a->name,
                'type'               => $a->type,
                'parentId'           => $a->parent_id,
                'balance'            => (float) $a->balance,
                'aggregatedBalance'  => round($aggregated, 2),
                'childrenCount'      => count($children),
                'children'           => $children,
            ];
        })->all();
    }

    private function assertCodeUnique(string $code, ?string $ignoreId = null): void
    {
        $exists = Account::query()
            ->where('code', $code)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        if ($exists) {
            throw new DomainException("Account code '{$code}' is already in use.");
        }
    }

    private function assertParentReachable(?string $parentId): void
    {
        if ($parentId === null) return;
        if (!Account::query()->whereKey($parentId)->exists()) {
            throw new DomainException('Parent account not found.');
        }
    }

    private function assertParentTypeMatches(?string $parentId, string $childType): void
    {
        if ($parentId === null) return;
        $parent = Account::query()->find($parentId);
        if ($parent && $parent->type !== $childType) {
            throw new DomainException("Sub-account type ({$childType}) must match parent type ({$parent->type}).");
        }
    }

    private function assertNotMovingUnderDescendant(Account $a, ?string $newParentId): void
    {
        if ($newParentId === null) return;
        $current = Account::query()->find($newParentId);
        while ($current !== null) {
            if ($current->id === $a->id) {
                throw new DomainException('Cannot move an account beneath one of its own descendants.');
            }
            $current = $current->parent_id ? Account::query()->find($current->parent_id) : null;
        }
    }
}
