<?php

namespace App\Livewire\Purchases\GRN;

use App\Models\GoodsReceivedNote;
use App\Models\GRNItem;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportRedirects\Redirector;

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
            $user = auth()->user();
            $this->grnId = $id;
            $this->grn = GoodsReceivedNote::with('items.product')
                ->when($user?->branch_id, fn ($q) => $q->where('branch_id', $user->branch_id))
                ->findOrFail($id);

            if ($user?->branch_id && $this->grn->branch_id !== $user->branch_id) {
                abort(403);
            }

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

        // Load existing GRN items with correct schema fields
        $this->items = $this->grn->items->map(function ($item) {
            return [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'purchase_item_id' => $item->purchase_item_id,
                'qty_ordered' => $item->qty_ordered ?? 0,
                'quantity_ordered' => $item->qty_ordered ?? 0,
                'qty_received' => $item->qty_received ?? 0,
                'quantity_received' => $item->qty_received ?? 0,
                'qty_rejected' => $item->qty_rejected ?? 0,
                'quantity_damaged' => $item->qty_rejected ?? 0,
                'quantity_defective' => 0,
                'qty_accepted' => $item->qty_accepted ?? ($item->qty_received - $item->qty_rejected),
                'unit_cost' => $item->unit_cost ?? 0,
                'quality_status' => $item->quality_status ?? 'good',
                'rejection_reason' => $item->rejection_reason ?? '',
                'notes' => $item->notes ?? '',
                'inspection_notes' => $item->notes ?? '',
                'uom' => $item->uom ?? '',
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
            $ordered = (string) ($item['quantity_ordered'] ?? '0');
            $received = (string) ($item['quantity_received'] ?? '0');
            $damaged = (string) ($item['quantity_damaged'] ?? '0');
            $defective = (string) ($item['quantity_defective'] ?? '0');

            // Use bccomp for precise quantity comparison
            if (bccomp($received, $ordered, 4) !== 0) {
                $discrepancies[] = "Item {$index}: Quantity mismatch";
            }

            // Use bccomp for quality issues check
            if (bccomp($damaged, '0', 4) > 0 || bccomp($defective, '0', 4) > 0) {
                $discrepancies[] = "Item {$index}: Quality issues";
            }
        }

        return $discrepancies;
    }

    /**
     * Save GRN items with schema-consistent fields
     */
    private function saveGRNItems(): void
    {
        $this->grn->items()->delete();

        foreach ($this->items as $item) {
            $qtyReceived = (string) ($item['qty_received'] ?? $item['quantity_received'] ?? '0');
            $damaged = (string) ($item['quantity_damaged'] ?? '0');
            $defective = (string) ($item['quantity_defective'] ?? '0');
            $qtyRejected = (string) ($item['qty_rejected'] ?? bcadd($damaged, $defective, 4));
            
            // Calculate accepted quantity with bcmath
            $qtyAcceptedCalc = bcsub($qtyReceived, $qtyRejected, 4);
            $qtyAccepted = bccomp($qtyAcceptedCalc, '0', 4) > 0 ? $qtyAcceptedCalc : '0';
            
            GRNItem::create([
                'grn_id' => $this->grn->id,
                'product_id' => $item['product_id'],
                'purchase_item_id' => $item['purchase_item_id'] ?? null,
                'qty_ordered' => $item['qty_ordered'] ?? $item['quantity_ordered'] ?? 0,
                'qty_received' => $qtyReceived,
                'qty_rejected' => $qtyRejected,
                'qty_accepted' => $qtyAccepted,
                'unit_cost' => $item['unit_cost'] ?? 0,
                'quality_status' => $item['quality_status'] ?? 'good',
                'rejection_reason' => $item['rejection_reason'] ?? '',
                'notes' => $item['notes'] ?? $item['inspection_notes'] ?? '',
                'uom' => $item['uom'] ?? '',
                'created_by' => auth()->id(),
            ]);
        }
    }

    /**
     * Validate GRN data
     */
    private function validateGRN(): void
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
    }

    /**
     * Save or update GRN record
     */
    private function saveGRNRecord(string $status): void
    {
        $data = [
            'purchase_id' => $this->purchaseId,
            'received_date' => $this->receivedDate,
            'inspected_by' => $this->inspectorId,
            'notes' => $this->notes,
            'status' => $status,
            'branch_id' => $this->grn?->branch_id ?? auth()->user()?->branch_id,
        ];

        if ($this->grn) {
            $this->grn->update($data);
        } else {
            $this->grn = GoodsReceivedNote::create($data);
        }
    }

    public function save(): Redirector|RedirectResponse|null
    {
        $this->validateGRN();
        DB::transaction(function () {
            $this->saveGRNRecord('draft');
            $this->saveGRNItems();
        });

        session()->flash('success', __('GRN saved successfully.'));

        return redirect()->route('app.purchases.grn.index');
    }

    public function submit(): Redirector|RedirectResponse|null
    {
        $this->validateGRN();
        DB::transaction(function () {
            $this->saveGRNRecord('pending_inspection');
            $this->saveGRNItems();
        });

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
