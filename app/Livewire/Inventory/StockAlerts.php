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

    public function mount(): void
    {
        // Authorization check - must have inventory.view permission
        $user = auth()->user();
        if (! $user || ! $user->can('inventory.view')) {
            abort(403, __('Unauthorized access to stock alerts'));
        }
    }

    public function render()
    {
        // Using subquery approach to avoid column ambiguity issues with joins
        // Note: stock_movements table has no status/deleted_at columns per migration
        // All movements are valid - quantity is signed: positive = in, negative = out
        $stockSubquery = DB::table('stock_movements')
            ->select('stock_movements.product_id')
            ->selectRaw('SUM(stock_movements.quantity) as total_stock')
            ->groupBy('stock_movements.product_id');

        $query = Product::query()
            ->with(['branch', 'category', 'unit'])
            ->where('products.track_stock_alerts', true)
            ->where('products.status', 'active')
            ->leftJoinSub($stockSubquery, 'stock_calc', function ($join) {
                $join->on('products.id', '=', 'stock_calc.product_id');
            })
            ->select('products.*')
            ->selectRaw('COALESCE(stock_calc.total_stock, 0) as current_stock');

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('products.name', 'like', "%{$this->search}%")
                    ->orWhere('products.code', 'like', "%{$this->search}%")
                    ->orWhere('products.sku', 'like', "%{$this->search}%");
            });
        }

        // Filter by alert type using portable comparison
        if ($this->alertType === 'low') {
            $query->whereRaw('COALESCE(stock_calc.total_stock, 0) <= products.min_stock AND COALESCE(stock_calc.total_stock, 0) > 0');
        } elseif ($this->alertType === 'out') {
            $query->whereRaw('COALESCE(stock_calc.total_stock, 0) <= 0');
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
