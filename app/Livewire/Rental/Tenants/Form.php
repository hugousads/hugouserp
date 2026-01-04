<?php

declare(strict_types=1);

namespace App\Livewire\Rental\Tenants;

use App\Models\Tenant;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;

    public ?int $tenantId = null;

    public string $name = '';

    public string $email = '';

    public string $phone = '';

    public string $address = '';

    public bool $is_active = true;

    public function mount(?int $tenant = null): void
    {
        if ($tenant) {
            $this->authorize('rental.tenants.update');
            $this->tenantId = $tenant;
            $this->loadTenant();
        } else {
            $this->authorize('rental.tenants.create');
        }
    }

    protected function loadTenant(): void
    {
        $tenant = Tenant::findOrFail($this->tenantId);

        $this->name = $tenant->name;
        $this->email = $tenant->email ?? '';
        $this->phone = $tenant->phone ?? '';
        $this->address = $tenant->address ?? '';
        $this->is_active = $tenant->is_active;
    }

    protected function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'is_active' => 'boolean',
        ];
    }

    public function save(): mixed
    {
        if ($this->tenantId) {
            $this->authorize('rental.tenants.update');
        } else {
            $this->authorize('rental.tenants.create');
        }

        $validated = $this->validate();

        $user = auth()->user();
        $data = array_merge($validated, [
            'branch_id' => $user->branch_id ?? 1,
        ]);

        if ($this->tenantId) {
            Tenant::findOrFail($this->tenantId)->update($data);
            session()->flash('success', __('Tenant updated successfully'));
        } else {
            Tenant::create($data);
            session()->flash('success', __('Tenant created successfully'));
        }

        Cache::forget('tenants_stats_'.($user->branch_id ?? 'all'));

        $this->redirectRoute('app.rental.tenants.index', navigate: true);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.rental.tenants.form');
    }
}
