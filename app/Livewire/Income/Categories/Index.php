<?php

declare(strict_types=1);

namespace App\Livewire\Income\Categories;

use App\Models\IncomeCategory;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    protected $queryString = ['search'];

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->can('income.manage')) {
            abort(403);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $categories = IncomeCategory::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('name_ar', 'like', "%{$this->search}%"))
            ->withCount('incomes')
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.income.categories.index', [
            'categories' => $categories,
        ]);
    }

    public function delete(int $id): void
    {
        $category = IncomeCategory::find($id);
        if ($category) {
            if ($category->incomes()->count() > 0) {
                session()->flash('error', __('Cannot delete category with incomes'));
                return;
            }
            $category->delete();
            session()->flash('success', __('Category deleted successfully'));
        }
    }

    public function toggleActive(int $id): void
    {
        $category = IncomeCategory::find($id);
        if ($category) {
            $category->update(['is_active' => ! $category->is_active]);
        }
    }
}
