<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\FiscalPeriod;
use App\Models\Tenant\LedgerEntry;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Fiscal period closing service.
 *
 * Lifecycle managed here: open -> locked (close), locked -> open (reopen).
 *
 * close():
 *   1. Compute net movement for every Revenue and Expense account within
 *      the period (excluding reversed JEs).
 *   2. Build a balanced closing journal entry:
 *        DR revenue (= sum of credits - debits, the period's revenue total)
 *        CR expense (= sum of debits  - credits, the period's expense total)
 *        CR Retained Earnings (= revenue - expense) when profit
 *        DR Retained Earnings (= |revenue - expense|) when loss
 *   3. Post through AccountingService::postEntry (which runs the balance
 *      check + lock check). The period is still open at this point so the
 *      lock check passes.
 *   4. Flip the period to locked and store the JE id for traceability.
 *
 * reopen(): clears the lock + closing JE pointer. Leaves the closing JE in
 *           the ledger intact - reversing it is a separate, deliberate step
 *           (use AccountingService::reverseEntry).
 *
 * preview(): same computation without posting. Used by the UI dry-run.
 */
class PeriodClosingService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function buildQuery(): Builder
    {
        return FiscalPeriod::query()
            ->with(['retainedEarningsAccount', 'closingJournalEntry'])
            ->orderByDesc('end_date');
    }

    public function create(array $data): FiscalPeriod
    {
        if ($data['start_date'] > $data['end_date']) {
            throw new DomainException('Start date must be on or before end date.');
        }

        $overlap = FiscalPeriod::query()
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date',   '>=', $data['start_date'])
            ->exists();
        if ($overlap) {
            throw new DomainException('Period overlaps an existing fiscal period.');
        }

        return FiscalPeriod::create([
            'period_number' => $data['period_number'],
            'name'          => $data['name'],
            'start_date'    => $data['start_date'],
            'end_date'      => $data['end_date'],
            'notes'         => $data['notes'] ?? null,
            'status'        => FiscalPeriod::STATUS_OPEN,
        ]);
    }

    public function update(FiscalPeriod $period, array $data): FiscalPeriod
    {
        if ($period->isLocked()) {
            throw new DomainException(
                "Period {$period->period_number} is locked and cannot be edited."
            );
        }
        $period->fill(array_filter([
            'name'       => $data['name']       ?? null,
            'start_date' => $data['start_date'] ?? null,
            'end_date'   => $data['end_date']   ?? null,
            'notes'      => $data['notes']      ?? null,
        ], fn ($v) => $v !== null))->save();
        return $period;
    }

    public function delete(FiscalPeriod $period): void
    {
        if ($period->isLocked()) {
            throw new DomainException(
                "Period {$period->period_number} is locked and cannot be deleted."
            );
        }
        $period->delete();
    }

    /**
     * Return the planned closing-entry preview WITHOUT posting it.
     * Used by the UI to show the user what will be posted before confirming.
     */
    public function preview(FiscalPeriod $period, string $retainedEarningsAccountId): array
    {
        if (!$period->isOpen()) {
            throw new DomainException(
                "Period {$period->period_number} is not open (status: {$period->status})."
            );
        }
        $re = $this->requireEquityAccount($retainedEarningsAccountId);
        return $this->buildClosingLines($period, $re);
    }

    public function close(FiscalPeriod $period, array $data): FiscalPeriod
    {
        if (!$period->isOpen()) {
            throw new DomainException(
                "Period {$period->period_number} is not open (status: {$period->status})."
            );
        }
        $re = $this->requireEquityAccount($data['retained_earnings_account_id']);

        $plan = $this->buildClosingLines($period, $re);

        if (empty($plan['lines'])) {
            // Nothing to close (no revenue or expense movement). Still lock the period.
            return DB::transaction(function () use ($period, $re) {
                $period->forceFill([
                    'status'                        => FiscalPeriod::STATUS_LOCKED,
                    'locked_at'                     => now(),
                    'locked_by'                     => Auth::id(),
                    'retained_earnings_account_id'  => $re->id,
                ])->save();
                return $period->fresh(['retainedEarningsAccount', 'closingJournalEntry']);
            });
        }

        return DB::transaction(function () use ($period, $re, $plan, $data) {
            $journal = $this->accounting->postEntry([
                'reference_number' => "CLOSE-{$period->period_number}",
                'description'      => "Closing entry for period {$period->period_number} ({$period->name})",
                'entry_date'       => optional($period->end_date)->toDateString(),
                'lines'            => $plan['lines'],
            ]);

            $period->forceFill([
                'status'                        => FiscalPeriod::STATUS_LOCKED,
                'locked_at'                     => now(),
                'locked_by'                     => Auth::id(),
                'retained_earnings_account_id'  => $re->id,
                'closing_journal_entry_id'      => $journal->id,
                'notes'                         => $data['notes'] ?? $period->notes,
            ])->save();

            return $period->fresh(['retainedEarningsAccount', 'closingJournalEntry']);
        });
    }

    public function reopen(FiscalPeriod $period): FiscalPeriod
    {
        if (!$period->isLocked()) {
            throw new DomainException(
                "Period {$period->period_number} is not locked (status: {$period->status})."
            );
        }
        $period->forceFill([
            'status'                   => FiscalPeriod::STATUS_OPEN,
            'locked_at'                => null,
            'locked_by'                => null,
            'closing_journal_entry_id' => null,
        ])->save();
        return $period->fresh(['retainedEarningsAccount', 'closingJournalEntry']);
    }

    // ----- helpers ----------------------------------------------------------

    private function requireEquityAccount(string $accountId): Account
    {
        $account = Account::query()->findOrFail($accountId);
        if ($account->type !== 'equity') {
            throw new DomainException(
                "Retained Earnings account '{$account->code} / {$account->name}' must be type 'equity' (got '{$account->type}')."
            );
        }
        return $account;
    }

    /**
     * Build the planned closing-entry lines + a totals summary. Returns:
     *   [
     *     'lines'      => array of postEntry-compatible line arrays,
     *     'revenue'    => array of [account, debit] (one per touched revenue account),
     *     'expense'    => array of [account, credit] (one per touched expense account),
     *     'net'        => float (positive = profit, negative = loss),
     *     'retainedDr' => float (DR amount on RE if loss, else 0),
     *     'retainedCr' => float (CR amount on RE if profit, else 0),
     *   ]
     */
    private function buildClosingLines(FiscalPeriod $period, Account $re): array
    {
        $rows = LedgerEntry::query()
            ->selectRaw('ledger_entries.account_id, COALESCE(SUM(ledger_entries.debit), 0) AS d, COALESCE(SUM(ledger_entries.credit), 0) AS c')
            ->join('journal_entries', 'journal_entries.id', '=', 'ledger_entries.journal_entry_id')
            ->whereDate('journal_entries.entry_date', '>=', $period->start_date)
            ->whereDate('journal_entries.entry_date', '<=', $period->end_date)
            ->where('journal_entries.status', '!=', 'reversed')
            ->groupBy('ledger_entries.account_id')
            ->get();

        $accountIds = $rows->pluck('account_id')->all();
        $accounts = Account::query()->whereIn('id', $accountIds)->get()->keyBy('id');

        $revenueClosures = [];  // DR each revenue
        $expenseClosures = []; // CR each expense
        $revenueTotal    = 0.0;
        $expenseTotal    = 0.0;

        foreach ($rows as $row) {
            $account = $accounts[$row->account_id] ?? null;
            if (!$account) continue;
            $d = (float) $row->d;
            $c = (float) $row->c;

            if ($account->type === 'revenue') {
                $movement = round($c - $d, 2); // natural credit balance: revenue earned
                if ($movement > 0.001) {
                    $revenueClosures[] = ['account' => $account, 'amount' => $movement];
                    $revenueTotal += $movement;
                }
            } elseif ($account->type === 'expense') {
                $movement = round($d - $c, 2); // natural debit balance: expense incurred
                if ($movement > 0.001) {
                    $expenseClosures[] = ['account' => $account, 'amount' => $movement];
                    $expenseTotal += $movement;
                }
            }
        }

        $net = round($revenueTotal - $expenseTotal, 2);

        $lines = [];
        foreach ($revenueClosures as $c) {
            $lines[] = ['account_id' => $c['account']->id, 'debit' => $c['amount'], 'credit' => 0.0];
        }
        foreach ($expenseClosures as $c) {
            $lines[] = ['account_id' => $c['account']->id, 'debit' => 0.0, 'credit' => $c['amount']];
        }

        $retainedDr = 0.0;
        $retainedCr = 0.0;
        if ($net > 0.001) {
            // Profit: CR Retained Earnings.
            $retainedCr = $net;
            $lines[] = ['account_id' => $re->id, 'debit' => 0.0, 'credit' => $net];
        } elseif ($net < -0.001) {
            // Loss: DR Retained Earnings.
            $retainedDr = abs($net);
            $lines[] = ['account_id' => $re->id, 'debit' => abs($net), 'credit' => 0.0];
        }

        return [
            'lines'      => $lines,
            'revenue'    => $revenueClosures,
            'expense'    => $expenseClosures,
            'net'        => $net,
            'retainedDr' => $retainedDr,
            'retainedCr' => $retainedCr,
        ];
    }
}
