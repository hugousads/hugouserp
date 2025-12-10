<?php

declare(strict_types=1);

namespace App\Livewire\Inventory;

use App\Models\Product;
use App\Models\ProductStoreMapping;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
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
        }

        $this->loadStores();
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
        $this->resetForm();

        if ($id) {
            $mapping = ProductStoreMapping::findOrFail($id);
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
        $this->validate();

        $data = [
            'product_id' => $this->productId,
            'store_id' => $this->store_id,
            'external_id' => $this->external_id,
            'external_sku' => $this->external_sku ?: null,
        ];

        if ($this->editingId) {
            $mapping = ProductStoreMapping::findOrFail($this->editingId);
            $mapping->update($data);
            session()->flash('success', __('Mapping updated successfully'));
        } else {
            $exists = ProductStoreMapping::where('product_id', $this->productId)
                ->where('store_id', $this->store_id)
                ->exists();

            if ($exists) {
                $this->addError('store_id', __('This product is already mapped to this store'));

                return;
            }

            ProductStoreMapping::create($data);
            session()->flash('success', __('Mapping created successfully'));
        }

        $this->closeModal();
    }

    public function delete(int $id): void
    {
        $user = Auth::user();
        if (! $user || ! $user->can('inventory.products.delete')) {
            abort(403);
        }

        ProductStoreMapping::findOrFail($id)->delete();
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
