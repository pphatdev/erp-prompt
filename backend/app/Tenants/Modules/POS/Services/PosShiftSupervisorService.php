<?php

declare(strict_types=1);

namespace App\Tenants\Modules\POS\Services;

use App\Models\Tenant\Account;
use App\Models\Tenant\PosShift;
use App\Tenants\Modules\FMS\Services\AccountingService;
use App\Tenants\Modules\Settings\Services\SettingService;
use DomainException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Supervisor (`pos.shift.approve`) reconciles a `variance_pending` shift,
 * posting a balanced Cash Over/Short journal and flipping the shift to
 * `reconciled`.
 *
 * Direction:
 *   variance > 0 (over - drawer has more than the books expect):
 *     DR Cash               variance
 *     CR Cash Over/Short    variance
 *
 *   variance < 0 (short - drawer has less than the books expect):
 *     DR Cash Over/Short    |variance|
 *     CR Cash               |variance|
 *
 * Cash account resolution mirrors PosOrderService::checkout:
 *   1. terminal.petty_cash_account_id when set,
 *   2. else `pos.cash_account_code` setting (default 1100).
 *
 * Cash Over/Short account: `pos.cash_over_short_account_code` (default 5900).
 *
 * Idempotent on a re-call against an already-reconciled shift.
 */
class PosShiftSupervisorService
{
    private const DEFAULT_CASH_CODE = '1100';
    private const DEFAULT_COS_CODE  = '5900';

    public function __construct(
        private readonly AccountingService $accounting,
        private readonly SettingService $settings,
    ) {
    }

    public function reconcileVariance(PosShift $shift, ?string $notes = null): PosShift
    {
        if ($shift->status === PosShift::STATUS_RECONCILED) {
            return $shift;
        }
        if ($shift->status !== PosShift::STATUS_VARIANCE_PENDING) {
            throw new DomainException(
                "Only variance_pending shifts can be reconciled (current: '{$shift->status}')."
            );
        }
        if ($shift->variance === null || (float) $shift->variance === 0.0) {
            // Defensive: a shift with zero variance shouldn't be in
            // variance_pending, but treat it as a clean reconcile.
            return DB::transaction(function () use ($shift, $notes) {
                $shift->update([
                    'status' => PosShift::STATUS_RECONCILED,
                    'reconciled_by' => Auth::id(),
                    'reconciled_at' => now(),
                    'notes' => $this->mergeNotes($shift->notes, $notes),
                ]);
                return $shift->fresh();
            });
        }

        return DB::transaction(function () use ($shift, $notes) {
            $shift->loadMissing('terminal');
            $journal = $this->postCashOverShortJournal($shift);

            $shift->update([
                'status' => PosShift::STATUS_RECONCILED,
                'reconciled_by' => Auth::id(),
                'reconciled_at' => now(),
                'variance_journal_entry_id' => $journal->id,
                'notes' => $this->mergeNotes($shift->notes, $notes),
            ]);

            return $shift->fresh(['terminal', 'cashier', 'varianceJournalEntry']);
        });
    }

    private function postCashOverShortJournal(PosShift $shift)
    {
        $variance = round((float) $shift->variance, 2);
        $absVariance = abs($variance);

        $cashCode = (string) $this->settings->get('pos.cash_account_code', self::DEFAULT_CASH_CODE);
        $cosCode  = (string) $this->settings->get('pos.cash_over_short_account_code', self::DEFAULT_COS_CODE);

        $cashAccount = $shift->terminal?->petty_cash_account_id
            ? Account::find($shift->terminal->petty_cash_account_id) ?? $this->requireAccount($cashCode, 'POS cash drawer')
            : $this->requireAccount($cashCode, 'POS cash drawer');
        $cosAccount = $this->requireAccount($cosCode, 'Cash Over/Short');

        if ($variance > 0) {
            // OVER: drawer has extra cash.
            $lines = [
                ['account_id' => $cashAccount->id, 'debit' => $absVariance, 'credit' => 0],
                ['account_id' => $cosAccount->id,  'debit' => 0,            'credit' => $absVariance],
            ];
            $direction = 'OVER';
        } else {
            // SHORT: drawer is missing cash.
            $lines = [
                ['account_id' => $cosAccount->id,  'debit' => $absVariance, 'credit' => 0],
                ['account_id' => $cashAccount->id, 'debit' => 0,            'credit' => $absVariance],
            ];
            $direction = 'SHORT';
        }

        return $this->accounting->postEntry([
            'reference_number' => 'POS-VAR-' . $shift->id,
            'description' => "POS shift variance reconcile ({$direction}) {$absVariance} - shift {$shift->id}",
            'entry_date' => now()->toDateString(),
            'lines' => $lines,
        ]);
    }

    private function requireAccount(string $code, string $label): Account
    {
        $account = Account::where('code', $code)->first();
        if ($account) {
            return $account;
        }

        // Auto-provision the Cash Over/Short account on first use so existing
        // tenants seeded before the 5900 row was canonical don't dead-end on
        // the supervisor reconcile flow. Other account codes still throw -
        // they are configurable via pos.*_account_code so an admin override
        // is the resolution path, not auto-mint.
        if ($code === self::DEFAULT_COS_CODE) {
            \Illuminate\Support\Facades\Log::info(
                "Auto-provisioning Cash Over/Short account ({$code}) for POS variance reconcile."
            );
            return Account::create([
                'code' => $code,
                'name' => 'Cash Over/Short',
                'type' => 'expense',
                'balance' => 0,
            ]);
        }

        throw new DomainException(
            "Chart of Accounts is missing the '{$label}' account (code: {$code}). " .
            'Seed it or set the matching pos.*_account_code setting.'
        );
    }

    private function mergeNotes(?string $existing, ?string $incoming): ?string
    {
        if (!$incoming) {
            return $existing;
        }
        return $existing
            ? trim($existing) . "\n\nReconcile: " . trim($incoming)
            : 'Reconcile: ' . trim($incoming);
    }
}
