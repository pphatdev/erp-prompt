<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\Budget;
use App\Models\Tenant\BudgetLine;
use App\Models\Tenant\LedgerEntry;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Budget service. Drafts are mutable, active and archived are not.
 *
 * Variance is computed at read time. For each line:
 *   actual_movement = sum(debit) +/- sum(credit) on the line's account_id
 *                     between budget.start_date and budget.end_date,
 *                     excluding reversed journals.
 *
 * Sign convention follows account natural balance:
 *   asset, expense                -> actual = debits - credits
 *   liability, equity, revenue    -> actual = credits - debits
 *
 * Variance bucket:
 *   |variance_pct| <  5    -> green
 *   |variance_pct| < 15    -> yellow
 *   otherwise              -> red
 *   (when expected is zero, any non-zero actual is red)
 */
class BudgetService
{
    private const NATURAL_DEBIT = ['asset', 'expense'];

    private const BUCKET_GREEN_THRESHOLD  = 5.0;
    private const BUCKET_YELLOW_THRESHOLD = 15.0;

    public function buildQuery(): Builder
    {
        return Budget::query()
            ->withCount('lines')
            ->orderByDesc('start_date')
            ->orderByDesc('created_at');
    }

    public function create(array $data): Budget
    {
        if (!empty($data['start_date']) && !empty($data['end_date'])
            && $data['start_date'] > $data['end_date']) {
            throw new DomainException('Start date must be on or before end date.');
        }

        return DB::transaction(function () use ($data) {
            $budget = Budget::create([
                'budget_number' => $data['budget_number'],
                'name'          => $data['name'],
                'start_date'    => $data['start_date'],
                'end_date'      => $data['end_date'],
                'notes'         => $data['notes'] ?? null,
                'status'        => Budget::STATUS_DRAFT,
            ]);

            foreach ($data['lines'] ?? [] as $line) {
                $this->addLine($budget, $line);
            }

            return $budget->fresh('lines.account');
        });
    }

    public function update(Budget $budget, array $data): Budget
    {
        if (!$budget->isEditable()) {
            throw new DomainException(
                "Budget {$budget->budget_number} is not editable (status: {$budget->status})."
            );
        }
        if (!empty($data['start_date']) && !empty($data['end_date'])
            && $data['start_date'] > $data['end_date']) {
            throw new DomainException('Start date must be on or before end date.');
        }

        $budget->fill(array_filter([
            'name'       => $data['name']       ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date'   => $data['end_date']   ?? null,
            'notes'      => $data['notes']      ?? null,
        ], fn ($v) => $v !== null))->save();

        return $budget->fresh('lines.account');
    }

    public function delete(Budget $budget): void
    {
        if (!$budget->isEditable()) {
            throw new DomainException(
                "Only draft budgets can be deleted. {$budget->budget_number} is {$budget->status}."
            );
        }
        $budget->delete();
    }

    public function addLine(Budget $budget, array $data): BudgetLine
    {
        if (!$budget->isEditable()) {
            throw new DomainException(
                "Lines can only be added to draft budgets. {$budget->budget_number} is {$budget->status}."
            );
        }
        Account::query()->findOrFail($data['account_id']);

        $existing = BudgetLine::query()
            ->where('budget_id', $budget->id)
            ->where('account_id', $data['account_id'])
            ->first();
        if ($existing) {
            throw new DomainException('Budget already has a line for this account.');
        }

        $amount = round((float) $data['expected_amount'], 2);
        if ($amount < 0) {
            throw new DomainException('Expected amount must be non-negative.');
        }

        return BudgetLine::create([
            'budget_id'       => $budget->id,
            'account_id'      => $data['account_id'],
            'expected_amount' => $amount,
            'notes'           => $data['notes'] ?? null,
        ]);
    }

    public function updateLine(BudgetLine $line, array $data): BudgetLine
    {
        if (!$line->budget->isEditable()) {
            throw new DomainException(
                "Lines can only be edited on draft budgets. Parent budget is {$line->budget->status}."
            );
        }
        if (array_key_exists('expected_amount', $data)) {
            $amount = round((float) $data['expected_amount'], 2);
            if ($amount < 0) {
                throw new DomainException('Expected amount must be non-negative.');
            }
            $line->expected_amount = $amount;
        }
        if (array_key_exists('notes', $data)) {
            $line->notes = $data['notes'];
        }
        $line->save();
        return $line->fresh('account');
    }

