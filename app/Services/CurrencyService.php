<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Currency;
use App\Models\CurrencyRate;
use App\Traits\HandlesServiceErrors;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class CurrencyService
{
    use HandlesServiceErrors;

    public function getSupportedCurrencies(): array
    {
        return Cache::remember('supported_currencies', 3600, function () {
            $currencies = Currency::active()->ordered()->get();

            if ($currencies->isEmpty()) {
                return $this->getDefaultCurrencies();
            }

            return $currencies->pluck('name', 'code')->toArray();
        });
    }

    public function getActiveCurrencies(): Collection
    {
        return Currency::active()->ordered()->get();
    }

    public function getCurrencySymbols(): array
    {
        return Cache::remember('currency_symbols', 3600, function () {
            $currencies = Currency::active()->get();

            if ($currencies->isEmpty()) {
                return $this->getDefaultSymbols();
            }

            return $currencies->pluck('symbol', 'code')->toArray();
        });
    }

    public function getBaseCurrency(): ?Currency
    {
        return Currency::where('is_base', true)->first();
    }

    protected function getDefaultCurrencies(): array
    {
        return [
            'EGP' => 'Egyptian Pound',
            'USD' => 'US Dollar',
            'EUR' => 'Euro',
            'GBP' => 'British Pound',
            'SAR' => 'Saudi Riyal',
            'AED' => 'UAE Dirham',
            'KWD' => 'Kuwaiti Dinar',
        ];
    }

    protected function getDefaultSymbols(): array
    {
        return [
            'EGP' => 'ج.م',
            'USD' => '$',
            'EUR' => '€',
            'GBP' => '£',
            'SAR' => 'ر.س',
            'AED' => 'د.إ',
            'KWD' => 'د.ك',
        ];
    }

    public function getActiveRates(): Collection
    {
        return $this->handleServiceOperation(
            callback: fn () => CurrencyRate::query()
                ->where('is_active', true)
                ->orderBy('from_currency')
                ->orderBy('to_currency')
                ->orderByDesc('effective_date')
                ->get(),
            operation: 'getActiveRates',
            context: [],
            defaultValue: new Collection
        );
    }

    public function getRate(string $from, string $to, $date = null): ?float
    {
        return $this->handleServiceOperation(
            callback: function () use ($from, $to, $date) {
                if (strtoupper($from) === strtoupper($to)) {
                    return 1.0;
                }

                $rate = CurrencyRate::getRate($from, $to, $date);

                if ($rate === null) {
                    $reverseRate = CurrencyRate::getRate($to, $from, $date);
                    if ($reverseRate !== null && $reverseRate > 0) {
                        return 1 / $reverseRate;
                    }
                }

                return $rate;
            },
            operation: 'getRate',
            context: ['from' => $from, 'to' => $to, 'date' => $date],
            defaultValue: null
        );
    }

    public function convert(float $amount, string $from, string $to, $date = null): ?float
    {
        $rate = $this->getRate($from, $to, $date);

        if ($rate === null) {
            return null;
        }

        // Use bcmath for precise currency conversion
        return (float) bcmul((string) $amount, (string) $rate, 2);
    }

    public function setRate(string $from, string $to, float $rate, $effectiveDate = null): CurrencyRate
    {
        return $this->handleServiceOperation(
            callback: function () use ($from, $to, $rate, $effectiveDate) {
                $effectiveDateObject = $effectiveDate
                    ? \Carbon\Carbon::parse($effectiveDate)->startOfDay()
                    : now()->startOfDay();
                $from = strtoupper($from);
                $to = strtoupper($to);

                $currencyRate = CurrencyRate::updateOrCreate(
                    [
                        'from_currency' => $from,
                        'to_currency' => $to,
                        'effective_date' => $effectiveDateObject,
                    ],
                    [
                        'rate' => $rate,
                        'is_active' => true,
                    ]
                );

                // Set created_by only for newly created rates
                if ($currencyRate->wasRecentlyCreated) {
                    $currencyRate->created_by = auth()->id();
                    $currencyRate->save();
                }

                $this->clearRateCache($from, $to, $effectiveDateObject->format('Y-m-d'));

                return $currencyRate;
            },
            operation: 'setRate',
            context: ['from' => $from, 'to' => $to, 'rate' => $rate]
        );
    }

    public function setRateWithReverse(string $from, string $to, float $rate, $effectiveDate = null): array
    {
        $forwardRate = $this->setRate($from, $to, $rate, $effectiveDate);

        $reverseRate = null;
        if ($rate > 0) {
            $reverseRate = $this->setRate($to, $from, 1 / $rate, $effectiveDate);
        }

        return ['forward' => $forwardRate, 'reverse' => $reverseRate];
    }

    protected function clearRateCache(string $from, string $to, $effectiveDate = null): void
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        $dateKey = $effectiveDate
            ? \Carbon\Carbon::parse($effectiveDate)->format('Y-m-d')
            : 'latest';

        foreach ([$dateKey, 'latest'] as $key) {
            Cache::forget(sprintf('currency_rate:%s:%s:%s', $from, $to, $key));
            Cache::forget(sprintf('currency_rate:%s:%s:%s', $to, $from, $key));
        }
    }

    public function deactivateRate(int $id): bool
    {
        return $this->handleServiceOperation(
            callback: function () use ($id) {
                $rate = CurrencyRate::findOrFail($id);
                $rate->is_active = false;
                $rate->save();

                $this->clearRateCache($rate->from_currency, $rate->to_currency);

                return true;
            },
            operation: 'deactivateRate',
            context: ['id' => $id],
            defaultValue: false
        );
    }

    public function formatAmount(float $amount, string $currency): string
    {
        $symbols = $this->getCurrencySymbols();
        $symbol = $symbols[strtoupper($currency)] ?? $currency;

        $currencyModel = Currency::getCurrencyByCode($currency);
        $decimals = $currencyModel ? $currencyModel->decimal_places : 2;

        $formatted = number_format($amount, $decimals);

        return "{$formatted} {$symbol}";
    }

    public function getLatestRatesForCurrency(string $baseCurrency): array
    {
        return $this->handleServiceOperation(
            callback: function () use ($baseCurrency) {
                $rates = [];
                $currencies = array_keys($this->getSupportedCurrencies());

                foreach ($currencies as $currency) {
                    if ($currency === $baseCurrency) {
                        continue;
                    }

                    $rate = $this->getRate($baseCurrency, $currency);
                    if ($rate !== null) {
                        $rates[$currency] = $rate;
                    }
                }

                return $rates;
            },
            operation: 'getLatestRatesForCurrency',
            context: ['base_currency' => $baseCurrency],
            defaultValue: []
        );
    }

    public function clearCurrencyCache(): void
    {
        Cache::forget('supported_currencies');
        Cache::forget('currency_symbols');
    }
}
