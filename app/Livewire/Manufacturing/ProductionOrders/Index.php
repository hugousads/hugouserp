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
            return [
                'total_orders' => (clone $baseQuery)->count(),
                'in_progress' => 0,
                'completed' => 0,
                'planned_quantity' => 0,
                'produced_quantity' => 0,
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
