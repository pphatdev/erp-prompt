<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\JournalEntry;
use App\Tenants\Modules\FMS\Resources\LedgerResource;
use App\Tenants\Modules\FMS\Services\AccountingService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class LedgerController extends Controller
{
    use Paginates;

    public function __construct(private readonly AccountingService $accountingService) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', JournalEntry::class);

        $query = JournalEntry::query()->with('entries.account')->orderBy('entry_date', 'desc');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->query('from')) {
            $query->whereDate('entry_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('entry_date', '<=', $to);
        }

        $paginator = $this->paginateQuery($query, $request);
        return $this->paginatedResponse(LedgerResource::class, $paginator, $request);
    }

    public function show(JournalEntry $journal): LedgerResource
    {
        Gate::authorize('view', $journal);
        return new LedgerResource($journal->load('entries.account'));
    }

    public function store(Request $request): LedgerResource|JsonResponse
    {
        Gate::authorize('create', JournalEntry::class);

        $data = $request->validate([
            'reference_number'    => 'required|string|unique:journal_entries,reference_number',
            'description'         => 'nullable|string',
            'entry_date'          => 'required|date',
            'lines'               => 'required|array|min:2',
            'lines.*.account_id'  => 'required|exists:accounts,id',
            'lines.*.debit'       => 'required_without:lines.*.credit|numeric|min:0',
            'lines.*.credit'      => 'required_without:lines.*.debit|numeric|min:0',
        ]);

        try {
            $journal = $this->accountingService->postEntry($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new LedgerResource($journal->load('entries.account'));
    }

    public function reverse(Request $request, JournalEntry $journal): LedgerResource|JsonResponse
    {
        Gate::authorize('reverse', $journal);

        $data = $request->validate([
            'reference_number' => 'sometimes|nullable|string|max:64|unique:journal_entries,reference_number',
            'description'      => 'sometimes|nullable|string|max:255',
        ]);

        try {
            $reversal = $this->accountingService->reverseEntry(
                $journal,
                $data['reference_number'] ?? null,
                $data['description'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new LedgerResource($reversal->load('entries.account'));
    }
}
