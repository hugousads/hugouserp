<?php

declare(strict_types=1);

namespace App\Livewire\Banking\Accounts;

use App\Models\BankAccount;
use App\Models\Currency;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;

    public ?BankAccount $account = null;
    public bool $isEditing = false;

    // Form fields
    public string $account_number = '';
    public string $account_name = '';
    public string $bank_name = '';
    public string $bank_branch = '';
    public string $swift_code = '';
    public string $iban = '';
    public string $currency = 'USD';
    public string $account_type = 'checking';
    public string $opening_balance = '0';
    public string $opening_date = '';
    public string $notes = '';

    public array $currencies = [];

    protected function rules(): array
    {
        return [
            'account_number' => 'required|string|max:255|unique:bank_accounts,account_number,' . ($this->account->id ?? 'NULL'),
            'account_name' => 'required|string|max:255',
            'bank_name' => 'required|string|max:255',
            'bank_branch' => 'nullable|string|max:255',
            'swift_code' => 'nullable|string|max:255',
            'iban' => 'nullable|string|max:255',
            'currency' => 'required|string|size:3',
            'account_type' => 'required|in:checking,savings,credit',
            'opening_balance' => 'required|numeric',
            'opening_date' => 'required|date',
            'notes' => 'nullable|string',
        ];
    }

    public function mount(?BankAccount $account = null): void
    {
        // Load currencies
        $this->currencies = Currency::query()
            ->where('is_active', true)
            ->pluck('code', 'code')
            ->toArray();

        if (empty($this->currencies)) {
            $this->currencies = ['USD' => 'USD', 'EUR' => 'EUR', 'GBP' => 'GBP'];
        }

        if ($account && $account->exists) {
            $this->authorize('banking.edit');
            $this->isEditing = true;
            $this->account = $account;
            $this->fill($account->toArray());
            $this->opening_date = $account->opening_date->format('Y-m-d');
        } else {
            $this->authorize('banking.create');
            $this->opening_date = now()->format('Y-m-d');
            
            // Set default currency if available
            $defaultCurrency = \App\Models\SystemSetting::where('key', 'default_currency')->value('value');
            if ($defaultCurrency && isset($this->currencies[$defaultCurrency])) {
                $this->currency = $defaultCurrency;
            }
        }
    }

    public function save(): void
    {
        $this->validate();

        $data = [
            'branch_id' => auth()->user()->branch_id,
            'account_number' => $this->account_number,
            'account_name' => $this->account_name,
            'bank_name' => $this->bank_name,
            'bank_branch' => $this->bank_branch,
            'swift_code' => $this->swift_code,
            'iban' => $this->iban,
            'currency' => $this->currency,
            'account_type' => $this->account_type,
            'opening_balance' => $this->opening_balance,
            'opening_date' => $this->opening_date,
            'notes' => $this->notes,
            'status' => 'active',
        ];

        if ($this->isEditing) {
            $data['updated_by'] = auth()->id();
            
            // Check if account has transactions before updating balance
            $hasTransactions = \DB::table('bank_transactions')
                ->where('bank_account_id', $this->account->id)
                ->exists();
            
            $this->account->update($data);
            
            // Update current balance if opening balance changed and no transactions exist
            if (!$hasTransactions) {
                $this->account->current_balance = $this->opening_balance;
                $this->account->save();
            }
            
            session()->flash('success', __('Bank account updated successfully'));
        } else {
            $data['created_by'] = auth()->id();
            $data['current_balance'] = $this->opening_balance;
            BankAccount::create($data);
            session()->flash('success', __('Bank account created successfully'));
        }

        $this->redirect(route('app.banking.accounts.index'));
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.banking.accounts.form');
    }
}
