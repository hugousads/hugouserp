<?php

declare(strict_types=1);

namespace App\Livewire\Customers;

use App\Livewire\Concerns\HandlesErrors;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Schema;
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

    public float $discount_percentage = 0;

    public string $payment_terms = '';

    public int $payment_terms_days = 30;

    public string $customer_group = '';

    public string $preferred_payment_method = '';

    public bool $is_active = true;

    private static array $customerColumns = [];

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
            'discount_percentage' => 'nullable|numeric|min:0|max:100',
            'payment_terms' => 'nullable|in:immediate,net15,net30,net60,net90',
            'payment_terms_days' => 'nullable|integer|min:0',
            'customer_group' => 'nullable|string|max:191',
            'preferred_payment_method' => 'nullable|string|max:191',
            'notes' => 'nullable|string',
            'is_active' => 'boolean',
        ];
    }

    public function mount(?Customer $customer = null): void
    {
        $user = auth()->user();

        if ($customer && $customer->exists) {
            $this->authorize('customers.manage');
            if ($user?->branch_id && $customer->branch_id !== $user->branch_id && ! $this->isSuperAdmin($user)) {
                abort(403);
            }
            $this->customer = $customer;
            $this->editMode = true;
            
            // Explicitly set all fields to ensure proper initialization
            $this->name = $customer->name ?? '';
            $this->email = $customer->email ?? '';
            $this->phone = $customer->phone ?? '';
            $this->phone2 = $customer->phone2 ?? '';
            $this->address = $customer->address ?? '';
            $this->city = $customer->city ?? '';
            $this->country = $customer->country ?? '';
            $this->tax_number = $customer->tax_number ?? '';
            $this->company_name = $customer->company_name ?? '';
            $this->customer_type = $customer->customer_type ?? 'individual';
            $this->credit_limit = (float) ($customer->credit_limit ?? 0);
            $this->discount_percentage = (float) ($customer->discount_percentage ?? 0);
            $this->payment_terms = $customer->payment_terms ?? '';
            $this->payment_terms_days = (int) ($customer->payment_terms_days ?? 30);
            $this->customer_group = $customer->customer_group ?? '';
            $this->preferred_payment_method = $customer->preferred_payment_method ?? '';
            $this->notes = $customer->notes ?? '';
            $this->is_active = (bool) ($customer->is_active ?? true);
        } else {
            $this->authorize('customers.manage');
        }
    }

    public function save(): void
    {
        $validated = $this->validate();
        
        // Get the user's branch - handle both direct branch_id and relationship
        $user = auth()->user();
        $branchId = $this->customer?->branch_id ?? $user?->branch_id ?? $user?->branches()->first()?->id;

        if (! $branchId && ! $this->isSuperAdmin($user)) {
            abort(403);
        }
        
        $validated['branch_id'] = $branchId;
        
        // Only set created_by for new records
        if (!$this->editMode) {
            $validated['created_by'] = auth()->id();
        }

        if (empty(self::$customerColumns)) {
            self::$customerColumns = Schema::getColumnListing('customers');
        }

        $validated = array_intersect_key($validated, array_flip(self::$customerColumns));

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

    private function isSuperAdmin(?User $user): bool
    {
        return (bool) $user?->hasAnyRole(['super-admin', 'Super Admin']);
    }
}
