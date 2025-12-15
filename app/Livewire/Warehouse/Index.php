<?php

declare(strict_types=1);

namespace App\Livewire\Warehouse;

use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    #[Url]
    public string $activeTab = 'warehouses';

    public function mount(): void
    {
        $this->authorize('warehouse.view');
    }

    #[Url]
    public string $search = '';

    #[Url]
    public string $warehouseId = '';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function setTab(string $tab): void
    {
        $this->activeTab = $tab;
        $this->resetPage();
    }

    public function getStatistics(): array
    {
        $user = auth()->user();
        $cacheKey = 'warehouse_stats_'.($user?->branch_id ?? 'all');

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $warehouseQuery = Warehouse::query();

            if ($user && $user->branch_id) {
                $warehouseQuery->where('branch_id', $user->branch_id);
            }

            $stockMovementQuery = StockMovement::query();
            if ($user && $user->branch_id) {
                $stockMovementQuery->whereHas('warehouse', fn ($q) => $q->where('branch_id', $user->branch_id));
            }
            $totalStock = (clone $stockMovementQuery)->where('direction', 'in')->sum('qty') - (clone $stockMovementQuery)->where('direction', 'out')->sum('qty');
            $totalValue = (clone $stockMovementQuery)->sum('valuated_amount') ?? 0;

            return [
                'total_warehouses' => $warehouseQuery->count(),
                'active_warehouses' => Warehouse::query()
                    ->when($user && $user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
                    ->where('status', 'active')->count(),
                'total_stock' => $totalStock,
                'stock_value' => $totalValue,
                'recent_movements' => StockMovement::query()
                    ->when($user && $user->branch_id, fn ($q) => $q->whereHas('warehouse', fn ($wq) => $wq->where('branch_id', $user->branch_id)))
                    ->where('created_at', '>=', now()->subDays(7))->count(),
            ];
        });
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $user = auth()->user();
        $warehouses = [];
        $movements = [];

        if ($this->activeTab === 'warehouses') {
            $warehouses = Warehouse::query()
                ->when($user && $user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('name')
                ->paginate(15);
        } else {
            $movements = StockMovement::query()
                ->with(['product', 'warehouse'])
                ->when($user && $user->branch_id, fn ($q) => $q->whereHas('warehouse', fn ($wq) => $wq->where('branch_id', $user->branch_id)))
                ->when($this->search, fn ($q) => $q->whereHas('product', fn ($pq) => $pq->where('name', 'like', "%{$this->search}%")))
                ->when($this->warehouseId, fn ($q) => $q->where('warehouse_id', $this->warehouseId))
                ->orderBy('created_at', 'desc')
                ->paginate(15);
        }

        $allWarehouses = Cache::remember('all_warehouses_'.($user?->branch_id ?? 'all'), 600, function () use ($user) {
            return Warehouse::query()
                ->when($user && $user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
                ->get();
        });

        $stats = $this->getStatistics();

        return view('livewire.warehouse.index', [
            'warehouses' => $warehouses,
            'movements' => $movements,
            'allWarehouses' => $allWarehouses,
            'stats' => $stats,
        ]);
    }
}
