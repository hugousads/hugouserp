<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\Batches;

use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;

    public ?InventoryBatch $batch = null;
    public bool $isEditing = false;

    // Form fields
    public ?int $product_id = null;
    public ?int $warehouse_id = null;
    public string $batch_number = '';
    public string $manufacturing_date = '';
    public string $expiry_date = '';
    public string $quantity = '';
    public string $unit_cost = '';
    public string $supplier_batch_ref = '';
    public string $notes = '';

    protected function rules(): array
    {
        return [
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'batch_number' => 'required|string|max:255',
            'manufacturing_date' => 'nullable|date',
            'expiry_date' => 'nullable|date|after:manufacturing_date',
            'quantity' => 'required|numeric|min:0',
            'unit_cost' => 'required|numeric|min:0',
            'supplier_batch_ref' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
        ];
    }

    public function mount(?InventoryBatch $batch = null): void
    {
        $this->authorize('inventory.products.view');
        
        if ($batch && $batch->exists) {
            $this->isEditing = true;
            $this->batch = $batch;
            $this->fill($batch->toArray());
            $this->manufacturing_date = $batch->manufacturing_date?->format('Y-m-d') ?? '';
            $this->expiry_date = $batch->expiry_date?->format('Y-m-d') ?? '';
        } else {
            $this->batch_number = 'BATCH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -4));
        }
    }

    public function save(): mixed
    {
        $this->validate();

        $data = [
            'branch_id' => auth()->user()->branch_id,
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'batch_number' => $this->batch_number,
            'manufacturing_date' => $this->manufacturing_date ?: null,
            'expiry_date' => $this->expiry_date ?: null,
            'quantity' => $this->quantity,
            'unit_cost' => $this->unit_cost,
            'supplier_batch_ref' => $this->supplier_batch_ref,
            'notes' => $this->notes,
            'status' => 'active',
        ];

        if ($this->isEditing) {
            $this->batch->update($data);
            session()->flash('success', __('Batch updated successfully'));
        } else {
            InventoryBatch::create($data);
            session()->flash('success', __('Batch created successfully'));
        }

        $this->redirectRoute('app.inventory.batches.index', navigate: true);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $branchId = auth()->user()->branch_id;
        
        $products = Product::where('branch_id', $branchId)
            ->where('is_batch_tracked', true)
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::where('branch_id', $branchId)
            ->orderBy('name')
            ->get();

        return view('livewire.inventory.batches.form', [
            'products' => $products,
            'warehouses' => $warehouses,
        ]);
    }
}
