<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\BankAccount;
use App\Models\Tenant\BankReconSession;
use App\Models\Tenant\BankReconStatementLine;
use App\Models\Tenant\LedgerEntry;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Bank Reconciliation service.
 *
 * Lifecycle: open() -> addStatementLine()* / match() / unmatch() -> close().
 * Sessions are immutable once closed (reopen() requires a separate perm).
 *
 * Match semantics (v1, 1:1):
 *   - statement line `amount` is signed: positive = deposit, negative = withdrawal
 *   - matched ledger entry's debit/credit on the bank's GL must agree with the sign:
 *       deposit (+)  → ledger_entry.debit  > 0
 *       withdrawal(-)→ ledger_entry.credit > 0
 *   - a given ledger_entry can be matched by ONE statement line across all sessions
 *
 * Close gate: every line matched AND
 *             opening_balance + sum(statement_line.amount) == statement_ending_balance.
 *
 * On close, bank_account.last_reconciled_at + last_reconciled_balance are
 * updated, so the next session can seed its opening_balance correctly.
 */
class BankReconService
{
    public function buildQuery(): Builder
    {
        return BankReconSession::query()
            ->with(['bankAccount.glAccount', 'statementLines'])
            ->orderByDesc('end_date')
            ->orderByDesc('created_at');
    }

    /**
     * Open a new reconciliation session for a bank account.
     */
    public function open(array $data): BankReconSession
    {
        $bank = BankAccount::query()->with('glAccount')->findOrFail($data['bank_account_id']);
        if (!$bank->account_id || !$bank->glAccount) {
            throw new DomainException(
                "Bank account '{$bank->name}' has no linked GL account. " .
                'Link it to a Cash/Bank account in Chart of Accounts before opening a reconciliation.'
            );
        }

        $start = $data['start_date'];
        $end   = $data['end_date'];
        if ($start > $end) {
            throw new DomainException('Start date must be on or before end date.');
        }

        // Refuse a second open session for the same bank — keep it linear.
        $existingOpen = BankReconSession::query()
            ->where('bank_account_id', $bank->id)
            ->where('status', BankReconSession::STATUS_OPEN)
            ->exists();
        if ($existingOpen) {
            throw new DomainException(
                "Bank account '{$bank->name}' already has an open reconciliation session. " .
                'Close it before starting a new one.'
            );
        }

        $opening = isset($data['opening_balance'])
            ? round((float) $data['opening_balance'], 2)
            : round((float) ($bank->last_reconciled_balance ?? 0), 2);

        return DB::transaction(function () use ($data, $bank, $opening) {
            return BankReconSession::create([
                'session_number'           => $data['session_number'],
                'bank_account_id'          => $bank->id,
                'start_date'               => $data['start_date'],
                'end_date'                 => $data['end_date'],
                'opening_balance'          => $opening,
                'statement_ending_balance' => round((float) $data['statement_ending_balance'], 2),
                'book_ending_balance'      => round((float) ($bank->glAccount?->balance ?? 0), 2),
                'status'                   => BankReconSession::STATUS_OPEN,
                'notes'                    => $data['notes'] ?? null,
            ])->fresh(['bankAccount.glAccount', 'statementLines']);
        });
    }

    public function addStatementLine(BankReconSession $session, array $data): BankReconStatementLine
    {
        $this->requireOpen($session);

        $amount = round((float) $data['amount'], 2);
        if (abs($amount) < 0.001) {
            throw new DomainException('Statement line amount must be non-zero.');
        }

        return BankReconStatementLine::create([
            'session_id'       => $session->id,
            'statement_date'   => $data['statement_date'],
            'description'      => $data['description'],
            'reference_number' => $data['reference_number'] ?? null,
            'amount'           => $amount,
            'notes'            => $data['notes'] ?? null,
        ])->fresh(['matchedLedgerEntry']);
    }

    public function removeStatementLine(BankReconStatementLine $line): void
    {
        $this->requireOpen($line->session);
        if ($line->isMatched()) {
            throw new DomainException(
                'Unmatch the statement line before deleting it.'
            );
        }
        $line->delete();
    }

