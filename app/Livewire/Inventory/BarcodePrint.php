<?php

declare(strict_types=1);

namespace App\Livewire\Inventory;

use App\Models\Product;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class BarcodePrint extends Component
{
    public string $search = '';

    public array $selectedProducts = [];

    public array $printQuantities = [];

    public string $labelSize = 'medium';

    public bool $showPrice = true;

    public bool $showName = true;

    public bool $showSku = true;

    public string $barcodeType = 'barcode';

    public bool $showPreview = false;

    public function mount(): void
    {
        $user = Auth::user();
        if (! $user || ! $user->can('inventory.products.view')) {
            abort(403);
        }
    }

    public function render()
    {
        $products = Product::query()
            ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%")
                ->orWhere('sku', 'like', "%{$this->search}%")
                ->orWhere('barcode', 'like', "%{$this->search}%"))
            ->orderBy('name')
            ->limit(50)
            ->get();

        $selectedProductDetails = Product::whereIn('id', $this->selectedProducts)->get();

        return view('livewire.inventory.barcode-print', [
            'products' => $products,
            'selectedProductDetails' => $selectedProductDetails,
        ]);
    }

    public function addProduct(int $productId): void
    {
        if (! in_array($productId, $this->selectedProducts)) {
            $this->selectedProducts[] = $productId;
            $this->printQuantities[$productId] = 1;
        }
    }

    public function removeProduct(int $productId): void
    {
        $this->selectedProducts = array_values(array_filter($this->selectedProducts, fn ($id) => $id !== $productId));
        unset($this->printQuantities[$productId]);
    }

    public function updateQuantity(int $productId, int $qty): void
    {
        $this->printQuantities[$productId] = max(1, min(100, $qty));
    }

    public function clearAll(): void
    {
        $this->selectedProducts = [];
        $this->printQuantities = [];
    }

    public function togglePreview(): void
    {
        $this->showPreview = ! $this->showPreview;
    }

    public function getTotalLabelsProperty(): int
    {
        return array_sum($this->printQuantities);
    }
}