    public function removeLine(BudgetLine $line): void
    {
        if (!$line->budget->isEditable()) {
            throw new DomainException(
                "Lines can only be removed from draft budgets. Parent is {$line->budget->status}."
            );
        }
        $line->delete();
    }

    public function activate(Budget $budget): Budget
    {
        if (!$budget->isActivatable()) {
            if ($budget->isActive()) {
                throw new DomainException("Budget {$budget->budget_number} is already active.");
            }
            if (!$budget->isDraft()) {
                throw new DomainException(
                    "Only draft budgets can be activated. {$budget->budget_number} is {$budget->status}."
                );
            }
            throw new DomainException('A budget must have at least one line before it can be activated.');
        }
        $budget->status = Budget::STATUS_ACTIVE;
        $budget->save();
        return $budget->fresh('lines.account');
    }

    public function archive(Budget $budget): Budget
    {
        if (!$budget->isArchivable()) {
            throw new DomainException(
                "Only active budgets can be archived. {$budget->budget_number} is {$budget->status}."
            );
        }
        $budget->status = Budget::STATUS_ARCHIVED;
        $budget->save();
        return $budget->fresh('lines.account');
    }

    /**
     * Compute variance per line for the budget. Returns the budget with lines
     * loaded plus a parallel array keyed by line id.
     *
     * Output per line:
     *   expected   (float)
     *   actual     (float, signed by natural balance)
     *   variance   (float, actual - expected)
     *   variancePct(float|null) null when expected == 0
     *   bucket     ('green' | 'yellow' | 'red')
     */
    public function computeVariance(Budget $budget): array
    {
        $budget->loadMissing('lines.account');

        $accountIds = $budget->lines->pluck('account_id')->all();
        if (empty($accountIds)) {
            return ['budget' => $budget, 'variance' => []];
        }

        $rows = LedgerEntry::query()
            ->selectRaw('account_id, COALESCE(SUM(debit), 0) AS d, COALESCE(SUM(credit), 0) AS c')
            ->whereIn('account_id', $accountIds)
            ->whereHas('journalEntry', fn ($q) => $q
                ->whereDate('entry_date', '>=', $budget->start_date)
                ->whereDate('entry_date', '<=', $budget->end_date)
                ->where('status', '!=', 'reversed'))
            ->groupBy('account_id')
            ->get();

        $sums = [];
        foreach ($rows as $r) {
            $sums[$r->account_id] = ['d' => (float) $r->d, 'c' => (float) $r->c];
        }

        $variance = [];
        foreach ($budget->lines as $line) {
            $account = $line->account;
            $sum = $sums[$line->account_id] ?? ['d' => 0.0, 'c' => 0.0];
            $actual = $this->naturalMovement($account?->type, $sum['d'], $sum['c']);
            $expected = (float) $line->expected_amount;
            $delta = round($actual - $expected, 2);

            $pct = $expected > 0.001
                ? round(($delta / $expected) * 100.0, 2)
                : null;

            $variance[$line->id] = [
                'expected'    => round($expected, 2),
                'actual'      => round($actual, 2),
                'variance'    => $delta,
                'variancePct' => $pct,
                'bucket'      => $this->bucketFor($expected, $delta, $pct),
            ];
        }

        return ['budget' => $budget, 'variance' => $variance];
    }

    private function naturalMovement(?string $type, float $debit, float $credit): float
    {
        if ($type !== null && in_array($type, self::NATURAL_DEBIT, true)) {
            return round($debit - $credit, 2);
        }
        return round($credit - $debit, 2);
    }

    private function bucketFor(float $expected, float $delta, ?float $pct): string
    {
        if ($expected <= 0.001) {
            return abs($delta) < 0.001 ? 'green' : 'red';
        }
        $absPct = abs($pct ?? 0.0);
        if ($absPct < self::BUCKET_GREEN_THRESHOLD)  return 'green';
        if ($absPct < self::BUCKET_YELLOW_THRESHOLD) return 'yellow';
        return 'red';
    }
}
