<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductStoreMapping;
use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class OrdersController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $store = $this->getStore($request);

        $query = Sale::query()
            ->with(['customer:id,name,email,phone', 'items.product:id,name,sku'])
            ->when($store?->branch_id, fn ($q) => $q->where('branch_id', $store->branch_id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status)
            )
            ->when($request->filled('customer_id'), fn ($q) => $q->where('customer_id', $request->customer_id)
            )
            ->when($request->filled('from_date'), fn ($q) => $q->whereDate('created_at', '>=', $request->from_date)
            )
            ->when($request->filled('to_date'), fn ($q) => $q->whereDate('created_at', '<=', $request->to_date)
            )
            ->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_dir', 'desc'));

        $orders = $query->paginate($request->get('per_page', 50));

        return $this->paginatedResponse($orders, __('Orders retrieved successfully'));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $store = $this->getStore($request);

        $order = Sale::query()
            ->with(['customer', 'items.product', 'createdBy:id,name'])
            ->when($store?->branch_id, fn ($q) => $q->where('branch_id', $store->branch_id))
            ->find($id);

        if (! $order) {
            return $this->errorResponse(__('Order not found'), 404);
        }

        return $this->successResponse($order, __('Order retrieved successfully'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'customer_id' => 'nullable|exists:customers,id',
            'customer' => 'nullable|array',
            'customer.name' => 'required_with:customer|string|max:255',
            'customer.email' => 'nullable|email|max:255',
            'customer.phone' => 'nullable|string|max:50',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required_without:items.*.external_id|exists:products,id',
            'items.*.external_id' => 'required_without:items.*.product_id|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.price' => 'required|numeric|min:0',
            'items.*.discount' => 'nullable|numeric|min:0',
            'discount' => 'nullable|numeric|min:0',
            'tax' => 'nullable|numeric|min:0',
            'shipping' => 'nullable|numeric|min:0',
            'payment_method' => 'nullable|string|max:50',
            'notes' => 'nullable|string',
            'external_id' => 'nullable|string|max:100',
        ]);

        $store = $this->getStore($request);

        try {
            $order = DB::transaction(function () use ($validated, $store) {
                $customerId = $validated['customer_id'] ?? null;

                if (! $customerId && isset($validated['customer'])) {
                    $customer = Customer::firstOrCreate(
                        ['email' => $validated['customer']['email'] ?? null],
                        [
                            'name' => $validated['customer']['name'],
                            'phone' => $validated['customer']['phone'] ?? null,
                            'branch_id' => $store?->branch_id,
                        ]
                    );
                    $customerId = $customer->id;
                }

                $subtotal = 0;
                $itemsData = [];

                foreach ($validated['items'] as $item) {
                    $product = null;

                    if (isset($item['product_id'])) {
                        $product = Product::find($item['product_id']);
                    } elseif (isset($item['external_id']) && $store) {
                        $mapping = ProductStoreMapping::where('store_id', $store->id)
                            ->where('external_id', $item['external_id'])
                            ->first();

                        if ($mapping) {
                            $product = $mapping->product;
                        }
                    }

                    if (! $product) {
                        throw new \Exception(__('Product not found').': '.($item['product_id'] ?? $item['external_id']));
                    }

                    $lineTotal = ($item['price'] * $item['quantity']) - ($item['discount'] ?? 0);
                    $subtotal += $lineTotal;

                    $itemsData[] = [
                        'product_id' => $product->id,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['price'],
                        'discount' => $item['discount'] ?? 0,
                        'total' => $lineTotal,
                    ];
                }

                $discount = $validated['discount'] ?? 0;
                $tax = $validated['tax'] ?? 0;
                $shipping = $validated['shipping'] ?? 0;
                $total = $subtotal - $discount + $tax + $shipping;

                $sale = Sale::create([
                    'branch_id' => $store?->branch_id,
                    'customer_id' => $customerId,
                    'user_id' => auth()->id(),
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'tax' => $tax,
                    'total' => $total,
                    'payment_method' => $validated['payment_method'] ?? 'online',
                    'status' => 'pending',
                    'notes' => $validated['notes'] ?? null,
                    'source' => 'api',
                    'external_reference' => $validated['external_id'] ?? null,
                ]);

                foreach ($itemsData as $itemData) {
                    $sale->items()->create($itemData);
                }

                return $sale->load(['customer', 'items.product']);
            });

            return $this->successResponse($order, __('Order created successfully'), 201);
        } catch (\Exception $e) {
            return $this->errorResponse($e->getMessage(), 400);
        }
    }

    public function updateStatus(Request $request, int $id): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:pending,processing,completed,cancelled,refunded',
        ]);

        $store = $this->getStore($request);

        $order = Sale::query()
            ->when($store?->branch_id, fn ($q) => $q->where('branch_id', $store->branch_id))
            ->find($id);

        if (! $order) {
            return $this->errorResponse(__('Order not found'), 404);
        }

        $order->update(['status' => $validated['status']]);

        return $this->successResponse($order, __('Order status updated successfully'));
    }

    public function byExternalId(Request $request, string $externalId): JsonResponse
    {
        $store = $this->getStore($request);

        $order = Sale::query()
            ->with(['customer', 'items.product'])
            ->when($store?->branch_id, fn ($q) => $q->where('branch_id', $store->branch_id))
            ->where('external_reference', $externalId)
            ->first();

        if (! $order) {
            return $this->errorResponse(__('Order not found'), 404);
        }

        return $this->successResponse($order, __('Order retrieved successfully'));
    }
}
