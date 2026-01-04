<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\ProductStoreMappings;

use App\Models\Product;
use App\Models\ProductStoreMapping;
use App\Models\Store;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Form extends Component
{
    public ?int $mappingId = null;

    public ?int $productId = null;

    public ?Product $product = null;

    public ?int $store_id = null;

    public string $external_id = '';

    public string $external_sku = '';

    public array $stores = [];

    public function mount(?int $product = null, ?int $mapping = null): void
    {
        $user = Auth::user();

        if (! $user || ! $user->can('inventory.products.view')) {
            abort(403);
        }

        $this->productId = $product;

        if ($product) {
            $this->product = Product::findOrFail($product);
            $this->authorizeProductBranch($this->product);
        }

        if ($mapping) {
            $this->mappingId = $mapping;
            $this->loadMapping();
        }

        $this->loadStores();
    }

    protected function requireUserBranch(): int
    {
        $user = Auth::user();

        if (! $user || ! $user->branch_id) {
            abort(403, __('User must be assigned to a branch to perform this action'));
        }

        return $user->branch_id;
    }

    protected function authorizeProductBranch(Product $product): void
    {
        $userBranchId = $this->requireUserBranch();

        if ($product->branch_id !== $userBranchId) {
            abort(403, __('Access denied to product from another branch'));
        }
    }

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

    protected function loadMapping(): void
    {
        $mapping = ProductStoreMapping::with('product')->findOrFail($this->mappingId);

        if ($mapping->product) {
            $this->authorizeProductBranch($mapping->product);
        }

        $this->store_id = $mapping->store_id;
        $this->external_id = $mapping->external_id ?? '';
        $this->external_sku = $mapping->external_sku ?? '';
    }

    protected function rules(): array
    {
        return [
            'store_id' => 'required|exists:stores,id',
            'external_id' => 'required|string|max:255',
            'external_sku' => 'nullable|string|max:255',
        ];
    }

    public function save(): mixed
    {
        if ($this->mappingId) {
            $this->authorizeAction('inventory.products.update');
        } else {
            $this->authorizeAction('inventory.products.create');
        }

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

        if ($this->mappingId) {
            $mapping = ProductStoreMapping::with('product')->findOrFail($this->mappingId);

            if ($mapping->product) {
                $this->authorizeProductBranch($mapping->product);
            }

            $mapping->update($data);
            session()->flash('success', __('Mapping updated successfully'));
        } else {
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
            } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                $this->addError('store_id', __('This product is already mapped to this store'));

                return null;
            } catch (\Illuminate\Database\QueryException $e) {
                // Fallback for older Laravel or PDO drivers that don't throw UniqueConstraintViolationException
                $errorCode = $e->errorInfo[1] ?? 0;
                if ($errorCode === 1062 || $errorCode === 19 || $errorCode === 2627) {
                    $this->addError('store_id', __('This product is already mapped to this store'));

                    return null;
                }
                throw $e;
            }

            session()->flash('success', __('Mapping created successfully'));
        }

        $this->redirectRoute('app.inventory.products.store-mappings', ['product' => $this->productId], navigate: true);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        return view('livewire.inventory.product-store-mappings.form');
    }
}
