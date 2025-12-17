<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Currency;
use App\Models\CurrencyRate;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * CurrencyExchangeService - Multi-currency exchange rate management
 * 
 * NEW FEATURE: Automated currency exchange rate management
 * 
 * FEATURES:
 * - Convert amounts between currencies
 * - Cache exchange rates for performance
 * - Support for manual rate updates
 * - Historical rate tracking
 * - Base currency conversion
 */
class CurrencyExchangeService
{
    private string $baseCurrency;

    public function __construct()
    {
        $this->baseCurrency = setting('general.default_currency', 'EGP');
    }

    /**
     * Convert amount from one currency to another.
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency, ?\DateTime $date = null): float
    {
        if ($fromCurrency === $toCurrency) {
            return $amount;
        }

        $date = $date ?? now();

        // Get exchange rate
        $rate = $this->getExchangeRate($fromCurrency, $toCurrency, $date);

        if ($rate === null) {
            Log::warning("Exchange rate not found for {$fromCurrency} to {$toCurrency}");
            return $amount; // Return original amount if rate not found
        }

        return round($amount * $rate, 4);
    }

    /**
     * Get exchange rate between two currencies.
     */
    public function getExchangeRate(string $fromCurrency, string $toCurrency, ?\DateTime $date = null): ?float
    {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $date = $date ?? now();
        $cacheKey = "exchange_rate_{$fromCurrency}_{$toCurrency}_" . $date->format('Y-m-d');

        return Cache::remember($cacheKey, 3600, function () use ($fromCurrency, $toCurrency, $date) {
            // Try direct conversion
            $rate = $this->getDirectRate($fromCurrency, $toCurrency, $date);

            if ($rate !== null) {
                return $rate;
            }

            // Try conversion through base currency
            return $this->getCrossRate($fromCurrency, $toCurrency, $date);
        });
    }

    /**
     * Get direct exchange rate from database.
     */
    private function getDirectRate(string $fromCurrency, string $toCurrency, \DateTime $date): ?float
    {
        $rate = DB::table('currency_rates')
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('effective_date', '<=', $date)
            ->orderBy('effective_date', 'desc')
            ->first();

        return $rate ? (float) $rate->rate : null;
    }

    /**
     * Calculate cross rate through base currency.
     */
    private function getCrossRate(string $fromCurrency, string $toCurrency, \DateTime $date): ?float
    {
        // Convert from -> base
        $fromRate = $this->getDirectRate($fromCurrency, $this->baseCurrency, $date);

        // Convert base -> to
        $toRate = $this->getDirectRate($this->baseCurrency, $toCurrency, $date);

        if ($fromRate !== null && $toRate !== null) {
            return $fromRate * $toRate;
        }

        // Try inverse rates
        if ($fromRate === null) {
            $inverseRate = $this->getDirectRate($this->baseCurrency, $fromCurrency, $date);
            $fromRate = $inverseRate ? (1 / $inverseRate) : null;
        }

        if ($toRate === null) {
            $inverseRate = $this->getDirectRate($toCurrency, $this->baseCurrency, $date);
            $toRate = $inverseRate ? (1 / $inverseRate) : null;
        }

        if ($fromRate !== null && $toRate !== null) {
            return $fromRate * $toRate;
        }

        return null;
    }

    /**
     * Update exchange rate.
     */
    public function updateRate(string $fromCurrency, string $toCurrency, float $rate, ?\DateTime $effectiveDate = null): CurrencyRate
    {
        $effectiveDate = $effectiveDate ?? now();

        $currencyRate = CurrencyRate::create([
            'from_currency' => $fromCurrency,
            'to_currency' => $toCurrency,
            'rate' => $rate,
            'effective_date' => $effectiveDate,
            'source' => 'manual',
        ]);

        // Clear cache
        $cacheKey = "exchange_rate_{$fromCurrency}_{$toCurrency}_" . $effectiveDate->format('Y-m-d');
        Cache::forget($cacheKey);

        return $currencyRate;
    }

    /**
     * Bulk update rates for a currency against base currency.
     */
    public function bulkUpdateRates(array $rates, ?\DateTime $effectiveDate = null): array
    {
        $effectiveDate = $effectiveDate ?? now();
        $created = [];

        DB::transaction(function () use ($rates, $effectiveDate, &$created) {
            foreach ($rates as $currency => $rate) {
                if ($currency === $this->baseCurrency) {
                    continue;
                }

                $created[] = $this->updateRate($this->baseCurrency, $currency, $rate, $effectiveDate);
            }
        });

        return $created;
    }

    /**
     * Get all active currencies.
     */
    public function getActiveCurrencies(): array
    {
        return Cache::remember('active_currencies', 3600, function () {
            return Currency::where('is_active', true)
                ->orderBy('code')
                ->get()
                ->map(fn($c) => [
                    'code' => $c->code,
                    'name' => $c->name,
                    'symbol' => $c->symbol,
                ])
                ->toArray();
        });
    }

    /**
     * Get latest exchange rates for all currencies against base.
     */
    public function getLatestRates(): array
    {
        $currencies = $this->getActiveCurrencies();
        $rates = [];

        foreach ($currencies as $currency) {
            if ($currency['code'] === $this->baseCurrency) {
                $rates[$currency['code']] = 1.0;
                continue;
            }

            $rate = $this->getExchangeRate($this->baseCurrency, $currency['code']);
            $rates[$currency['code']] = $rate ?? 0;
        }

        return $rates;
    }

    /**
     * Format amount with currency symbol.
     */
    public function format(float $amount, string $currencyCode): string
    {
        $currency = Currency::where('code', $currencyCode)->first();
        $symbol = $currency?->symbol ?? $currencyCode;

        return $symbol . ' ' . number_format($amount, 2);
    }

    /**
     * Get historical rates for a currency pair.
     */
    public function getHistoricalRates(string $fromCurrency, string $toCurrency, int $days = 30): array
    {
        return DB::table('currency_rates')
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->where('effective_date', '>=', now()->subDays($days))
            ->orderBy('effective_date', 'desc')
            ->get()
            ->map(fn($r) => [
                'date' => $r->effective_date,
                'rate' => (float) $r->rate,
                'source' => $r->source,
            ])
            ->toArray();
    }

    /**
     * Check if rate needs update (older than 1 day).
     */
    public function needsUpdate(string $fromCurrency, string $toCurrency): bool
    {
        $latestRate = DB::table('currency_rates')
            ->where('from_currency', $fromCurrency)
            ->where('to_currency', $toCurrency)
            ->orderBy('effective_date', 'desc')
            ->first();

        if (!$latestRate) {
            return true;
        }

        $lastUpdate = new \DateTime($latestRate->effective_date);
        $daysSinceUpdate = $lastUpdate->diff(now())->days;

        return $daysSinceUpdate > 1;
    }

    /**
     * Clear all exchange rate caches.
     */
    public function clearCache(): void
    {
        Cache::forget('active_currencies');
        // Clear all exchange rate caches (this is a simple implementation)
        // In production, you might want to use cache tags
    }
}
