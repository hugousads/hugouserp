<?php

declare(strict_types=1);

namespace App\Livewire\Inventory;

use App\Models\Product;
use App\Models\ProductStoreMapping;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

class ProductStoreMappings extends Component
{
    use WithPagination;

    protected string $paginationTheme = 'tailwind';

    public ?int $productId = null;

    public ?Product $product = null;

    public string $search = '';

    public ?int $storeFilter = null;

    public bool $showModal = false;

    public ?int $editingId = null;

    public ?int $store_id = null;

    public string $external_id = '';

    public string $external_sku = '';

    public array $stores = [];

    public function mount(?int $productId = null): void
    {
        $user = Auth::user();

        if (! $user || ! $user->can('inventory.products.view')) {
            abort(403);
        }

        $this->productId = $productId;

        if ($productId) {
            $this->product = Product::findOrFail($productId);
            
            // Enforce branch scoping: user must have access to product's branch
            $this->authorizeProductBranch($this->product);
        }

        $this->loadStores();
    }

    /**
     * Verify the user has a valid branch assignment.
     */
    protected function requireUserBranch(): int
    {
        $user = Auth::user();
        
        if (! $user || ! $user->branch_id) {
            abort(403, __('User must be assigned to a branch to perform this action'));
        }

        return $user->branch_id;
    }

    /**
     * Verify the product belongs to the user's branch.
     */
    protected function authorizeProductBranch(Product $product): void
    {
        $userBranchId = $this->requireUserBranch();
        
        if ($product->branch_id !== $userBranchId) {
            abort(403, __('Access denied to product from another branch'));
        }
    }

    /**
     * Check if the user has the required permission.
     */
    protected function authorizeAction(string $permission): void
    {
        $user = Auth::user();
        
        if (! $user || ! $user->can($permission)) {
            abort(403, __('Unauthorized'));
        }
    }

    protected function loadStores(): void
    {
        $query = Store::where('is_active', true);

        if ($this->product && $this->product->branch_id) {
            $query->where(function ($q) {
                $q->where('branch_id', $this->product->branch_id)
                    ->orWhereNull('branch_id');
            });
        }

        $this->stores = $query->orderBy('name')->get(['id', 'name', 'type'])->toArray();
    }

    public function updatingSearch(): void
    {
        $this->resetPage();
    }

    public function openModal(?int $id = null): void
    {
        // Require create or update permission based on action
        if ($id) {
            $this->authorizeAction('inventory.products.update');
        } else {
            $this->authorizeAction('inventory.products.create');
        }
        
        $this->resetForm();

        if ($id) {
            $mapping = ProductStoreMapping::with('product')->findOrFail($id);
            
            // Ensure the mapping belongs to the user's branch
            if ($mapping->product) {
                $this->authorizeProductBranch($mapping->product);
            }
            
            $this->editingId = $mapping->id;
            $this->store_id = $mapping->store_id;
            $this->external_id = $mapping->external_id ?? '';
            $this->external_sku = $mapping->external_sku ?? '';
        }

        $this->showModal = true;
    }

    public function closeModal(): void
    {
        $this->showModal = false;
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->editingId = null;
        $this->store_id = null;
        $this->external_id = '';
        $this->external_sku = '';
        $this->resetValidation();
    }

    protected function rules(): array
    {
        return [
            'store_id' => 'required|exists:stores,id',
            'external_id' => 'required|string|max:255',
            'external_sku' => 'nullable|string|max:255',
        ];
    }

    public function save(): void
    {
        // Check authorization based on create vs update
        if ($this->editingId) {
            $this->authorizeAction('inventory.products.update');
        } else {
            $this->authorizeAction('inventory.products.create');
        }
        
        // Ensure user has a branch and product belongs to their branch
        if ($this->product) {
            $this->authorizeProductBranch($this->product);
        } else {
            abort(422, __('Product is required'));
        }
        
        $this->validate();

        $data = [
            'product_id' => $this->productId,
            'store_id' => $this->store_id,
            'external_id' => $this->external_id,
            'external_sku' => $this->external_sku ?: null,
        ];

        if ($this->editingId) {
            $mapping = ProductStoreMapping::with('product')->findOrFail($this->editingId);
            
            // Re-verify branch ownership before update
            if ($mapping->product) {
                $this->authorizeProductBranch($mapping->product);
            }
            
            $mapping->update($data);
            session()->flash('success', __('Mapping updated successfully'));
        } else {
            // Use firstOrCreate to handle concurrency (BUG-004)
            // The database has a unique constraint on (product_id, store_id)
            try {
                DB::transaction(function () use ($data) {
                    ProductStoreMapping::firstOrCreate(
                        [
                            'product_id' => $data['product_id'],
                            'store_id' => $data['store_id'],
                        ],
                        [
                            'external_id' => $data['external_id'],
                            'external_sku' => $data['external_sku'],
                        ]
                    );
                });
            } catch (\Illuminate\Database\QueryException $e) {
                // Handle unique constraint violation gracefully
                if (str_contains($e->getMessage(), 'UNIQUE constraint failed') || 
                    str_contains($e->getMessage(), 'Duplicate entry')) {
                    $this->addError('store_id', __('This product is already mapped to this store'));
                    return;
                }
                throw $e;
            }
            
            session()->flash('success', __('Mapping created successfully'));
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $this->authorizeAction('inventory.products.delete');
        
        $mapping = ProductStoreMapping::with('product')->findOrFail($id);
        
        // Verify branch ownership before delete
        if ($mapping->product) {
            $this->authorizeProductBranch($mapping->product);
        }

        $mapping->delete();
        session()->flash('success', __('Mapping deleted successfully'));
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $query = ProductStoreMapping::with('store');

        if ($this->productId) {
            $query->where('product_id', $this->productId);
        }

        if ($this->search) {
            $query->where(function ($q) {
                $q->where('external_id', 'like', '%'.$this->search.'%')
                    ->orWhere('external_sku', 'like', '%'.$this->search.'%');
            });
        }

        if ($this->storeFilter) {
            $query->where('store_id', $this->storeFilter);
        }

        $mappings = $query->orderByDesc('created_at')->paginate(15);

        return view('livewire.inventory.product-store-mappings', [
            'mappings' => $mappings,
        ]);
    }
}
