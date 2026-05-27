<?php

declare(strict_types=1);

namespace App\Tenants\Modules\FMS\Services;

use App\Models\Tenant\ExchangeRate;
use DomainException;
use Illuminate\Database\Eloquent\Builder;

class ExchangeRateService
{
    public function buildQuery(): Builder
    {
        return ExchangeRate::query()
            ->orderBy('effective_date', 'desc')
            ->orderBy('base_currency')
            ->orderBy('quote_currency');
    }

    public function create(array $data): ExchangeRate
    {
        $this->assertNotSameCurrency($data['base_currency'], $data['quote_currency']);
        $this->assertRatePositive((float) $data['rate']);
        $this->assertNoDuplicate($data['base_currency'], $data['quote_currency'], $data['effective_date']);

        return ExchangeRate::create($data);
    }

    public function update(ExchangeRate $r, array $data): ExchangeRate
    {
        $base  = strtoupper($data['base_currency']  ?? $r->base_currency);
        $quote = strtoupper($data['quote_currency'] ?? $r->quote_currency);
        $date  = $data['effective_date'] ?? optional($r->effective_date)->toDateString();

        if (isset($data['rate'])) {
            $this->assertRatePositive((float) $data['rate']);
        }
        $this->assertNotSameCurrency($base, $quote);

        $pairChanged = $base !== $r->base_currency
            || $quote !== $r->quote_currency
            || $date !== optional($r->effective_date)->toDateString();
        if ($pairChanged) {
            $this->assertNoDuplicate($base, $quote, $date, $r->id);
        }

        $r->update($data);
        return $r->fresh();
    }

    public function archive(ExchangeRate $r): ExchangeRate
    {
        $r->update(['is_active' => false]);
        $r->delete();
        return $r;
    }

    public function latest(string $base, string $quote, ?string $on = null): ?ExchangeRate
    {
        $base  = strtoupper($base);
        $quote = strtoupper($quote);

        $q = ExchangeRate::query()
            ->where('base_currency', $base)
            ->where('quote_currency', $quote)
            ->where('is_active', true);

        if ($on !== null) {
            $q->where('effective_date', '<=', $on);
        }

        return $q->orderBy('effective_date', 'desc')->first();
    }

    /**
     * Converts $amount from $from to $to using the latest active rate on or
     * before $on. Falls back to the inverse pair (1 / rate) if only the
     * reverse direction is stored.
     */
    public function convert(float $amount, string $from, string $to, ?string $on = null): array
    {
        $from = strtoupper($from);
        $to   = strtoupper($to);

        if ($from === $to) {
            return [
                'amount'         => $amount,
                'from'           => $from,
                'to'             => $to,
                'rate'           => 1.0,
                'converted'      => $amount,
                'effectiveDate'  => $on,
                'rateId'         => null,
                'inverse'        => false,
            ];
        }

        $direct = $this->latest($from, $to, $on);
        if ($direct) {
            return [
                'amount'        => $amount,
                'from'          => $from,
                'to'            => $to,
                'rate'          => (float) $direct->rate,
                'converted'     => round($amount * (float) $direct->rate, 4),
                'effectiveDate' => optional($direct->effective_date)->toDateString(),
                'rateId'        => $direct->id,
                'inverse'       => false,
            ];
        }

        $inverse = $this->latest($to, $from, $on);
        if ($inverse && (float) $inverse->rate > 0) {
            $inverseRate = 1 / (float) $inverse->rate;
            return [
                'amount'        => $amount,
                'from'          => $from,
                'to'            => $to,
                'rate'          => $inverseRate,
                'converted'     => round($amount * $inverseRate, 4),
                'effectiveDate' => optional($inverse->effective_date)->toDateString(),
                'rateId'        => $inverse->id,
                'inverse'       => true,
            ];
        }

        throw new DomainException("No exchange rate available for {$from}/{$to}.");
    }

    private function assertNotSameCurrency(string $base, string $quote): void
    {
        if (strtoupper($base) === strtoupper($quote)) {
            throw new DomainException('Base and quote currencies must differ.');
        }
    }

    private function assertRatePositive(float $rate): void
    {
        if ($rate <= 0) {
            throw new DomainException('Rate must be greater than zero.');
        }
    }

    private function assertNoDuplicate(string $base, string $quote, string $date, ?string $ignoreId = null): void
    {
        $exists = ExchangeRate::query()
            ->where('base_currency', strtoupper($base))
            ->where('quote_currency', strtoupper($quote))
            ->whereDate('effective_date', $date)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists();
        if ($exists) {
            throw new DomainException("A rate for {$base}/{$quote} on {$date} already exists.");
        }
    }
}
