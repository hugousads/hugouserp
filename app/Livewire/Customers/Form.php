<?php

declare(strict_types=1);

namespace App\Livewire\Customers;

use App\Livewire\Concerns\HandlesErrors;
use App\Models\Customer;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;
    use HandlesErrors;

    public ?Customer $customer = null;

    public bool $editMode = false;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $phone2 = '';

    public string $address = '';

    public string $city = '';

    public string $country = '';

    public string $tax_number = '';

    public string $company_name = '';

    public string $customer_type = 'individual';

    public float $credit_limit = 0;

    public string $notes = '';

    public bool $is_active = true;

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'phone2' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'country' => 'nullable|string|max:100',
            'tax_number' => 'nullable|string|max:50',
            'company_name' => 'nullable|string|max:255',
            'customer_type' => 'required|in:individual,company',
            'credit_limit' => 'nullable|numeric|min:0',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function mount(?Customer $customer = null): void
    {
        if ($customer && $customer->exists) {
            $this->authorize('customers.manage');
            $this->customer = $customer;
            $this->editMode = true;
            $this->fill($customer->toArray());
        } else {
            $this->authorize('customers.manage');
        }
    }

    public function save(): void
    {
        $validated = $this->validate();
        
        // Get the user's branch - handle both direct branch_id and relationship
        $user = auth()->user();
        $branchId = $user->branch_id ?? $user->branches()->first()?->id ?? 1;
        
        $validated['branch_id'] = $branchId;
        
        // Only set created_by for new records
        if (!$this->editMode) {
            $validated['created_by'] = auth()->id();
        }

        $this->handleOperation(
            operation: function () use ($validated) {
                if ($this->editMode) {
                    $this->customer->update($validated);
                } else {
                    Customer::create($validated);
                }
            },
            successMessage: $this->editMode ? __('Customer updated successfully') : __('Customer created successfully'),
            redirectRoute: 'customers.index'
        );
    }

    public function render()
    {
        return view('livewire.customers.form')
            ->layout('layouts.app', ['title' => $this->editMode ? __('Edit Customer') : __('Add Customer')]);
    }
}
