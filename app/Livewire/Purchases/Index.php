<?php

declare(strict_types=1);

namespace App\Livewire\Purchases;

use App\Models\Purchase;
use App\Traits\HasExport;
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
    use HasExport;
    use HasSortableColumns;
    use WithPagination;

    #[Url]
    public string $search = '';

    public string $sortField = 'created_at';

    public string $sortDirection = 'desc';

    public function mount(): void
    {
        $this->authorize('purchases.view');
        $this->initializeExport('purchases');
    }

    #[Url]
    public string $status = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    /**
     * Define allowed sort columns to prevent SQL injection.
     */
    protected function allowedSortColumns(): array
    {
        return ['id', 'code', 'reference_no', 'grand_total', 'paid_total', 'due_total', 'status', 'created_at', 'updated_at'];
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function getStatistics(): array
    {
        $user = auth()->user();
        $cacheKey = 'purchases_stats_'.($user?->branch_id ?? 'all');

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $query = Purchase::query();

            if ($user && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            }

            return [
                'total_purchases' => $query->count(),
                'total_amount' => $query->sum('grand_total'),
                'total_paid' => $query->sum('paid_total'),
                'total_due' => $query->sum('due_total'),
            ];
        });
    }

    public function export()
    {
        $user = auth()->user();
        $sortField = $this->getSortField();
        $sortDirection = $this->getSortDirection();

        $data = Purchase::query()
            ->leftJoin('suppliers', 'purchases.supplier_id', '=', 'suppliers.id')
            ->leftJoin('branches', 'purchases.branch_id', '=', 'branches.id')
            ->when($user && $user->branch_id, fn ($q) => $q->where('purchases.branch_id', $user->branch_id))
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('purchases.code', 'like', "%{$this->search}%")
                    ->orWhere('purchases.reference_no', 'like', "%{$this->search}%")
                    ->orWhere('suppliers.name', 'like', "%{$this->search}%");
            }))
            ->when($this->status, fn ($q) => $q->where('purchases.status', $this->status))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('purchases.created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('purchases.created_at', '<=', $this->dateTo))
            ->orderBy('purchases.'.$sortField, $sortDirection)
            ->select([
                'purchases.id',
                'purchases.code as reference',
                'purchases.created_at as posted_at',
                'suppliers.name as supplier_name',
                'purchases.grand_total',
                'purchases.paid_total as amount_paid',
                'purchases.due_total as amount_due',
                'purchases.status',
                'branches.name as branch_name',
            ])
            ->get();

        return $this->performExport('purchases', $data, __('Purchases Export'));
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $user = auth()->user();

        $purchases = Purchase::query()
            ->with(['supplier', 'branch', 'warehouse', 'createdBy'])
            ->when($user && $user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('code', 'like', "%{$this->search}%")
                    ->orWhere('reference_no', 'like', "%{$this->search}%")
                    ->orWhereHas('supplier', fn ($s) => $s->where('name', 'like', "%{$this->search}%"));
            }))
            ->when($this->status, fn ($q) => $q->where('status', $this->status))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('created_at', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('created_at', '<=', $this->dateTo))
            ->orderBy($this->getSortField(), $this->getSortDirection())
            ->paginate(15);

        $stats = $this->getStatistics();

        return view('livewire.purchases.index', [
            'purchases' => $purchases,
            'stats' => $stats,
        ]);
    }
}
