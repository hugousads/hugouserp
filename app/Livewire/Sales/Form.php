<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Livewire\Concerns\HandlesErrors;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class Form extends Component
{
    use AuthorizesRequests;
    use HandlesErrors;

    public ?Sale $sale = null;

    public bool $editMode = false;

    public string $customer_id = '';

    public string $warehouse_id = '';

    public string $reference_no = '';

    public string $status = 'completed';

    public string $currency = 'EGP';

    public string $notes = '';

    public float $discount_total = 0;

    public float $shipping_total = 0;

    public array $items = [];

    public string $productSearch = '';

    public array $searchResults = [];

    public string $payment_method = 'cash';

    public float $payment_amount = 0;

    protected function rules(): array
    {
        return [
            'customer_id' => 'nullable|exists:customers,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'reference_no' => 'nullable|string|max:100',
            'status' => 'required|in:draft,pending,completed,cancelled,refunded',
            'currency' => 'nullable|string|max:3',
            'notes' => 'nullable|string',
            'discount_total' => 'nullable|numeric|min:0',
            'shipping_total' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'payment_method' => 'required|string|in:cash,card,bank_transfer,cheque',
            'payment_amount' => 'required|numeric|min:0',
        ];
    }

    public function mount(?Sale $sale = null): void
    {
        $this->authorize('sales.manage');

        if ($sale && $sale->exists) {
            $this->sale = $sale;
            $this->editMode = true;
            $this->customer_id = (string) ($sale->customer_id ?? '');
            $this->warehouse_id = (string) ($sale->warehouse_id ?? '');
            $this->reference_no = $sale->reference_no ?? '';
            $this->status = $sale->status ?? 'completed';
            $this->currency = $sale->currency ?? 'EGP';
            $this->notes = $sale->notes ?? '';
            $this->discount_total = (float) ($sale->discount_total ?? 0);
            $this->shipping_total = (float) ($sale->shipping_total ?? 0);

            $this->items = $sale->items->map(fn ($item) => [
                'id' => $item->id,
                'product_id' => $item->product_id,
                'product_name' => $item->product?->name ?? '',
                'sku' => $item->product?->sku ?? '',
                'qty' => (float) $item->qty,
                'unit_price' => (float) $item->unit_price,
                'discount' => (float) ($item->discount ?? 0),
                'tax_rate' => (float) ($item->tax_rate ?? 0),
            ])->toArray();

            if ($sale->payments->isNotEmpty()) {
                $firstPayment = $sale->payments->first();
                $this->payment_method = $firstPayment->payment_method ?? 'cash';
                $this->payment_amount = (float) ($firstPayment->amount ?? 0);
            }
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
            ->where('status', 'active')
            ->limit(10)
            ->get(['id', 'name', 'sku', 'default_price'])
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
                'unit_price' => (float) ($product->default_price ?? $product->price ?? 0),
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
            return ($item['qty'] ?? 0) * ($item['unit_price'] ?? 0) - ($item['discount'] ?? 0);
        });
    }

    public function getTaxTotalProperty(): float
    {
        return collect($this->items)->sum(function ($item) {
            $lineTotal = ($item['qty'] ?? 0) * ($item['unit_price'] ?? 0) - ($item['discount'] ?? 0);

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
                    $saleData = [
                        'branch_id' => $user->branch_id ?? $user->branches()->first()?->id ?? 1,
                        'customer_id' => $this->customer_id ?: null,
                        'warehouse_id' => $this->warehouse_id ?: null,
                        'reference_no' => $this->reference_no,
                        'status' => $this->status,
                        'currency' => $this->currency,
                        'notes' => $this->notes,
                        'sub_total' => $this->subTotal,
                        'discount_total' => $this->discount_total,
                        'tax_total' => $this->taxTotal,
                        'shipping_total' => $this->shipping_total,
                        'grand_total' => $this->grandTotal,
                        'paid_total' => $this->payment_amount,
                        'due_total' => $this->grandTotal - $this->payment_amount,
                        'updated_by' => $user->id,
                    ];

                    if ($this->editMode) {
                        $this->sale->update($saleData);
                        $sale = $this->sale;
                        $sale->items()->delete();
                        $sale->payments()->delete();
                    } else {
                        $saleData['created_by'] = $user->id;
                        $sale = Sale::create($saleData);
                    }

                    foreach ($this->items as $item) {
                        $lineTotal = ($item['qty'] * $item['unit_price']) - ($item['discount'] ?? 0);
                        $lineTotal += $lineTotal * (($item['tax_rate'] ?? 0) / 100);

                        SaleItem::create([
                            'sale_id' => $sale->id,
                            'product_id' => $item['product_id'],
                            'branch_id' => $sale->branch_id,
                            'qty' => $item['qty'],
                            'unit_price' => $item['unit_price'],
                            'discount' => $item['discount'] ?? 0,
                            'tax_rate' => $item['tax_rate'] ?? 0,
                            'line_total' => $lineTotal,
                            'created_by' => $user->id,
                        ]);
                    }

                    if ($this->payment_amount > 0) {
                        SalePayment::create([
                            'sale_id' => $sale->id,
                            'branch_id' => $sale->branch_id,
                            'payment_method' => $this->payment_method,
                            'amount' => $this->payment_amount,
                            'payment_date' => now(),
                            'created_by' => $user->id,
                        ]);
                    }
                });
            },
            successMessage: $this->editMode ? __('Sale updated successfully') : __('Sale created successfully'),
            redirectRoute: 'app.sales.index'
        );
    }

    public function render()
    {
        $customers = Customer::where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $warehouses = Warehouse::where('status', 'active')->orderBy('name')->get(['id', 'name']);
        $currencies = \App\Models\Currency::active()->ordered()->get(['code', 'name', 'symbol']);

        return view('livewire.sales.form', [
            'customers' => $customers,
            'warehouses' => $warehouses,
            'currencies' => $currencies,
            'subTotal' => $this->subTotal,
            'taxTotal' => $this->taxTotal,
            'grandTotal' => $this->grandTotal,
        ])->layout('layouts.app', ['title' => $this->editMode ? __('Edit Sale') : __('New Sale')]);
    }
}
