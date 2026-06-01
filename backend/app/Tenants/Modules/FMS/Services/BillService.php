<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\Bill;
use App\Models\Tenant\BillLine;
use App\Models\Tenant\Supplier;
use DomainException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BillService
{
    public function __construct(private readonly AccountingService $accounting) {}

    public function buildQuery(): Builder
    {
        return Bill::query()
            ->with(['supplier', 'lines.account', 'payableAccount'])
            ->orderByDesc('issue_date')
            ->orderByDesc('created_at');
    }

    /**
     * Create a draft Bill with its lines. Lines must reference real accounts;
     * totals are recomputed server-side from the lines so client values can't
     * drift the math.
     */
    public function create(array $data): Bill
    {
        $this->assertSupplierIsVendor($data['supplier_id']);
        $lines = $this->normalizeLines($data['lines'] ?? []);

        return DB::transaction(function () use ($data, $lines) {
            $bill = Bill::create([
                'bill_number'              => $data['bill_number'],
                'supplier_invoice_number'  => $data['supplier_invoice_number'] ?? null,
                'supplier_id'              => $data['supplier_id'],
                'po_id'                    => $data['po_id'] ?? null,
                'issue_date'               => $data['issue_date'],
                'due_date'                 => $data['due_date'] ?? null,
                'currency'                 => $data['currency'] ?? 'USD',
                'tax_amount'               => $data['tax_amount'] ?? 0,
                'payable_account_id'       => $data['payable_account_id'] ?? $this->resolveDefaultPayable($data['supplier_id']),
                'notes'                    => $data['notes'] ?? null,
                'status'                   => Bill::STATUS_DRAFT,
            ]);

            $this->replaceLines($bill, $lines);
            $this->recomputeTotals($bill);

            return $bill->fresh(['supplier', 'lines.account', 'payableAccount']);
        });
    }

    public function update(Bill $bill, array $data): Bill
    {
        if (!$bill->isEditable()) {
            throw new DomainException(
                "Bill {$bill->bill_number} cannot be edited in status '{$bill->status}'. " .
                'Only draft bills are editable. To correct an approved bill, cancel it first (posts a reversal).'
            );
        }

        if (isset($data['supplier_id']) && $data['supplier_id'] !== $bill->supplier_id) {
            $this->assertSupplierIsVendor($data['supplier_id']);
        }

        $lines = array_key_exists('lines', $data) ? $this->normalizeLines($data['lines']) : null;

        return DB::transaction(function () use ($bill, $data, $lines) {
            $bill->update(collect($data)->except('lines')->all());

            if ($lines !== null) {
                $this->replaceLines($bill, $lines);
            }
            $this->recomputeTotals($bill);

            return $bill->fresh(['supplier', 'lines.account', 'payableAccount']);
        });
    }

    /**
     * Approve a draft bill: validates it has ≥1 line and an AP account,
     * posts a balanced JE (DR each line account, CR AP), saves the JE id,
     * and flips status=approved. Idempotency-safe via the editable guard.
     */
    public function approve(Bill $bill): Bill
    {
        if (!$bill->isPostable()) {
            throw new DomainException("Bill {$bill->bill_number} cannot be approved from status '{$bill->status}'.");
        }
        $bill->load('lines');

        if ($bill->lines->isEmpty()) {
            throw new DomainException("Bill {$bill->bill_number} has no lines to post.");
        }
        if (!$bill->payable_account_id) {
            throw new DomainException(
                "Bill {$bill->bill_number} has no AP account set. " .
                "Pick one on the bill, or set the vendor's default Payable Account."
            );
        }

        return DB::transaction(function () use ($bill) {
            // DR each expense/asset line; single CR to the AP control account.
            $lines = [];
            $total = 0.0;
            foreach ($bill->lines as $l) {
                $amount = (float) $l->line_total;
                $lines[] = [
                    'account_id' => $l->account_id,
                    'debit'      => $amount,
                    'credit'     => 0.0,
                ];
                $total += $amount;
            }
            // Tax line — booked to the AP account as part of the credit
            // (simplest model; a future tax-account split is a separate task).
            $total += (float) $bill->tax_amount;
            $lines[] = [
                'account_id' => $bill->payable_account_id,
                'debit'      => 0.0,
                'credit'     => round($total, 2),
            ];

            $journal = $this->accounting->postEntry([
                'reference_number' => "BILL-{$bill->bill_number}",
                'description'      => "Bill {$bill->bill_number} from " . ($bill->supplier?->name ?? 'supplier'),
                'entry_date'       => $bill->issue_date?->toDateString() ?? now()->toDateString(),
                'lines'            => $lines,
            ]);

            $bill->forceFill([
                'status'           => Bill::STATUS_APPROVED,
                'journal_entry_id' => $journal->id,
            ])->save();

            return $bill->fresh(['supplier', 'lines.account', 'payableAccount', 'journalEntry']);
        });
    }

    /**
     * Cancel a bill. Draft cancellation is a status flip. Approved/partially
     * paid cancellation posts an offsetting reversal JE through
     * AccountingService::reverseEntry so the audit trail is preserved.
     * Fully paid bills can also be cancelled — but the operator should
     * usually issue a Debit Note instead (tracked separately).
     */
    public function cancel(Bill $bill): Bill
    {
        if ($bill->status === Bill::STATUS_CANCELLED) {
            throw new DomainException("Bill {$bill->bill_number} is already cancelled.");
        }

        return DB::transaction(function () use ($bill) {
            if ($bill->isReversible()) {
                $journal = $bill->journalEntry;
                if ($journal) {
                    $reversal = $this->accounting->reverseEntry(
                        $journal,
                        "BILL-{$bill->bill_number}-CANCEL",
                        "Cancellation of Bill {$bill->bill_number}",
                    );
                    $bill->reversal_journal_entry_id = $reversal->id;
                }
            }

            $bill->status = Bill::STATUS_CANCELLED;
            $bill->save();

            return $bill->fresh(['supplier', 'lines.account', 'payableAccount', 'journalEntry', 'reversalJournalEntry']);
        });
    }

    /**
     * Replace all lines on a draft bill (delete + recreate). Used by both
     * create() and update() — the line set is the source of truth.
     */
    private function replaceLines(Bill $bill, array $normalizedLines): void
    {
        $bill->lines()->delete();
        foreach ($normalizedLines as $line) {
            BillLine::create([
                'bill_id'     => $bill->id,
                'account_id'  => $line['account_id'],
                'description' => $line['description'] ?? null,
                'quantity'    => $line['quantity'],
                'unit_price'  => $line['unit_price'],
                // line_total is recomputed in the model boot hook.
            ]);
        }
    }

    private function recomputeTotals(Bill $bill): void
    {
        $bill->load('lines');
        $subtotal = (float) $bill->lines->sum(fn (BillLine $l) => (float) $l->line_total);
        $tax      = (float) $bill->tax_amount;
        $bill->forceFill([
            'subtotal' => round($subtotal, 2),
            'total'    => round($subtotal + $tax, 2),
        ])->save();
    }

    /**
     * Normalize raw line payload — cast numerics, drop blanks, ensure each
     * row has a positive amount.
     */
    private function normalizeLines(array $raw): array
    {
        $out = [];
        foreach ($raw as $line) {
            if (empty($line['account_id'])) continue;
            $qty = (float) ($line['quantity'] ?? 1);
            $up  = (float) ($line['unit_price'] ?? 0);
            if ($qty <= 0 || $up <= 0) {
                throw new DomainException('Each bill line must have a positive quantity and unit price.');
            }
            $out[] = [
                'account_id'  => $line['account_id'],
                'description' => $line['description'] ?? null,
                'quantity'    => $qty,
                'unit_price'  => $up,
            ];
        }
        if (empty($out)) {
            throw new DomainException('A bill must have at least one valid line.');
        }
        return $out;
    }

    /**
     * Only vendors (is_vendor=true on Supplier) can be billed. Suppliers
     * who never receive payments shouldn't show up in AP.
     */
    private function assertSupplierIsVendor(string $supplierId): void
    {
        $supplier = Supplier::query()->find($supplierId);
        if (!$supplier) {
            throw new DomainException('Supplier not found.');
        }
        if (!$supplier->is_vendor) {
            throw new DomainException(
                "Supplier '{$supplier->name}' is not flagged as a vendor. " .
                'Enable the Vendor / AP Details toggle on the supplier first.'
            );
        }
    }

    private function resolveDefaultPayable(string $supplierId): ?string
    {
        return Supplier::query()->whereKey($supplierId)->value('default_payable_account_id');
    }
}
