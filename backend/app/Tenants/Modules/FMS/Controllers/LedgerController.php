<?php

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\JournalEntry;
use App\Tenants\Modules\FMS\Resources\LedgerResource;
use App\Tenants\Modules\FMS\Services\AccountingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LedgerController extends Controller
{
    use Paginates;

    protected $accountingService;

    public function __construct(AccountingService $accountingService)
    {
        $this->accountingService = $accountingService;
    }

    public function index(Request $request): JsonResponse
    {
        $paginator = $this->paginateQuery(
            JournalEntry::query()->with('entries.account')->orderBy('entry_date', 'desc'),
            $request
        );

        return $this->paginatedResponse(LedgerResource::class, $paginator, $request);
    }

    public function store(Request $request): LedgerResource
    {
        $data = $request->validate([
            'reference_number' => 'required|string|unique:journal_entries,reference_number',
            'description' => 'nullable|string',
            'entry_date' => 'required|date',
            'lines' => 'required|array|min:2',
            'lines.*.account_id' => 'required|exists:accounts,id',
            'lines.*.debit' => 'required_without:lines.*.credit|numeric|min:0',
            'lines.*.credit' => 'required_without:lines.*.debit|numeric|min:0',
        ]);

        $journal = $this->accountingService->postEntry($data);
        return new LedgerResource($journal->load('entries.account'));
    }
}
