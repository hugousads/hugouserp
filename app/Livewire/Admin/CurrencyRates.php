<?php

declare(strict_types=1);

namespace App\Livewire\Admin;

use App\Models\CurrencyRate;
use App\Services\CurrencyService;
use Livewire\Component;
use Livewire\WithPagination;

class CurrencyRates extends Component
{
    use WithPagination;

    public string $fromCurrency = 'EGP';

    public string $toCurrency = 'USD';

    public float $rate = 0;

    public string $effectiveDate = '';

    public ?int $editingId = null;

    public bool $showModal = false;

    public string $baseCurrency = 'EGP';

    public float $convertAmount = 100;

    public string $convertTo = 'USD';

    public ?float $convertedResult = null;

    protected CurrencyService $currencyService;

    public function boot(CurrencyService $currencyService): void
    {
        $this->currencyService = $currencyService;
    }

    public function mount(): void
    {
        // Authorization check - must have settings.view permission
        $user = auth()->user();
        if (! $user || ! $user->can('settings.view')) {
            abort(403, __('Unauthorized access to currency rates'));
        }

        $this->effectiveDate = now()->format('Y-m-d');
    }

    public function render()
    {
        $rates = CurrencyRate::with('creator')
            ->orderByDesc('effective_date')
            ->orderBy('from_currency')
            ->paginate(20);

        $currencies = $this->currencyService->getSupportedCurrencies();

        return view('livewire.admin.currency-rates', [
            'rates' => $rates,
            'currencies' => $currencies,
        ]);
    }

    public function openModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->fromCurrency = 'EGP';
        $this->toCurrency = 'USD';
        $this->rate = 0;
        $this->effectiveDate = now()->format('Y-m-d');
    }

    public function edit(int $id): void
    {
        $rate = CurrencyRate::find($id);
        if ($rate) {
            $this->editingId = $id;
            $this->fromCurrency = $rate->from_currency;
            $this->toCurrency = $rate->to_currency;
            $this->rate = (float) $rate->rate;
            $this->effectiveDate = $rate->effective_date->format('Y-m-d');
            $this->showModal = true;
        }
    }

    public function save(): void
    {
        $this->validate([
            'fromCurrency' => 'required|string|size:3',
            'toCurrency' => 'required|string|size:3|different:fromCurrency',
            'rate' => 'required|numeric|min:0.000001',
            'effectiveDate' => 'required|date',
        ]);

        if ($this->fromCurrency === $this->toCurrency) {
            $this->dispatch('notify', type: 'error', message: __('From and To currencies must be different'));

            return;
        }

        $this->currencyService->setRate(
            $this->fromCurrency,
            $this->toCurrency,
            $this->rate,
            $this->effectiveDate
        );

        $this->dispatch('notify', type: 'success', message: $this->editingId
            ? __('Currency rate updated successfully')
            : __('Currency rate added successfully'));

        $this->closeModal();
    }

    public function deactivate(int $id): void
    {
        $this->authorize('settings.manage');
        
        $this->currencyService->deactivateRate($id);
        $this->dispatch('notify', type: 'success', message: __('Currency rate deactivated'));
    }

    public function activate(int $id): void
    {
        $this->authorize('settings.manage');
        
        $rate = CurrencyRate::find($id);
        if ($rate) {
            $rate->is_active = true;
            $rate->save();
            $this->dispatch('notify', type: 'success', message: __('Currency rate activated'));
        }
    }

    public function convert(): void
    {
        $result = $this->currencyService->convert(
            $this->convertAmount,
            $this->baseCurrency,
            $this->convertTo
        );

        if ($result !== null) {
            $this->convertedResult = $result;
        } else {
            $this->convertedResult = null;
            $this->dispatch('notify', type: 'error', message: __('No exchange rate found for this currency pair'));
        }
    }

    public function addReverseRate(): void
    {
        if ($this->rate > 0) {
            $reverseRate = 1 / $this->rate;

            $this->currencyService->setRate(
                $this->toCurrency,
                $this->fromCurrency,
                $reverseRate,
                $this->effectiveDate
            );

            $this->dispatch('notify', type: 'success', message: __('Reverse rate added successfully'));
        }
    }
}
