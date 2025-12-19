<?php

declare(strict_types=1);

namespace App\Livewire\Sales;

use App\Events\SaleCompleted;
use App\Livewire\Concerns\HandlesErrors;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\Warehouse;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    public string $customer_notes = '';

    public string $internal_notes = '';

    public string $delivery_date = '';

    public string $shipping_method = '';

    public string $tracking_number = '';

    public float $discount_total = 0;

    public float $shipping_total = 0;

    public array $items = [];

    public string $productSearch = '';

    public array $searchResults = [];

    public string $payment_method = 'cash';

    public float $payment_amount = 0;

    public bool $isSubmitting = false;

    /**
     * Get the user's branch ID with strict validation.
     * Returns null if user has no branch assigned.
     */
    protected function getUserBranchId(): ?int
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        // First check direct branch_id assignment
        if ($user->branch_id) {
            return (int) $user->branch_id;
        }

        // Then check branches relationship
        $firstBranch = $user->branches()->first();
        if ($firstBranch) {
            return (int) $firstBranch->id;
        }

        return null;
    }

    protected function rules(): array
    {
        $branchId = $this->getUserBranchId();

        return [
            'customer_id' => [
                'nullable',
                'exists:customers,id',
                function ($attribute, $value, $fail) use ($branchId) {
                    if ($value && $branchId) {
                        $customer = Customer::find($value);
                        if ($customer && $customer->branch_id && $customer->branch_id !== $branchId) {
                            $fail(__('The selected customer does not belong to your branch.'));
                        }
                    }
                },
            ],
            'warehouse_id' => [
                'nullable',
                'exists:warehouses,id',
                function ($attribute, $value, $fail) use ($branchId) {
                    if ($value && $branchId) {
                        $warehouse = Warehouse::find($value);
                        if ($warehouse && $warehouse->branch_id && $warehouse->branch_id !== $branchId) {
                            $fail(__('The selected warehouse does not belong to your branch.'));
                        }
                    }
                },
            ],
            'reference_no' => 'nullable|string|max:100',
            'status' => 'required|in:draft,pending,completed,cancelled,refunded',
            'currency' => 'nullable|string|max:3',
            'notes' => 'nullable|string',
            'customer_notes' => 'nullable|string|max:1000',
            'internal_notes' => 'nullable|string|max:1000',
            'delivery_date' => 'nullable|date',
            'shipping_method' => 'nullable|string|max:191',
            'tracking_number' => 'nullable|string|max:191',
            'discount_total' => 'nullable|numeric|min:0',
            'shipping_total' => 'nullable|numeric|min:0',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.qty' => 'required|numeric|min:0.0001',
            'items.*.unit_price' => 'required|numeric|min:0',
            'payment_method' => 'required|string|in:cash,card,bank_transfer,cheque',
            'payment_amount' => [
                'required',
                'numeric',
                'min:0',
                function ($attribute, $value, $fail) {
                    if ($value > $this->grandTotal) {
                        $fail(__('Payment amount cannot exceed the grand total.'));
                    }
                },
            ],
        ];
    }

    public function mount(?Sale $sale = null): void
    {
        $this->authorize('sales.manage');

        // BUG-001 Fix: Validate user has a branch assigned
        $branchId = $this->getUserBranchId();
        if (! $branchId) {
            abort(403, __('You must be assigned to a branch to create or edit sales.'));
        }

        if ($sale && $sale->exists) {
            // BUG-001 Fix: Verify sale belongs to user's branch
            if ($sale->branch_id !== $branchId) {
                abort(403, __('You do not have permission to edit this sale.'));
            }

            $this->sale = $sale;
            $this->editMode = true;
            $this->customer_id = (string) ($sale->customer_id ?? '');
            $this->warehouse_id = (string) ($sale->warehouse_id ?? '');
            $this->reference_no = $sale->reference_no ?? '';
            $this->status = $sale->status ?? 'completed';
            $this->currency = $sale->currency ?? 'EGP';
            $this->notes = $sale->notes ?? '';
            $this->customer_notes = $sale->customer_notes ?? '';
            $this->internal_notes = $sale->internal_notes ?? '';
            $this->delivery_date = $sale->delivery_date?->format('Y-m-d') ?? '';
            $this->shipping_method = $sale->shipping_method ?? '';
            $this->tracking_number = $sale->tracking_number ?? '';
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

        $branchId = $this->getUserBranchId();

        // BUG-003 Fix: Filter products by branch
        $query = Product::query()
            ->where(function ($q) {
                $q->where('name', 'like', "%{$this->productSearch}%")
                    ->orWhere('sku', 'like', "%{$this->productSearch}%");
            })
            ->where('status', 'active');

        // Filter by branch if user has one
        if ($branchId) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }

        $this->searchResults = $query
            ->limit(10)
            ->get(['id', 'name', 'sku', 'default_price'])
            ->toArray();
    }

    public function addProduct(int $productId): void
    {
        $branchId = $this->getUserBranchId();

        // BUG-003 Fix: Validate product belongs to user's branch
        $query = Product::query()->where('id', $productId);
        if ($branchId) {
            $query->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }

        $product = $query->first();
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
                'unit_price' => (float) ($product->default_price ?? 0),
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
        // BUG-010 Fix: Prevent double submission
        if ($this->isSubmitting) {
            return;
        }
        $this->isSubmitting = true;

        try {
            $this->validate();

            $user = auth()->user();

            // BUG-001 Fix: Strict branch validation
            $branchId = $this->getUserBranchId();
            if (! $branchId) {
                throw ValidationException::withMessages([
                    'branch' => [__('You must be assigned to a branch to create or edit sales.')],
                ]);
            }

            $this->handleOperation(
                operation: function () use ($user, $branchId) {
                    DB::transaction(function () use ($user, $branchId) {
                        // BUG-005 Fix: Ensure due_total is non-negative
                        $dueTotal = max(0, $this->grandTotal - $this->payment_amount);

                        $saleData = [
                            'branch_id' => $branchId,
                            'customer_id' => $this->customer_id ?: null,
                            'warehouse_id' => $this->warehouse_id ?: null,
                            'reference_no' => $this->reference_no,
                            'status' => $this->status,
                            'currency' => $this->currency,
                            'notes' => $this->notes,
                            'customer_notes' => $this->customer_notes,
                            'internal_notes' => $this->internal_notes,
                            'delivery_date' => $this->delivery_date ?: null,
                            'shipping_method' => $this->shipping_method,
                            'tracking_number' => $this->tracking_number,
                            'sub_total' => $this->subTotal,
                            'discount_total' => $this->discount_total,
                            'tax_total' => $this->taxTotal,
                            'shipping_total' => $this->shipping_total,
                            'grand_total' => $this->grandTotal,
                            'paid_total' => $this->payment_amount,
                            'due_total' => $dueTotal,
                            'updated_by' => $user->id,
                        ];

                        if ($this->editMode) {
                            // BUG-008 Fix: Verify branch hasn't changed on edit
                            if ($this->sale->branch_id !== $branchId) {
                                throw ValidationException::withMessages([
                                    'branch' => [__('You do not have permission to edit this sale.')],
                                ]);
                            }

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

                        // BUG-005 Fix: Update payment status after payment
                        $sale->updatePaymentStatus();

                        // BUG-006 Fix: Dispatch SaleCompleted event for inventory updates
                        // Only dispatch for completed sales to trigger stock deduction
                        if ($sale->status === 'completed') {
                            event(new SaleCompleted($sale->fresh()));
                        }
                    });
                },
                successMessage: $this->editMode ? __('Sale updated successfully') : __('Sale created successfully'),
                redirectRoute: 'app.sales.index'
            );
        } finally {
            $this->isSubmitting = false;
        }
    }

    public function render()
    {
        $branchId = $this->getUserBranchId();

        // BUG-003 Fix: Filter customers by branch
        $customersQuery = Customer::where('is_active', true)->orderBy('name');
        if ($branchId) {
            $customersQuery->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }
        // BUG-009 Fix: Limit results for performance
        $customers = $customersQuery->limit(100)->get(['id', 'name']);

        // BUG-003 Fix: Filter warehouses by branch
        $warehousesQuery = Warehouse::where('status', 'active')->orderBy('name');
        if ($branchId) {
            $warehousesQuery->where(function ($q) use ($branchId) {
                $q->where('branch_id', $branchId)
                    ->orWhereNull('branch_id');
            });
        }
        // BUG-009 Fix: Limit results for performance
        $warehouses = $warehousesQuery->limit(50)->get(['id', 'name']);

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
