<?php

declare(strict_types=1);

namespace App\Livewire\Purchases;

use App\Livewire\Concerns\HandlesErrors;
use App\Models\Product;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Supplier;
use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;
    use HandlesErrors;

    public ?Purchase $purchase = null;

    public bool $editMode = false;

    public string $supplier_id = '';

    public string $warehouse_id = '';

    public string $reference_no = '';

    public string $status = 'draft';

    public string $currency = 'EGP';

    public string $notes = '';

    public string $supplier_notes = '';

    public string $internal_notes = '';

    public string $expected_delivery_date = '';

    public string $actual_delivery_date = '';

    public string $shipping_method = '';

    public float $discount_total = 0;

    public float $shipping_total = 0;

    public array $items = [];

    public string $productSearch = '';

    public array $searchResults = [];

    protected function rules(): array
    {
        return [
            'supplier_id' => 'required|exists:suppliers,id',
            'warehouse_id' => 'required|exists:warehouses,id',
            'reference_no' => 'nullable|string|max:100',
            'status' => 'required|in:draft,pending,posted,received,cancelled',
            'currency' => 'nullable|string|max:3',
            'notes' => 'nullable|string',
            'supplier_notes' => 'nullable|string|max:1000',
            'internal_notes' => 'nullable|string|max:1000',
            'expected_delivery_date' => 'nullable|date',
            'actual_delivery_date' => 'nullable|date',
            'shipping_method' => 'nullable|string|max:191',
            'discount_total' => 'nullable|numeric|min:0',
            'shipping_total' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.0001',
            'items.*.unit_cost' => 'required|numeric|min:0',
        ];
    }

    public function mount(?Purchase $purchase = null): void
    {
        $this->authorize('purchases.manage');

        if ($purchase && $purchase->exists) {
            $this->purchase = $purchase;
            $this->editMode = true;
            $this->supplier_id = (string) ($purchase->supplier_id ?? '');
            $this->warehouse_id = (string) ($purchase->warehouse_id ?? '');
            $this->reference_no = $purchase->reference_no ?? '';
            $this->status = $purchase->status ?? 'draft';
            $this->currency = $purchase->currency ?? 'EGP';
            $this->notes = $purchase->notes ?? '';
            $this->supplier_notes = $purchase->supplier_notes ?? '';
            $this->internal_notes = $purchase->internal_notes ?? '';
            $this->expected_delivery_date = $purchase->expected_delivery_date?->format('Y-m-d') ?? '';
            $this->actual_delivery_date = $purchase->actual_delivery_date?->format('Y-m-d') ?? '';
            $this->shipping_method = $purchase->shipping_method ?? '';
            $this->discount_total = (float) ($purchase->discount_total ?? 0);
            $this->shipping_total = (float) ($purchase->shipping_total ?? 0);

            $this->items = $purchase->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? '',
                'sku' => $item->product?->sku ?? '',
                'qty' => (float) $item->qty,
                'unit_cost' => (float) $item->unit_cost,
                'discount' => (float) ($item->discount ?? 0),
                'tax_rate' => (float) ($item->tax_rate ?? 0),
            ])->toArray();
        }
    }

    public function updatedProductSearch(): void
    {
        if (strlen($this->productSearch) < 2) {
            $this->searchResults = [];

            return;
        }

        $this->searchResults = Product::query()
            ->where(function ($q) {
                $q->where('name', 'like', "%{$this->productSearch}%")
                    ->orWhere('sku', 'like', "%{$this->productSearch}%");
            })
            ->limit(10)
            ->get(['id', 'name', 'sku', 'cost'])
            ->toArray();
    }

    public function addProduct(int $productId): void
    {
        $product = Product::find($productId);
        if (! $product) {
            return;
        }

        $existingIndex = collect($this->items)->search(fn ($item) => $item['product_id'] == $productId);

        if ($existingIndex !== false) {
            $this->items[$existingIndex]['qty'] += 1;
        } else {
            $this->items[] = [
                'id' => null,
                'product_id' => $product->id,
                'product_name' => $product->name,
                'sku' => $product->sku ?? '',
                'qty' => 1,
                'unit_cost' => (float) ($product->cost ?? 0),
                'discount' => 0,
                'tax_rate' => 0,
            ];
        }

        $this->productSearch = '';
        $this->searchResults = [];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function getSubTotalProperty(): float
    {
        return collect($this->items)->sum(function ($item) {
            return ($item['qty'] ?? 0) * ($item['unit_cost'] ?? 0) - ($item['discount'] ?? 0);
        });
    }

    public function getTaxTotalProperty(): float
    {
        return collect($this->items)->sum(function ($item) {
            $lineTotal = ($item['qty'] ?? 0) * ($item['unit_cost'] ?? 0) - ($item['discount'] ?? 0);

            return $lineTotal * (($item['tax_rate'] ?? 0) / 100);
        });
    }

    public function getGrandTotalProperty(): float
    {
        return $this->subTotal + $this->taxTotal - $this->discount_total + $this->shipping_total;
    }

    public function save(): void
    {
        $this->validate();

        $user = auth()->user();

        $this->handleOperation(
            operation: function () use ($user) {
                DB::transaction(function () use ($user) {
                    $purchaseData = [
                        'branch_id' => $user->branch_id ?? $user->branches()->first()?->id ?? 1,
                        'supplier_id' => $this->supplier_id,
                        'warehouse_id' => $this->warehouse_id,
                        'reference_no' => $this->reference_no,
                        'status' => $this->status,
                        'currency' => $this->currency,
                        'notes' => $this->notes,
                        'supplier_notes' => $this->supplier_notes,
                        'internal_notes' => $this->internal_notes,
                        'expected_delivery_date' => $this->expected_delivery_date ?: null,
                        'actual_delivery_date' => $this->actual_delivery_date ?: null,
                        'shipping_method' => $this->shipping_method,
                        'sub_total' => $this->subTotal,
                        'discount_total' => $this->discount_total,
                        'tax_total' => $this->taxTotal,
                        'shipping_total' => $this->shipping_total,
                        'grand_total' => $this->grandTotal,
                        'paid_total' => 0,
                        'due_total' => $this->grandTotal,
                        'updated_by' => $user->id,
                    ];

                    if ($this->editMode) {
                        $this->purchase->update($purchaseData);
                        $purchase = $this->purchase;
                        $purchase->items()->delete();
                    } else {
                        $purchaseData['created_by'] = $user->id;
                        $purchase = Purchase::create($purchaseData);
                    }

                    foreach ($this->items as $item) {
                        $lineTotal = ($item['qty'] * $item['unit_cost']) - ($item['discount'] ?? 0);
                        $lineTotal += $lineTotal * (($item['tax_rate'] ?? 0) / 100);

                        PurchaseItem::create([
                            'purchase_id' => $purchase->id,
                            'product_id' => $item['product_id'],
                            'branch_id' => $purchase->branch_id,
                            'qty' => $item['qty'],
                            'unit_cost' => $item['unit_cost'],
                            'discount' => $item['discount'] ?? 0,
                            'tax_rate' => $item['tax_rate'] ?? 0,
                            'line_total' => $lineTotal,
                            'created_by' => $user->id,
                        ]);
                    }
                });
            },
            successMessage: $this->editMode ? __('Purchase updated successfully') : __('Purchase created successfully'),
            redirectRoute: 'app.purchases.index'
        );
    }

    public function render()
    {
        $suppliers = Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $currencies = \App\Models\Currency::active()->ordered()->get(['code', 'name', 'symbol']);

        return view('livewire.purchases.form', [
            'suppliers' => $suppliers,
            'warehouses' => $warehouses,
            'currencies' => $currencies,
            'subTotal' => $this->subTotal,
            'taxTotal' => $this->taxTotal,
            'grandTotal' => $this->grandTotal,
        ])->layout('layouts.app', ['title' => $this->editMode ? __('Edit Purchase') : __('New Purchase')]);
    }
}
