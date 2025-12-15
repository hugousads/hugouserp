<?php

declare(strict_types=1);

namespace App\Livewire\Warehouse\Adjustments;

use App\Models\Adjustment;
use App\Models\AdjustmentItem;
use App\Models\Product;
use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesRequests;

    public ?Adjustment $adjustment = null;

    public ?int $adjustmentId = null;

    public ?int $warehouseId = null;

    public string $reason = '';

    public string $note = '';

    public array $items = [];

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->authorize('warehouse.manage');
            $this->adjustmentId = $id;
            $this->adjustment = Adjustment::with('items.product')->findOrFail($id);
            
            // Check branch access
            $user = auth()->user();
            if ($user->branch_id && $this->adjustment->branch_id !== $user->branch_id) {
                abort(403, 'Unauthorized access to this branch data');
            }
            
            $this->loadAdjustment();
        } else {
            $this->authorize('warehouse.manage');
            // Initialize with empty item
            $this->items = [['product_id' => null, 'qty' => 0]];
        }
    }

    protected function loadAdjustment(): void
    {
        $this->warehouseId = $this->adjustment->warehouse_id;
        $this->reason = $this->adjustment->reason;
        $this->note = $this->adjustment->note ?? '';

        $this->items = $this->adjustment->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'qty' => $item->qty,
            ];
        })->toArray();
    }

    public function addItem(): void
    {
        $this->items[] = ['product_id' => null, 'qty' => 0];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items); // Re-index array
    }

    public function save(): mixed
    {
        $this->authorize('warehouse.manage');

        $this->validate([
            'warehouseId' => 'required|exists:warehouses,id',
            'reason' => 'required|string|max:255',
            'note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|not_in:0',
        ], [
            'items.required' => 'At least one item is required',
            'items.min' => 'At least one item is required',
            'items.*.product_id.required' => 'Product is required',
            'items.*.qty.required' => 'Quantity is required',
            'items.*.qty.not_in' => 'Quantity cannot be zero',
        ]);

        $user = auth()->user();

        $data = [
            'branch_id' => $user->branch_id ?? 1,
            'warehouse_id' => $this->warehouseId,
            'reason' => $this->reason,
            'note' => $this->note,
            'created_by' => $user->id,
        ];

        if ($this->adjustment) {
            $this->adjustment->update($data);
        } else {
            $this->adjustment = Adjustment::create($data);
        }

        // Save items
        $this->adjustment->items()->delete();

        foreach ($this->items as $item) {
            AdjustmentItem::create([
                'adjustment_id' => $this->adjustment->id,
                'product_id' => $item['product_id'],
                'qty' => $item['qty'],
            ]);
        }

        session()->flash('success', __('Adjustment saved successfully'));

        return redirect()->route('app.warehouse.adjustments.index');
    }

    public function render()
    {
        $user = auth()->user();

        $warehouses = Warehouse::when($user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $products = Product::when($user->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        return view('livewire.warehouse.adjustments.form', [
            'warehouses' => $warehouses,
            'products' => $products,
        ]);
    }
}
