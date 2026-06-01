<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\BankAccount;
use App\Models\Tenant\Bill;
use App\Models\Tenant\BillPayment;
use App\Models\Tenant\BillPaymentApplication;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BillPaymentService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function buildQuery(): Builder
    {
        return BillPayment::query()
            ->with(['supplier', 'bankAccount.glAccount', 'applications.bill'])
            ->orderByDesc('paid_on')
            ->orderByDesc('created_at');
    }

    /**
     * Record a bill payment that settles one or many bills for a single
     * vendor. Posts a balanced JE the moment it's saved — payments do not
     * have a draft state.
     *
     * Validation:
     *   - Bank must have a linked GL account (otherwise we can't credit Cash).
     *   - Every applied bill must belong to the payment's supplier.
     *   - Every applied bill must be open (approved or partially_paid).
     *   - Each applied_amount must be > 0 and <= bill.outstanding.
     *   - Sum of applied_amounts must equal the payment header amount
     *     (within 0.001 tolerance).
     */
    public function record(array $data): BillPayment
    {
        $bank = BankAccount::query()->with('glAccount')->findOrFail($data['bank_account_id']);
        if (!$bank->account_id || !$bank->glAccount) {
            throw new DomainException(
                "Bank account '{$bank->name}' has no linked GL account. " .
                'Link it to a Cash/Bank account in Chart of Accounts before posting payments.'
            );
        }

        $applications = $this->normalizeApplications($data['applications'] ?? []);
        $billIds      = collect($applications)->pluck('bill_id')->all();
        $bills        = Bill::query()->whereIn('id', $billIds)->get()->keyBy('id');

        $this->assertBillsBelongToSupplier($bills, $data['supplier_id']);
        $this->assertBillsOpen($bills);
        $this->assertAppliedAmountsFitBills($applications, $bills);
        $this->assertSumMatchesHeader($applications, (float) $data['amount']);

        return DB::transaction(function () use ($data, $bank, $bills, $applications) {
            $payment = BillPayment::create([
                'payment_number'    => $data['payment_number'],
                'bank_account_id'   => $bank->id,
                'supplier_id'       => $data['supplier_id'],
                'paid_on'           => $data['paid_on'],
                'amount'            => $data['amount'],
                'currency'          => $data['currency'] ?? $bank->currency ?? 'USD',
                'payment_method'    => $data['payment_method'] ?? null,
                'reference_number'  => $data['reference_number'] ?? null,
                'notes'             => $data['notes'] ?? null,
                'status'            => BillPayment::STATUS_POSTED,
            ]);

            // Build the journal lines: one DR per bill's AP account + one CR
            // to the bank's GL account for the total.
            $journalLines = [];
            foreach ($applications as $app) {
                /** @var Bill $bill */
                $bill = $bills[$app['bill_id']];
                if (!$bill->payable_account_id) {
                    throw new DomainException(
                        "Bill {$bill->bill_number} has no AP account set — cannot post payment."
                    );
                }
                $journalLines[] = [
                    'account_id' => $bill->payable_account_id,
                    'debit'      => $app['applied_amount'],
                    'credit'     => 0.0,
                ];
            }
            $journalLines[] = [
                'account_id' => $bank->account_id,
                'debit'      => 0.0,
                'credit'     => round((float) $data['amount'], 2),
            ];

            $journal = $this->accounting->postEntry([
                'reference_number' => "PAY-{$data['payment_number']}",
                'description'      => "Payment {$data['payment_number']} to " . ($payment->supplier?->name ?? 'vendor'),
                'entry_date'       => $data['paid_on'],
                'lines'            => $journalLines,
            ]);

            $payment->forceFill(['journal_entry_id' => $journal->id])->save();

            // Persist applications and roll each bill's paid_amount + status.
            foreach ($applications as $app) {
                BillPaymentApplication::create([
                    'bill_payment_id' => $payment->id,
                    'bill_id'         => $app['bill_id'],
                    'applied_amount'  => $app['applied_amount'],
                ]);

                /** @var Bill $bill */
                $bill = $bills[$app['bill_id']];
                $this->applyPaymentToBill($bill, (float) $app['applied_amount']);
            }

            return $payment->fresh(['supplier', 'bankAccount.glAccount', 'applications.bill', 'journalEntry']);
        });
    }

    /**
     * Cancel a posted payment: reverses the JE, decrements paid_amount on
     * each linked bill, and downgrades bill status from paid back to
     * partially_paid (or partially_paid back to approved) as appropriate.
     */
    public function cancel(BillPayment $payment): BillPayment
    {
        if (!$payment->isCancellable()) {
            throw new DomainException("Payment {$payment->payment_number} cannot be cancelled (status: {$payment->status}).");
        }

        return DB::transaction(function () use ($payment) {
            $payment->load(['applications.bill', 'journalEntry']);

            // Reverse the JE first so audit history is preserved.
            if ($payment->journalEntry) {
                $reversal = $this->accounting->reverseEntry(
                    $payment->journalEntry,
                    "PAY-{$payment->payment_number}-CANCEL",
                    "Cancellation of payment {$payment->payment_number}",
                );
                $payment->reversal_journal_entry_id = $reversal->id;
            }

            // Roll back each bill's paid_amount + status.
            foreach ($payment->applications as $app) {
                $bill = $app->bill;
                if ($bill) {
                    $this->unapplyPaymentFromBill($bill, (float) $app->applied_amount);
                }
            }

            $payment->status = BillPayment::STATUS_CANCELLED;
            $payment->save();

            return $payment->fresh(['supplier', 'bankAccount.glAccount', 'applications.bill', 'journalEntry', 'reversalJournalEntry']);
        });
    }

    // ----- Helpers ----------------------------------------------------------

    private function normalizeApplications(array $raw): array
    {
        $out = [];
        foreach ($raw as $a) {
            if (empty($a['bill_id'])) continue;
            $amt = round((float) ($a['applied_amount'] ?? 0), 2);
            if ($amt <= 0) continue;
            $out[] = ['bill_id' => $a['bill_id'], 'applied_amount' => $amt];
        }
        if (empty($out)) {
            throw new DomainException('A payment must apply to at least one bill.');
        }
        return $out;
    }

    private function assertBillsBelongToSupplier($bills, string $supplierId): void
    {
        foreach ($bills as $bill) {
            if ($bill->supplier_id !== $supplierId) {
                throw new DomainException(
                    "Bill {$bill->bill_number} belongs to a different vendor. " .
                    'A single payment can only settle bills for one vendor.'
                );
            }
        }
    }

    private function assertBillsOpen($bills): void
    {
        foreach ($bills as $bill) {
            if (!in_array($bill->status, Bill::OPEN_STATUSES, true)) {
                throw new DomainException(
                    "Bill {$bill->bill_number} is in status '{$bill->status}' and cannot accept payment."
                );
            }
        }
    }

    private function assertAppliedAmountsFitBills(array $applications, $bills): void
    {
        foreach ($applications as $app) {
            $bill = $bills[$app['bill_id']] ?? null;
            if (!$bill) {
                throw new DomainException("Bill {$app['bill_id']} not found.");
            }
            $outstanding = $bill->outstandingAmount();
            if ($app['applied_amount'] > $outstanding + 0.001) {
                throw new DomainException(
                    "Applied amount {$app['applied_amount']} exceeds outstanding {$outstanding} on bill {$bill->bill_number}."
                );
            }
        }
    }

    private function assertSumMatchesHeader(array $applications, float $headerAmount): void
    {
        $sum = round(array_sum(array_column($applications, 'applied_amount')), 2);
        if (abs($sum - round($headerAmount, 2)) > 0.001) {
            throw new DomainException(
                "Sum of applied amounts ({$sum}) does not equal payment amount ({$headerAmount})."
            );
        }
    }

    private function applyPaymentToBill(Bill $bill, float $amount): void
    {
        $newPaid = round((float) $bill->paid_amount + $amount, 2);
        $total   = round((float) $bill->total, 2);
        $status  = $newPaid + 0.001 >= $total
            ? Bill::STATUS_PAID
            : Bill::STATUS_PARTIALLY_PAID;

        $bill->forceFill([
            'paid_amount' => $newPaid,
            'status'      => $status,
        ])->save();
    }

    private function unapplyPaymentFromBill(Bill $bill, float $amount): void
    {
        $newPaid = max(0, round((float) $bill->paid_amount - $amount, 2));
        $status  = $bill->status;

        // Don't touch cancelled bills — their lifecycle is independent.
        if ($status === Bill::STATUS_CANCELLED) {
            $bill->forceFill(['paid_amount' => $newPaid])->save();
            return;
        }

        $status = $newPaid <= 0.001
            ? Bill::STATUS_APPROVED
            : Bill::STATUS_PARTIALLY_PAID;

        $bill->forceFill([
            'paid_amount' => $newPaid,
            'status'      => $status,
        ])->save();
    }
}
