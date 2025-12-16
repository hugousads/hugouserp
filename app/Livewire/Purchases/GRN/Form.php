<?php

namespace App\Livewire\Purchases\GRN;

use App\Models\GoodsReceivedNote;
use App\Models\GRNItem;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.app')]
class Form extends Component
{
    use AuthorizesRequests;

    public ?GoodsReceivedNote $grn = null;

    public ?int $grnId = null;

    public ?int $purchaseId = null;

    public ?string $receivedDate = null;

    public ?int $inspectorId = null;

    public ?string $notes = null;

    public array $items = [];

    public function mount(?int $id = null): void
    {
        if ($id) {
            $this->authorize('grn.update');
            $this->grnId = $id;
            $this->grn = GoodsReceivedNote::with('items.product')->findOrFail($id);
            $this->loadGRN();
        } else {
            $this->authorize('grn.create');
            $this->receivedDate = date('Y-m-d');
        }
    }

    protected function loadGRN(): void
    {
        $this->purchaseId = $this->grn->purchase_id;
        $this->receivedDate = $this->grn->received_date->format('Y-m-d');
        $this->inspectorId = $this->grn->inspected_by;
        $this->notes = $this->grn->notes;

        $this->items = $this->grn->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'quantity_ordered' => $item->quantity_ordered,
                'quantity_received' => $item->quantity_received,
                'quality_status' => $item->quality_status ?? 'good',
                'quantity_damaged' => $item->quantity_damaged ?? 0,
                'quantity_defective' => $item->quantity_defective ?? 0,
                'inspection_notes' => $item->inspection_notes,
            ];
        })->toArray();
    }

    public function loadPOItems(): void
    {
        if (! $this->purchaseId) {
            return;
        }

        $purchase = Purchase::with('items.product')->findOrFail($this->purchaseId);

        $this->items = $purchase->items->map(function ($item) {
            return [
                'product_id' => $item->product_id,
                'product_name' => $item->product->name ?? '',
                'quantity_ordered' => $item->qty,
                'quantity_received' => $item->qty, // Default to ordered quantity
                'quality_status' => 'good',
                'quantity_damaged' => 0,
                'quantity_defective' => 0,
                'inspection_notes' => '',
            ];
        })->toArray();
    }

    public function calculateDiscrepancies(): array
    {
        $discrepancies = [];

        foreach ($this->items as $index => $item) {
            $ordered = (float) ($item['quantity_ordered'] ?? 0);
            $received = (float) ($item['quantity_received'] ?? 0);
            $damaged = (float) ($item['quantity_damaged'] ?? 0);
            $defective = (float) ($item['quantity_defective'] ?? 0);

            if ($received != $ordered) {
                $discrepancies[] = "Item {$index}: Quantity mismatch";
            }

            if ($damaged > 0 || $defective > 0) {
                $discrepancies[] = "Item {$index}: Quality issues";
            }
        }

        return $discrepancies;
    }

    public function save(): ?RedirectResponse
    {
        $this->validate([
            'purchaseId' => 'required|exists:purchases,id',
            'receivedDate' => 'required|date|before_or_equal:today',
            'inspectorId' => 'nullable|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_received' => 'required|numeric|min:0',
            'items.*.quality_status' => 'required|in:good,damaged,defective',
            'items.*.quantity_damaged' => 'nullable|numeric|min:0',
            'items.*.quantity_defective' => 'nullable|numeric|min:0',
        ]);

        $data = [
            'purchase_id' => $this->purchaseId,
            'received_date' => $this->receivedDate,
            'inspected_by' => $this->inspectorId,
            'notes' => $this->notes,
            'status' => 'draft',
        ];

        if ($this->grn) {
            $this->grn->update($data);
        } else {
            $this->grn = GoodsReceivedNote::create($data);
        }

        // Save items
        $this->grn->items()->delete();

        foreach ($this->items as $item) {
            GoodsReceivedNoteItem::create([
                'goods_received_note_id' => $this->grn->id,
                'product_id' => $item['product_id'],
                'quantity_ordered' => $item['quantity_ordered'],
                'quantity_received' => $item['quantity_received'],
                'quality_status' => $item['quality_status'],
                'quantity_damaged' => $item['quantity_damaged'] ?? 0,
                'quantity_defective' => $item['quantity_defective'] ?? 0,
                'inspection_notes' => $item['inspection_notes'] ?? null,
            ]);
        }

        session()->flash('success', __('GRN saved successfully.'));

        return redirect()->route('app.purchases.grn.index');
    }

    public function submit(): ?RedirectResponse
    {
        // First validate and save
        $this->validate([
            'purchaseId' => 'required|exists:purchases,id',
            'receivedDate' => 'required|date|before_or_equal:today',
            'inspectorId' => 'nullable|exists:users,id',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity_received' => 'required|numeric|min:0',
            'items.*.quality_status' => 'required|in:good,damaged,defective',
            'items.*.quantity_damaged' => 'nullable|numeric|min:0',
            'items.*.quantity_defective' => 'nullable|numeric|min:0',
        ]);

        $data = [
            'purchase_id' => $this->purchaseId,
            'received_date' => $this->receivedDate,
            'inspected_by' => $this->inspectorId,
            'notes' => $this->notes,
            'status' => 'pending_inspection',
        ];

        if ($this->grn) {
            $this->grn->update($data);
        } else {
            $this->grn = GoodsReceivedNote::create($data);
        }

        // Save items
        $this->grn->items()->delete();

        foreach ($this->items as $item) {
            GRNItem::create([
                'grn_id' => $this->grn->id,
                'product_id' => $item['product_id'],
                'qty_ordered' => $item['quantity_ordered'],
                'qty_received' => $item['quantity_received'],
                'qty_accepted' => $item['quantity_received'] - ($item['quantity_damaged'] ?? 0) - ($item['quantity_defective'] ?? 0),
                'qty_rejected' => ($item['quantity_damaged'] ?? 0) + ($item['quantity_defective'] ?? 0),
                'notes' => $item['inspection_notes'] ?? null,
            ]);
        }

        session()->flash('success', __('GRN submitted for inspection.'));

        return redirect()->route('app.purchases.grn.index');
    }

    public function render()
    {
        $purchases = Purchase::where('status', 'approved')
            ->with('supplier')
            ->get();

        $inspectors = User::permission('purchases.manage')->get();

        return view('livewire.purchases.grn.form', [
            'purchases' => $purchases,
            'inspectors' => $inspectors,
        ]);
    }
}
