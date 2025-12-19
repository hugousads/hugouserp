<?php

declare(strict_types=1);

namespace App\Livewire\Customers;

use App\Models\Customer;
use App\Traits\HasExport;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use HasExport;
    use WithPagination;

    public string $search = '';

    public string $customerType = '';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public string $paginationMode = 'load-more';

    public int $perPage = 15;

    public int $loadMorePage = 1;

    public bool $hasMorePages = true;

    public ?int $branchId = null;

    public bool $isSuperAdmin = false;

    protected $queryString = ['search', 'customerType'];

    public function mount(): void
    {
        $this->initializeExport('customers');
        $user = auth()->user();
        $this->branchId = $user?->branch_id;
        $this->isSuperAdmin = (bool) $user?->hasRole('super-admin');

        if (!$this->branchId && !$this->isSuperAdmin) {
            abort(403, __('You must be assigned to a branch to view customers.'));
        }
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
        $this->loadMorePage = 1;
    }

    public function loadMore(): void
    {
        $this->loadMorePage++;
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
        $this->loadMorePage = 1;
    }

    public function delete(int $id): void
    {
        $this->authorize('customers.manage');
        $query = Customer::query();

        if ($this->branchId) {
            $query->where('branch_id', $this->branchId);
        } elseif (!$this->isSuperAdmin) {
            abort(403);
        }

        $query->findOrFail($id)->delete();
        session()->flash('success', __('Customer deleted successfully'));
    }

    public function render()
    {
        $query = Customer::query()
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->when($this->search, function ($q) {
                $q->where(function ($searchQuery) {
                    $searchQuery->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%");
                });
            })
            ->when($this->customerType, fn ($q) => $q->where('customer_type', $this->customerType))
            ->orderBy($this->sortField, $this->sortDirection);

        if ($this->paginationMode === 'load-more') {
            $total = (clone $query)->count();
            $customers = $query->take($this->loadMorePage * $this->perPage)->get();
            $this->hasMorePages = $customers->count() < $total;
        } else {
            $customers = $query->paginate($this->perPage);
            $this->hasMorePages = $customers->hasMorePages();
        }

        return view('livewire.customers.index', [
            'customers' => $customers,
            'paginationMode' => $this->paginationMode,
            'hasMorePages' => $this->hasMorePages,
        ])->layout('layouts.app', ['title' => __('Customers')]);
    }

    public function export()
    {
        $data = Customer::query()
            ->when($this->branchId, fn ($q) => $q->where('branch_id', $this->branchId))
            ->when($this->search, function ($q) {
                $q->where(function ($searchQuery) {
                    $searchQuery->where('name', 'like', "%{$this->search}%")
                        ->orWhere('email', 'like', "%{$this->search}%")
                        ->orWhere('phone', 'like', "%{$this->search}%");
                });
            })
            ->when($this->customerType, fn ($q) => $q->where('customer_type', $this->customerType))
            ->orderBy($this->sortField, $this->sortDirection)
            ->select(['id', 'name', 'email', 'phone', 'address', 'balance', 'created_at'])
            ->get();

        return $this->performExport('customers', $data, __('Customers Export'));
    }
}
