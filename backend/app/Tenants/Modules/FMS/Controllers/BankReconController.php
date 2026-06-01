<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\BankReconSession;
use App\Models\Tenant\BankReconStatementLine;
use App\Tenants\Modules\FMS\Resources\BankReconSessionResource;
use App\Tenants\Modules\FMS\Resources\BankReconStatementLineResource;
use App\Tenants\Modules\FMS\Services\BankReconService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class BankReconController extends Controller
{
    use Paginates;

    public function __construct(private readonly BankReconService $service) {}

    // ----- Sessions ---------------------------------------------------------

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', BankReconSession::class);

        $query = $this->service->buildQuery();

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($bankId = $request->query('bank_account_id')) {
            $query->where('bank_account_id', $bankId);
        }
        if ($search = $request->query('search')) {
            $like = '%' . $search . '%';
            $query->where(fn ($q) => $q
                ->where('session_number', 'ilike', $like)
                ->orWhereHas('bankAccount', fn ($b) => $b->where('name', 'ilike', $like)));
        }
        if ($from = $request->query('from')) {
            $query->whereDate('end_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('end_date', '<=', $to);
        }

        return $this->paginatedResponse(BankReconSessionResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): BankReconSessionResource|JsonResponse
    {
        Gate::authorize('create', BankReconSession::class);
        $data = $request->validate([
            'session_number'           => 'required|string|max:64|unique:bank_recon_sessions,session_number',
            'bank_account_id'          => 'required|uuid|exists:bank_accounts,id',
            'start_date'               => 'required|date',
            'end_date'                 => 'required|date|after_or_equal:start_date',
            'opening_balance'          => 'sometimes|nullable|numeric',
            'statement_ending_balance' => 'required|numeric',
            'notes'                    => 'sometimes|nullable|string|max:2000',
        ]);

        try {
            $session = $this->service->open($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new BankReconSessionResource($session);
    }

    public function show(BankReconSession $bankReconciliation): BankReconSessionResource
    {
        Gate::authorize('view', $bankReconciliation);
        return new BankReconSessionResource(
            $bankReconciliation->load(['bankAccount.glAccount', 'statementLines.matchedLedgerEntry'])
        );
    }

    public function close(BankReconSession $bankReconciliation): BankReconSessionResource|JsonResponse
    {
        Gate::authorize('close', $bankReconciliation);
        try {
            $session = $this->service->close($bankReconciliation);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new BankReconSessionResource($session);
    }

    public function reopen(BankReconSession $bankReconciliation): BankReconSessionResource|JsonResponse
    {
        Gate::authorize('reopen', $bankReconciliation);
        try {
            $session = $this->service->reopen($bankReconciliation);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new BankReconSessionResource($session);
    }

    // ----- Statement lines --------------------------------------------------

    public function addLine(Request $request, BankReconSession $bankReconciliation): BankReconStatementLineResource|JsonResponse
    {
        Gate::authorize('modify', $bankReconciliation);
        $data = $request->validate([
            'statement_date'   => 'required|date',
            'description'      => 'required|string|max:500',
            'reference_number' => 'sometimes|nullable|string|max:64',
            'amount'           => 'required|numeric',
            'notes'            => 'sometimes|nullable|string|max:500',
        ]);

        try {
            $line = $this->service->addStatementLine($bankReconciliation, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new BankReconStatementLineResource($line);
    }

    public function removeLine(BankReconStatementLine $line): JsonResponse
    {
        Gate::authorize('modify', $line->session);
        try {
            $this->service->removeStatementLine($line);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return response()->json(['data' => ['deleted' => true]]);
    }

    public function matchLine(Request $request, BankReconStatementLine $line): BankReconStatementLineResource|JsonResponse
    {
        Gate::authorize('modify', $line->session);
        $data = $request->validate([
            'ledger_entry_id' => 'required|uuid|exists:ledger_entries,id',
        ]);
        try {
            $line = $this->service->match($line, $data['ledger_entry_id']);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new BankReconStatementLineResource($line);
    }

    public function unmatchLine(BankReconStatementLine $line): BankReconStatementLineResource|JsonResponse
    {
        Gate::authorize('modify', $line->session);
        try {
            $line = $this->service->unmatch($line);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
        return new BankReconStatementLineResource($line);
    }

    // ----- Period ledger entries (matcher UI helper) ------------------------

    public function periodLedgerEntries(Request $request, BankReconSession $bankReconciliation): JsonResponse
    {
        Gate::authorize('view', $bankReconciliation);

        $entries = $this->service->periodLedgerEntriesQuery($bankReconciliation)
            ->orderBy(
                \App\Models\Tenant\JournalEntry::select('entry_date')
                    ->whereColumn('id', 'ledger_entries.journal_entry_id')
                    ->limit(1)
            )
            ->get()
            ->map(function (\App\Models\Tenant\LedgerEntry $e) {
                $j = $e->journalEntry;
                return [
                    'id'              => $e->id,
                    'journalEntryId'  => $e->journal_entry_id,
                    'referenceNumber' => $j?->reference_number,
                    'description'     => $j?->description,
                    'entryDate'       => optional($j?->entry_date)->toDateString(),
                    'debit'           => (float) $e->debit,
                    'credit'          => (float) $e->credit,
                    'direction'       => (float) $e->debit > 0.001 ? 'deposit' : ((float) $e->credit > 0.001 ? 'withdrawal' : 'zero'),
                    'amountAbs'       => (float) $e->debit > 0.001 ? (float) $e->debit : (float) $e->credit,
                    'matchedInSession'=> \App\Models\Tenant\BankReconStatementLine::query()
                        ->where('matched_ledger_entry_id', $e->id)
                        ->value('session_id'),
                ];
            });

        return response()->json(['data' => $entries]);
    }
}
