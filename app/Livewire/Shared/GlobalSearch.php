<?php

declare(strict_types=1);

namespace App\Livewire\Shared;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\Sale;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class GlobalSearch extends Component
{
    public string $query = '';

    public array $results = [];

    public bool $showResults = false;

    public bool $isSearching = false;

    public function updatedQuery(): void
    {
        $this->search();
    }

    public function search(): void
    {
        $this->results = [];
        $this->showResults = false;
        $this->isSearching = false;

        if (strlen($this->query) < 2) {
            return;
        }

        /** @var User|null $user */
        $user = Auth::user();
        if (! $user) {
            return;
        }

        $this->isSearching = true;
        $searchTerm = '%'.$this->query.'%';

        if ($user->can('inventory.products.view')) {
            $canEdit = $user->can('inventory.products.manage');
            $products = Product::query()
                ->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', $searchTerm)
                        ->orWhere('sku', 'like', $searchTerm)
                        ->orWhere('barcode', 'like', $searchTerm);
                })
                ->limit(5)
                ->get(['id', 'name', 'sku']);

            if ($products->isNotEmpty()) {
                $this->results['products'] = [
                    'label' => __('Products'),
                    'icon' => '📦',
                    'route' => 'inventory.products.index',
                    'items' => $products->map(fn ($p) => [
                        'id' => $p->id,
                        'title' => $p->name,
                        'subtitle' => 'SKU: '.($p->sku ?: '-'),
                        'route' => $canEdit
                            ? route('inventory.products.edit', $p->id)
                            : route('inventory.products.index', ['search' => $p->sku]),
                    ])->toArray(),
                ];
            }
        }

        if ($user->can('customers.view')) {
            $canEdit = $user->can('customers.manage');
            $customers = Customer::query()
                ->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm)
                        ->orWhere('phone', 'like', $searchTerm);
                })
                ->limit(5)
                ->get(['id', 'name']);

            if ($customers->isNotEmpty()) {
                $this->results['customers'] = [
                    'label' => __('Customers'),
                    'icon' => '👥',
                    'route' => 'customers.index',
                    'items' => $customers->map(fn ($c) => [
                        'id' => $c->id,
                        'title' => $c->name,
                        'subtitle' => __('Customer'),
                        'route' => $canEdit
                            ? route('customers.edit', $c->id)
                            : route('customers.index', ['search' => $c->name]),
                    ])->toArray(),
                ];
            }
        }

        if ($user->can('suppliers.view')) {
            $canEdit = $user->can('suppliers.manage');
            $suppliers = Supplier::query()
                ->where(function ($q) use ($searchTerm) {
                    $q->where('name', 'like', $searchTerm)
                        ->orWhere('email', 'like', $searchTerm)
                        ->orWhere('phone', 'like', $searchTerm);
                })
                ->limit(5)
                ->get(['id', 'name']);

            if ($suppliers->isNotEmpty()) {
                $this->results['suppliers'] = [
                    'label' => __('Suppliers'),
                    'icon' => '🏭',
                    'route' => 'suppliers.index',
                    'items' => $suppliers->map(fn ($s) => [
                        'id' => $s->id,
                        'title' => $s->name,
                        'subtitle' => __('Supplier'),
                        'route' => $canEdit
                            ? route('suppliers.edit', $s->id)
                            : route('suppliers.index', ['search' => $s->name]),
                    ])->toArray(),
                ];
            }
        }

        if ($user->can('sales.view')) {
            $sales = Sale::query()
                ->where(function ($q) use ($searchTerm) {
                    $q->where('invoice_number', 'like', $searchTerm)
                        ->orWhere('reference_no', 'like', $searchTerm);
                })
                ->limit(5)
                ->get(['id', 'invoice_number', 'status']);

            if ($sales->isNotEmpty()) {
                $this->results['sales'] = [
                    'label' => __('Sales'),
                    'icon' => '💰',
                    'route' => 'sales.index',
                    'items' => $sales->map(fn ($s) => [
                        'id' => $s->id,
                        'title' => $s->invoice_number ?: '#'.$s->id,
                        'subtitle' => ucfirst($s->status ?? 'pending'),
                        'route' => route('sales.show', $s->id),
                    ])->toArray(),
                ];
            }
        }

        if ($user->can('purchases.view')) {
            $canEdit = $user->can('purchases.manage');
            $purchases = Purchase::query()
                ->where(function ($q) use ($searchTerm) {
                    $q->where('reference_no', 'like', $searchTerm);
                })
                ->limit(5)
                ->get(['id', 'reference_no', 'status']);

            if ($purchases->isNotEmpty()) {
                $this->results['purchases'] = [
                    'label' => __('Purchases'),
                    'icon' => '📋',
                    'route' => 'purchases.index',
                    'items' => $purchases->map(fn ($p) => [
                        'id' => $p->id,
                        'title' => $p->reference_no ?: '#'.$p->id,
                        'subtitle' => ucfirst($p->status ?? 'pending'),
                        'route' => $canEdit
                            ? route('purchases.edit', $p->id)
                            : route('purchases.index', ['search' => $p->reference_no]),
                    ])->toArray(),
                ];
            }
        }

        $this->showResults = ! empty($this->results);
        $this->isSearching = false;
    }

    public function clearSearch(): void
    {
        $this->query = '';
        $this->results = [];
        $this->showResults = false;
    }

    public function closeResults(): void
    {
        $this->showResults = false;
    }

    public function getTotalResultsProperty(): int
    {
        return collect($this->results)->sum(fn ($group) => count($group['items'] ?? []));
    }

    public function render()
    {
        return view('livewire.shared.global-search');
    }
}
