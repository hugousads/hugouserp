<?php

declare(strict_types=1);

namespace App\Livewire\Expenses\Categories;

use App\Models\ExpenseCategory;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use AuthorizesRequests;
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $nameAr = '';

    public string $description = '';

    public bool $isActive = true;

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

    public function openModal(): void
    {
        $this->resetForm();
        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    public function resetForm(): void
    {
        $this->editingId = null;
        $this->name = '';
        $this->nameAr = '';
        $this->description = '';
        $this->isActive = true;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $category = ExpenseCategory::find($id);
        if ($category) {
            $this->editingId = $id;
            $this->name = $category->name;
            $this->nameAr = $category->name_ar ?? '';
            $this->description = $category->description ?? '';
            $this->isActive = $category->is_active;
            $this->showModal = true;
        }
    }

    public function save(): void
    {
        $rules = [
            'name' => [
                'required',
                'string',
                'max:255',
                $this->editingId 
                    ? Rule::unique('expense_categories', 'name')->ignore($this->editingId) 
                    : Rule::unique('expense_categories', 'name'),
            ],
            'nameAr' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:1000',
        ];

        $this->validate($rules);

        $user = Auth::user();

        $data = [
            'name' => $this->name,
            'name_ar' => $this->nameAr ?: null,
            'description' => $this->description ?: null,
            'is_active' => $this->isActive,
        ];

        try {
            if ($this->editingId) {
                $category = ExpenseCategory::findOrFail($this->editingId);
                $category->update($data);
                session()->flash('success', __('Category updated successfully'));
            } else {
                $data['branch_id'] = $user?->branch_id;
                ExpenseCategory::create($data);
                session()->flash('success', __('Category created successfully'));
            }

            $this->closeModal();
            $this->resetPage();
        } catch (\Exception $e) {
            $this->addError('name', __('Failed to save category. Please try again.'));
        }
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