    /**
     * Match a statement line to a posted ledger_entry.
     */
    public function match(BankReconStatementLine $line, string $ledgerEntryId): BankReconStatementLine
    {
        $session = $line->session;
        $this->requireOpen($session);

        if ($line->isMatched()) {
            throw new DomainException('Statement line is already matched.');
        }

        $ledger = LedgerEntry::query()->findOrFail($ledgerEntryId);

        $bank = $session->bankAccount;
        if ($ledger->account_id !== $bank->account_id) {
            throw new DomainException(
                'Ledger entry is not on this bank account\'s GL account.'
            );
        }

        // No double-matching across sessions.
        $already = BankReconStatementLine::query()
            ->where('matched_ledger_entry_id', $ledger->id)
            ->where('id', '!=', $line->id)
            ->exists();
        if ($already) {
            throw new DomainException('Ledger entry is already matched by another statement line.');
        }

        // Sign agreement.
        $amount  = (float) $line->amount;
        $debit   = (float) $ledger->debit;
        $credit  = (float) $ledger->credit;
        if ($amount > 0 && $debit <= 0.001) {
            throw new DomainException(
                "Statement line is a deposit (+{$amount}) but the ledger entry has no debit on the bank's GL."
            );
        }
        if ($amount < 0 && $credit <= 0.001) {
            throw new DomainException(
                "Statement line is a withdrawal ({$amount}) but the ledger entry has no credit on the bank's GL."
            );
        }

        // Amount agreement (informational — we don't reject mismatches for v1
        // to allow grouping use cases, but enforce equal magnitude here).
        $movement = $amount > 0 ? $debit : $credit;
        if (abs(abs($amount) - $movement) > 0.001) {
            throw new DomainException(
                "Statement line amount magnitude ({$amount}) doesn't equal ledger entry movement ({$movement})."
            );
        }

        $line->matched_ledger_entry_id = $ledger->id;
        $line->save();

        return $line->fresh(['matchedLedgerEntry', 'session']);
    }

    public function unmatch(BankReconStatementLine $line): BankReconStatementLine
    {
        $this->requireOpen($line->session);
        if (!$line->isMatched()) {
            throw new DomainException('Statement line is not matched.');
        }
        $line->matched_ledger_entry_id = null;
        $line->save();
        return $line->fresh(['matchedLedgerEntry', 'session']);
    }

    /**
     * Close a session once all lines are matched and the balance check passes.
     * Updates the bank account's last_reconciled snapshot.
     */
    public function close(BankReconSession $session): BankReconSession
    {
        if (!$session->isOpen()) {
            throw new DomainException("Session {$session->session_number} is not open (status: {$session->status}).");
        }
        if ($session->unmatchedLinesCount() > 0) {
            throw new DomainException(
                "Session has {$session->unmatchedLinesCount()} unmatched statement line(s). " .
                'Match them all before closing.'
            );
        }
        if (!$session->balanceMatches()) {
            $expected = round((float) $session->opening_balance + $session->statementLinesTotal(), 2);
            throw new DomainException(
                "Balance check failed: opening ({$session->opening_balance}) + line sum ({$session->statementLinesTotal()}) = {$expected}, " .
                "but statement_ending_balance is {$session->statement_ending_balance}."
            );
        }

        return DB::transaction(function () use ($session) {
            $session->forceFill([
                'status'              => BankReconSession::STATUS_CLOSED,
                'closed_at'           => now(),
                'closed_by'           => Auth::id(),
                'book_ending_balance' => round((float) ($session->bankAccount->glAccount?->balance ?? 0), 2),
            ])->save();

            $bank = $session->bankAccount;
            $bank->forceFill([
                'last_reconciled_at'      => $session->end_date,
                'last_reconciled_balance' => $session->statement_ending_balance,
            ])->save();

            return $session->fresh(['bankAccount.glAccount', 'statementLines.matchedLedgerEntry']);
        });
    }

    /**
     * Reopen a closed session. Requires the dedicated reopen perm
     * (gated by policy on the controller). Doesn't touch the bank's
     * last_reconciled snapshot — that stays at the close timestamp.
     */
    public function reopen(BankReconSession $session): BankReconSession
    {
        if (!$session->isClosed()) {
            throw new DomainException("Session {$session->session_number} is not closed (status: {$session->status}).");
        }
        $session->forceFill([
            'status'    => BankReconSession::STATUS_OPEN,
            'closed_at' => null,
            'closed_by' => null,
        ])->save();
        return $session->fresh(['bankAccount.glAccount', 'statementLines.matchedLedgerEntry']);
    }

    /**
     * Ledger entries on the session's bank GL within the session date range.
     * Used by the UI matcher to surface candidates alongside statement lines.
     */
    public function periodLedgerEntriesQuery(BankReconSession $session): Builder
    {
        $bank = $session->bankAccount;
        return LedgerEntry::query()
            ->with(['journalEntry'])
            ->where('account_id', $bank->account_id)
            ->whereHas('journalEntry', fn ($q) => $q
                ->whereDate('entry_date', '>=', $session->start_date)
                ->whereDate('entry_date', '<=', $session->end_date)
                ->where('status', '!=', 'reversed'));
    }

    // ----- helpers ----------------------------------------------------------

    private function requireOpen(BankReconSession $session): void
    {
        if (!$session->isOpen()) {
            throw new DomainException(
                "Session {$session->session_number} is closed — cannot modify lines or matches."
            );
        }
    }
}
