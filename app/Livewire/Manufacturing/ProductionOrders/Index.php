<?php

declare(strict_types=1);

namespace App\Livewire\Manufacturing\ProductionOrders;

use App\Livewire\Manufacturing\Concerns\StatsCacheVersion;
use App\Models\ProductionOrder;
use App\Traits\HasSortableColumns;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Cache;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class Index extends Component
{
    use AuthorizesRequests;
    use HasSortableColumns;
    use StatsCacheVersion;
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public function mount(): void
    {
        $this->authorize('manufacturing.view');
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    protected function allowedSortColumns(): array
    {
        return [
            'created_at',
            'order_number',
        ];
    }

    public function getStatistics(): array
    {
        $user = auth()->user();
        $baseQuery = ProductionOrder::query();

        if ($user && $user->branch_id) {
            $baseQuery->where('branch_id', $user->branch_id);
        }

        $cacheKey = sprintf(
            'production_orders_stats_%s_%s',
            $user?->branch_id ?? 'all',
            $this->statsCacheVersion($baseQuery)
        );

        return Cache::remember($cacheKey, 300, function () use ($baseQuery) {
            $totalOrders = (clone $baseQuery)->count();
            $inProgress = 0;
            $completed = 0;
            $plannedQuantity = 0;
            $producedQuantity = 0;

            // Try to get detailed statistics, fallback if columns don't exist
            try {
                $inProgress = (clone $baseQuery)->where('status', 'in_progress')->count();
                $completed = (clone $baseQuery)->where('status', 'completed')->count();
            } catch (\Illuminate\Database\QueryException $e) {
                // Status column doesn't exist
            }

            try {
                $plannedQuantity = (clone $baseQuery)->sum('quantity_planned');
                $producedQuantity = (clone $baseQuery)->sum('quantity_produced');
            } catch (\Illuminate\Database\QueryException $e) {
                // Quantity columns don't exist
            }

            return [
                'total_orders' => $totalOrders,
                'in_progress' => $inProgress,
                'completed' => $completed,
                'planned_quantity' => $plannedQuantity,
                'produced_quantity' => $producedQuantity,
            ];
        });
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $user = auth()->user();

        $orders = ProductionOrder::query()
            ->with(['product', 'bom', 'warehouse', 'branch'])
            ->when($user && $user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('order_number', 'like', "%{$this->search}%")
                    ->orWhereHas('product', fn ($p) => $p->where('name', 'like', "%{$this->search}%"))
                    ->orWhereHas('bom', fn ($b) => $b->where('name', 'like', "%{$this->search}%"));
            }))
            ->orderBy($this->getSortField(), $this->getSortDirection())
            ->paginate(15);

        $stats = $this->getStatistics();

        return view('livewire.manufacturing.production-orders.index', [
            'orders' => $orders,
            'stats' => $stats,
        ]);
    }
}
