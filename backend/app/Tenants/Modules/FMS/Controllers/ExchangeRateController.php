<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Controllers;

use App\Http\Concerns\Paginates;
use App\Http\Controllers\Controller;
use App\Models\Tenant\ExchangeRate;
use App\Tenants\Modules\FMS\Resources\ExchangeRateResource;
use App\Tenants\Modules\FMS\Services\ExchangeRateService;
use DomainException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class ExchangeRateController extends Controller
{
    use Paginates;

    public function __construct(private readonly ExchangeRateService $service) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ExchangeRate::class);

        $query = $this->service->buildQuery();

        if ($base = $request->query('base_currency')) {
            $query->where('base_currency', strtoupper($base));
        }
        if ($quote = $request->query('quote_currency')) {
            $query->where('quote_currency', strtoupper($quote));
        }
        if ($from = $request->query('from')) {
            $query->whereDate('effective_date', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $query->whereDate('effective_date', '<=', $to);
        }
        if ($request->has('is_active')) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        return $this->paginatedResponse(ExchangeRateResource::class, $this->paginateQuery($query, $request), $request);
    }

    public function store(Request $request): ExchangeRateResource|JsonResponse
    {
        Gate::authorize('create', ExchangeRate::class);
        $data = $this->validatePayload($request);

        try {
            $rate = $this->service->create($data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ExchangeRateResource($rate);
    }

    public function show(ExchangeRate $exchangeRate): ExchangeRateResource
    {
        Gate::authorize('view', $exchangeRate);
        return new ExchangeRateResource($exchangeRate);
    }

    public function update(Request $request, ExchangeRate $exchangeRate): ExchangeRateResource|JsonResponse
    {
        Gate::authorize('update', $exchangeRate);
        $data = $this->validatePayload($request, $exchangeRate);

        try {
            $rate = $this->service->update($exchangeRate, $data);
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return new ExchangeRateResource($rate);
    }

    public function destroy(ExchangeRate $exchangeRate): JsonResponse
    {
        Gate::authorize('delete', $exchangeRate);
        $this->service->archive($exchangeRate);
        return response()->json(['message' => 'Exchange rate archived.']);
    }

    public function latest(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ExchangeRate::class);
        $data = $request->validate([
            'base_currency'  => 'required|string|size:3',
            'quote_currency' => 'required|string|size:3',
            'on'             => 'sometimes|date',
        ]);

        $rate = $this->service->latest($data['base_currency'], $data['quote_currency'], $data['on'] ?? null);
        if (!$rate) {
            return response()->json(['message' => 'No rate found for this pair.'], 404);
        }
        return response()->json(['data' => (new ExchangeRateResource($rate))->toArray($request)]);
    }

    public function convert(Request $request): JsonResponse
    {
        Gate::authorize('viewAny', ExchangeRate::class);
        $data = $request->validate([
            'amount' => 'required|numeric',
            'from'   => 'required|string|size:3',
            'to'     => 'required|string|size:3',
            'on'     => 'sometimes|date',
        ]);

        try {
            $result = $this->service->convert(
                (float) $data['amount'],
                $data['from'],
                $data['to'],
                $data['on'] ?? null,
            );
        } catch (DomainException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        return response()->json(['data' => $result]);
    }

    private function validatePayload(Request $request, ?ExchangeRate $existing = null): array
    {
        $isUpdate = $existing !== null;
        $req = $isUpdate ? 'sometimes' : 'required';
        return $request->validate([
            'base_currency'  => "{$req}|string|size:3",
            'quote_currency' => "{$req}|string|size:3",
            'rate'           => "{$req}|numeric|gt:0",
            'effective_date' => "{$req}|date",
            'source'         => 'sometimes|nullable|string|max:32',
            'notes'          => 'sometimes|nullable|string|max:2000',
            'is_active'      => 'sometimes|boolean',
        ]);
    }
}
