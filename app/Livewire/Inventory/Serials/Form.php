<?php

declare(strict_types=1);

namespace App\Livewire\Inventory\Serials;

use App\Models\InventorySerial;
use App\Models\InventoryBatch;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;

    public ?InventorySerial $serial = null;
    public bool $isEditing = false;

    // Form fields
    public ?int $product_id = null;
    public ?int $warehouse_id = null;
    public ?int $batch_id = null;
    public string $serial_number = '';
    public string $unit_cost = '';
    public string $warranty_start = '';
    public string $warranty_end = '';
    public string $notes = '';

    protected function rules(): array
    {
        $uniqueRule = 'unique:inventory_serials,serial_number';
        if ($this->isEditing && $this->serial) {
            $uniqueRule .= ',' . $this->serial->id;
        }

        return [
            'product_id' => 'required|exists:products,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'batch_id' => 'nullable|exists:inventory_batches,id',
            'serial_number' => ['required', 'string', 'max:255', $uniqueRule],
            'unit_cost' => 'required|numeric|min:0',
            'warranty_start' => 'nullable|date',
            'warranty_end' => 'nullable|date|after:warranty_start',
            'notes' => 'nullable|string',
        ];
    }

    public function mount(?InventorySerial $serial = null): void
    {
        $this->authorize('inventory.products.view');
        
        if ($serial && $serial->exists) {
            $this->isEditing = true;
            $this->serial = $serial;
            $this->fill($serial->toArray());
            $this->warranty_start = $serial->warranty_start?->format('Y-m-d') ?? '';
            $this->warranty_end = $serial->warranty_end?->format('Y-m-d') ?? '';
        } else {
            $this->serial_number = 'SN-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        }
    }

    public function save(): mixed
    {
        $this->validate();

        $data = [
            'branch_id' => auth()->user()->branch_id,
            'product_id' => $this->product_id,
            'warehouse_id' => $this->warehouse_id,
            'batch_id' => $this->batch_id,
            'serial_number' => $this->serial_number,
            'unit_cost' => $this->unit_cost,
            'warranty_start' => $this->warranty_start ?: null,
            'warranty_end' => $this->warranty_end ?: null,
            'notes' => $this->notes,
            'status' => 'in_stock',
        ];

        if ($this->isEditing) {
            $this->serial->update($data);
            session()->flash('success', __('Serial number updated successfully'));
        } else {
            InventorySerial::create($data);
            session()->flash('success', __('Serial number created successfully'));
        }

        $this->redirectRoute('app.inventory.serials.index', navigate: true);
    }

    #[Layout('layouts.app')]
    public function render()
    {
        $branchId = auth()->user()->branch_id;
        
        $products = Product::where('branch_id', $branchId)
            ->where('is_serialized', true)
            ->orderBy('name')
            ->get();

        $warehouses = Warehouse::where('branch_id', $branchId)
            ->orderBy('name')
            ->get();

        $batches = [];
        if ($this->product_id) {
            $batches = InventoryBatch::where('branch_id', $branchId)
                ->where('product_id', $this->product_id)
                ->where('status', 'active')
                ->orderBy('batch_number')
                ->get();
        }

        return view('livewire.inventory.serials.form', [
            'products' => $products,
            'warehouses' => $warehouses,
            'batches' => $batches,
        ]);
    }
}
