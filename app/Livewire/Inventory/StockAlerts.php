<?php

declare(strict_types=1);

namespace App\Livewire\Inventory;

use App\Models\Product;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class StockAlerts extends Component
{
    use WithPagination;

    public string $search = '';
    public string $alertType = 'all'; // all, low, out, expiring

    public function render()
    {
        $query = Product::query()
            ->with(['branch', 'category', 'unit'])
            ->where('products.track_stock_alerts', true)
            ->where('products.status', 'active')
            ->leftJoin('stock_movements', 'products.id', '=', 'stock_movements.product_id')
            ->select('products.*')
            ->selectRaw('
                COALESCE(
                    SUM(CASE WHEN stock_movements.direction = ? THEN stock_movements.qty ELSE -stock_movements.qty END),
                    0
                ) as current_stock
            ', ['in'])
            ->where(function ($q) {
                $q->where('stock_movements.status', 'posted')
                    ->orWhereNull('stock_movements.id');
            })
            ->groupBy('products.id');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('products.name', 'like', "%{$this->search}%")
                    ->orWhere('products.code', 'like', "%{$this->search}%")
                    ->orWhere('products.sku', 'like', "%{$this->search}%");
            });
        }

        // Filter by alert type using portable comparison
        if ($this->alertType === 'low') {
            $query->havingRaw('current_stock <= products.min_stock AND current_stock > 0');
        } elseif ($this->alertType === 'out') {
            $query->havingRaw('current_stock <= 0');
        }

        $products = $query->paginate(20);

        return view('livewire.inventory.stock-alerts', [
            'products' => $products,
        ]);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedAlertType(): void
    {
        $this->resetPage();
    }
}
