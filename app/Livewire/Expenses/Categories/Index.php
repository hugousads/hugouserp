<?php

declare(strict_types=1);

namespace App\Livewire\Expenses\Categories;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    protected $queryString = ['search'];

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->can('expenses.manage')) {
            abort(403);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $categories = ExpenseCategory::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('name_ar', 'like', "%{$this->search}%"))
            ->withCount('expenses')
            ->orderBy('name')
            ->paginate(20);

        return view('livewire.expenses.categories.index', [
            'categories' => $categories,
        ]);
    }

    public function delete(int $id): void
    {
        $this->authorize('expenses.manage');
        
        $category = ExpenseCategory::find($id);
        if ($category) {
            if ($category->expenses()->count() > 0) {
                session()->flash('error', __('Cannot delete category with expenses'));
                return;
            }
            $category->delete();
            session()->flash('success', __('Category deleted successfully'));
        }
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('expenses.manage');
        
        $category = ExpenseCategory::find($id);
        if ($category) {
            $category->update(['is_active' => ! $category->is_active]);
        }
    }
}
