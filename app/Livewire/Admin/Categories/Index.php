<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Categories;

use App\Models\ProductCategory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.app')]
class Index extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $showModal = false;

    public ?int $editingId = null;

    public string $name = '';

    public string $nameAr = '';

    public ?int $parentId = null;

    public string $description = '';

    public int $sortOrder = 0;

    public bool $isActive = true;

    protected $queryString = ['search'];

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->can('inventory.categories.view')) {
            abort(403);
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $categories = ProductCategory::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('name_ar', 'like', "%{$this->search}%"))
            ->withCount('products')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(20);

        $parentCategories = ProductCategory::roots()->active()->orderBy('name')->get();

        return view('livewire.admin.categories.index', [
            'categories' => $categories,
            'parentCategories' => $parentCategories,
        ]);
    }

    public function openModal(): void
    {
        $this->resetForm();
        $this->sortOrder = ProductCategory::max('sort_order') + 1;
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
        $this->parentId = null;
        $this->description = '';
        $this->sortOrder = 0;
        $this->isActive = true;
        $this->resetValidation();
    }

    public function edit(int $id): void
    {
        $category = ProductCategory::find($id);
        if ($category) {
            $this->editingId = $id;
            $this->name = $category->name;
            $this->nameAr = $category->name_ar ?? '';
            $this->parentId = $category->parent_id;
            $this->description = $category->description ?? '';
            $this->sortOrder = $category->sort_order;
            $this->isActive = $category->is_active;
            $this->showModal = true;
        }
    }

    public function save(): void
    {
        $this->validate([
            'name' => 'required|string|max:255',
            'nameAr' => 'nullable|string|max:255',
            'parentId' => 'nullable|exists:product_categories,id',
            'description' => 'nullable|string|max:1000',
            'sortOrder' => 'integer|min:0',
        ]);

        $user = Auth::user();

        $data = [
            'name' => $this->name,
            'name_ar' => $this->nameAr ?: null,
            'parent_id' => $this->parentId,
            'description' => $this->description ?: null,
            'sort_order' => $this->sortOrder,
            'is_active' => $this->isActive,
            'updated_by' => $user?->id,
        ];

        if ($this->editingId) {
            $category = ProductCategory::find($this->editingId);
            if ($category) {
                if ($this->parentId === $this->editingId) {
                    $this->addError('parentId', __('A category cannot be its own parent'));

                    return;
                }
                $category->update($data);
                session()->flash('success', __('Category updated successfully'));
            }
        } else {
            $data['slug'] = Str::slug($this->name).'-'.Str::random(4);
            $data['created_by'] = $user?->id;
            $data['branch_id'] = $user?->branch_id;
            ProductCategory::create($data);
            session()->flash('success', __('Category created successfully'));
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $category = ProductCategory::find($id);
        if ($category) {
            if ($category->products()->count() > 0) {
                session()->flash('error', __('Cannot delete category with products'));

                return;
            }
            if ($category->children()->count() > 0) {
                session()->flash('error', __('Cannot delete category with subcategories'));

                return;
            }
            $category->delete();
            session()->flash('success', __('Category deleted successfully'));
        }
    }

    public function toggleActive(int $id): void
    {
        $category = ProductCategory::find($id);
        if ($category) {
            $category->update(['is_active' => ! $category->is_active]);
        }
    }
}
