<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Supplier;
use Illuminate\Support\Collection;
use Livewire\Component;

class CommandPalette extends Component
{
    public string $query = '';
    public int $selectedIndex = 0;
    public array $results = [];
    
    /**
     * Maximum number of results to return
     */
    protected int $maxResults = 10;

    public function updatedQuery(): void
    {
        if (strlen($this->query) < 2) {
            $this->results = [];
            $this->selectedIndex = 0;
            return;
        }

        $this->results = $this->search();
        $this->selectedIndex = 0;
    }

    protected function search(): array
    {
        $query = $this->query;
        $branchId = auth()->user()?->branch_id;
        $results = [];

        // Search Products
        if (auth()->user()?->can('products.view')) {
            $products = Product::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('sku', 'like', "%{$query}%");
                })
                ->limit(5)
                ->get()
                ->map(fn($p) => [
                    'type' => 'Product',
                    'icon' => '📦',
                    'name' => $p->name,
                    'subtitle' => $p->sku ?? '',
                    'url' => route('products.show', $p),
                ]);
            $results = array_merge($results, $products->toArray());
        }

        // Search Customers
        if (auth()->user()?->can('customers.view')) {
            $customers = Customer::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");
                })
                ->limit(5)
                ->get()
                ->map(fn($c) => [
                    'type' => 'Customer',
                    'icon' => '👤',
                    'name' => $c->name,
                    'subtitle' => $c->phone ?? $c->email ?? '',
                    'url' => route('customers.index', ['search' => $c->name]),
                ]);
            $results = array_merge($results, $customers->toArray());
        }

        // Search Suppliers
        if (auth()->user()?->can('suppliers.view')) {
            $suppliers = Supplier::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where(function ($q) use ($query) {
                    $q->where('name', 'like', "%{$query}%")
                        ->orWhere('email', 'like', "%{$query}%")
                        ->orWhere('phone', 'like', "%{$query}%");
                })
                ->limit(5)
                ->get()
                ->map(fn($s) => [
                    'type' => 'Supplier',
                    'icon' => '🏢',
                    'name' => $s->name,
                    'subtitle' => $s->phone ?? $s->email ?? '',
                    'url' => route('suppliers.index', ['search' => $s->name]),
                ]);
            $results = array_merge($results, $suppliers->toArray());
        }

        // Search Sales/Invoices
        if (auth()->user()?->can('sales.view')) {
            $sales = Sale::query()
                ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
                ->where('id', 'like', "%{$query}%")
                ->with('customer')
                ->limit(5)
                ->get()
                ->map(fn($s) => [
                    'type' => 'Invoice',
                    'icon' => '🧾',
                    'name' => "Invoice #{$s->id}",
                    'subtitle' => $s->customer?->name ?? __('Walk-in Customer'),
                    'url' => route('app.sales.show', $s),
                ]);
            $results = array_merge($results, $sales->toArray());
        }

        return array_slice($results, 0, $this->maxResults);
    }

    public function selectResult(int $index): void
    {
        if (isset($this->results[$index])) {
            $this->redirect($this->results[$index]['url']);
        }
    }

    public function moveDown(): void
    {
        if ($this->selectedIndex < count($this->results) - 1) {
            $this->selectedIndex++;
        }
    }

    public function moveUp(): void
    {
        if ($this->selectedIndex > 0) {
            $this->selectedIndex--;
        }
    }

    public function render()
    {
        return view('livewire.command-palette');
    }
}
