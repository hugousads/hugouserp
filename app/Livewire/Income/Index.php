<?php

declare(strict_types=1);

namespace App\Livewire\Income;

use App\Models\Income;
use App\Models\IncomeCategory;
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
    public string $search = '';

    public function mount(): void
    {
        $this->authorize('income.view');
    }

    #[Url]
    public string $categoryId = '';

    #[Url]
    public string $dateFrom = '';

    #[Url]
    public string $dateTo = '';

    public string $sortField = 'income_date';

    public string $sortDirection = 'desc';

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if ($this->sortField === $field) {
            $this->sortDirection = $this->sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            $this->sortField = $field;
            $this->sortDirection = 'asc';
        }
    }

    public function delete(int $id): void
    {
        $this->authorize('income.delete');
        Income::findOrFail($id)->delete();
        Cache::forget('income_stats_'.(auth()->user()?->branch_id ?? 'all'));
        session()->flash('success', __('Income deleted successfully'));
    }

    public function getStatistics(): array
    {
        $user = auth()->user();
        $cacheKey = 'income_stats_'.($user?->branch_id ?? 'all');

        return Cache::remember($cacheKey, 300, function () use ($user) {
            $query = Income::query();

            if ($user && $user->branch_id) {
                $query->where('branch_id', $user->branch_id);
            }

            $thisMonth = Income::query()
                ->when($user && $user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
                ->whereMonth('income_date', now()->month)
                ->whereYear('income_date', now()->year)
                ->sum('amount');

            return [
                'total_count' => $query->count(),
                'total_amount' => $query->sum('amount'),
                'this_month' => $thisMonth,
                'avg_amount' => $query->count() > 0 ? $query->avg('amount') : 0,
            ];
        });
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $user = auth()->user();

        $incomes = Income::query()
            ->with(['category', 'branch', 'creator'])
            ->when($user && $user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->when($this->search, fn ($q) => $q->where(function ($query) {
                $query->where('description', 'like', "%{$this->search}%")
                    ->orWhere('reference_number', 'like', "%{$this->search}%");
            }))
            ->when($this->categoryId, fn ($q) => $q->where('category_id', $this->categoryId))
            ->when($this->dateFrom, fn ($q) => $q->whereDate('income_date', '>=', $this->dateFrom))
            ->when($this->dateTo, fn ($q) => $q->whereDate('income_date', '<=', $this->dateTo))
            ->orderBy($this->sortField, $this->sortDirection)
            ->paginate(15);

        $categories = Cache::remember('income_categories', 600, fn () => IncomeCategory::all());
        $stats = $this->getStatistics();

        return view('livewire.income.index', [
            'incomes' => $incomes,
            'categories' => $categories,
            'stats' => $stats,
        ]);
    }
}
