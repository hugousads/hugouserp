<?php

declare(strict_types=1);

namespace App\Livewire\Warehouse\Transfers;

use App\Models\Product;
use App\Models\Transfer;
use App\Models\TransferItem;
use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesRequests;

    public ?Transfer $transfer = null;

    public ?int $transferId = null;

    public ?int $fromWarehouseId = null;

    public ?int $toWarehouseId = null;

    public string $status = 'pending';

    public string $note = '';

    public array $items = [];

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->authorize('warehouse.manage');
            $this->transferId = $id;
            $this->transfer = Transfer::with('items.product')->findOrFail($id);
            
            $user = auth()->user();
            if ($user->branch_id && $this->transfer->branch_id !== $user->branch_id) {
                abort(403);
            }
            
            $this->loadTransfer();
        } else {
            $this->authorize('warehouse.manage');
            $this->items = [['product_id' => null, 'qty' => 0]];
        }
    }

    protected function loadTransfer(): void
    {
        $this->fromWarehouseId = $this->transfer->from_warehouse_id;
        $this->toWarehouseId = $this->transfer->to_warehouse_id;
        $this->status = $this->transfer->status;
        $this->note = $this->transfer->note ?? '';

        $this->items = $this->transfer->items->map(function ($item) {
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
        $this->items = array_values($this->items);
    }

    public function save(): mixed
    {
        $this->authorize('warehouse.manage');

        $this->validate([
            'fromWarehouseId' => 'required|exists:warehouses,id',
            'toWarehouseId' => 'required|exists:warehouses,id|different:fromWarehouseId',
            'status' => 'required|in:pending,in_transit,completed,cancelled',
            'note' => 'nullable|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.01',
        ], [
            'toWarehouseId.different' => 'Destination warehouse must be different from source warehouse',
            'items.required' => 'At least one item is required',
            'items.min' => 'At least one item is required',
        ]);

        $user = auth()->user();

        $data = [
            'branch_id' => $user->branch_id ?? 1,
            'from_warehouse_id' => $this->fromWarehouseId,
            'to_warehouse_id' => $this->toWarehouseId,
            'status' => $this->status,
            'note' => $this->note,
            'created_by' => $user->id,
        ];

        if ($this->transfer) {
            $this->transfer->update($data);
        } else {
            $this->transfer = Transfer::create($data);
        }

        // Save items
        $this->transfer->items()->delete();

        foreach ($this->items as $item) {
            TransferItem::create([
                'transfer_id' => $this->transfer->id,
                'product_id' => $item['product_id'],
                'qty' => $item['qty'],
            ]);
        }

        session()->flash('success', __('Transfer saved successfully'));

        return redirect()->route('app.warehouse.transfers.index');
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

        return view('livewire.warehouse.transfers.form', [
            'warehouses' => $warehouses,
            'products' => $products,
        ]);
    }
}
