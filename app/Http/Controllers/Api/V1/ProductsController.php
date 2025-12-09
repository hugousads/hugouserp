<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\Product;
use App\Models\ProductStoreMapping;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductsController extends BaseApiController
{
    /**
     * Search products by name, SKU, or barcode for POS terminal.
     * This endpoint is used by the frontend POS system.
     */
    public function search(Request $request, ?int $branchId = null): JsonResponse
    {
        $query = $request->get('q', '');

        if (strlen($query) < 2) {
            return $this->successResponse([], __('Search query too short'));
        }

        $products = Product::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->when(! $branchId && auth()->user()?->branch_id, fn ($q) => $q->where('branch_id', auth()->user()->branch_id))
            ->where(function ($q) use ($query) {
                $q->where('name', 'like', '%'.$query.'%')
                    ->orWhere('sku', 'like', '%'.$query.'%')
                    ->orWhere('barcode', 'like', '%'.$query.'%');
            })
            ->where('status', 'active')
            ->select('id', 'name', 'sku', 'default_price', 'barcode', 'category_id', 'tax_id')
            ->limit(50)
            ->get();

        // Format response to match frontend expectations
        $formattedProducts = $products->map(function ($product) {
            return [
                'id' => $product->id,
                'product_id' => $product->id, // Frontend expects both
                'name' => $product->name,
                'label' => $product->name, // Frontend fallback
                'sku' => $product->sku,
                'price' => (float) $product->default_price,
                'sale_price' => (float) $product->default_price, // Frontend fallback
                'barcode' => $product->barcode,
                'tax_id' => $product->tax_id,
            ];
        });

        return $this->successResponse($formattedProducts, __('Products found'));
    }

    public function index(Request $request): JsonResponse
    {
        $store = $this->getStore($request);

        $query = Product::query()
            ->when($store?->branch_id, fn ($q) => $q->where('branch_id', $store->branch_id))
            ->when($request->filled('search'), fn ($q) => $q->where('name', 'like', '%'.$request->search.'%')
                ->orWhere('sku', 'like', '%'.$request->search.'%')
            )
            ->when($request->filled('category_id'), fn ($q) => $q->where('category_id', $request->category_id)
            )
            ->orderBy($request->get('sort_by', 'created_at'), $request->get('sort_dir', 'desc'));

        $products = $query->paginate($request->get('per_page', 50));

        return $this->paginatedResponse($products, __('Products retrieved successfully'));
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $store = $this->getStore($request);

        $product = Product::query()
            ->when($store?->branch_id, fn ($q) => $q->where('branch_id', $store->branch_id))
            ->find($id);

        if (! $product) {
            return $this->errorResponse(__('Product not found'), 404);
        }

        $product->load(['category']);

        $mapping = null;
        if ($store) {
            $mapping = ProductStoreMapping::where('product_id', $product->id)
                ->where('store_id', $store->id)
                ->first();
        }

        return $this->successResponse([
            'product' => $product,
            'store_mapping' => $mapping,
        ], __('Product retrieved successfully'));
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|max:100|unique:products,sku',
            'description' => 'nullable|string',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'quantity' => 'required|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'barcode' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'min_stock' => 'nullable|integer|min:0',
            'external_id' => 'nullable|string|max:100',
        ]);

        $store = $this->getStore($request);
        $validated['branch_id'] = $store?->branch_id;

        $product = Product::create($validated);

        if ($store && $request->filled('external_id')) {
            ProductStoreMapping::create([
                'product_id' => $product->id,
                'store_id' => $store->id,
                'external_id' => $request->external_id,
                'external_sku' => $request->external_sku ?? $product->sku,
                'last_synced_at' => now(),
            ]);
        }

        return $this->successResponse($product, __('Product created successfully'), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $store = $this->getStore($request);

        $product = Product::query()
            ->when($store?->branch_id, fn ($q) => $q->where('branch_id', $store->branch_id))
            ->find($id);

        if (! $product) {
            return $this->errorResponse(__('Product not found'), 404);
        }

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'sku' => 'sometimes|string|max:100|unique:products,sku,'.$product->id,
            'description' => 'nullable|string',
            'price' => 'sometimes|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'quantity' => 'sometimes|integer|min:0',
            'category_id' => 'nullable|exists:categories,id',
            'warehouse_id' => 'nullable|exists:warehouses,id',
            'barcode' => 'nullable|string|max:100',
            'unit' => 'nullable|string|max:50',
            'min_stock' => 'nullable|integer|min:0',
        ]);

        $product->update($validated);

        return $this->successResponse($product, __('Product updated successfully'));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $store = $this->getStore($request);

        $product = Product::query()
            ->when($store?->branch_id, fn ($q) => $q->where('branch_id', $store->branch_id))
            ->find($id);

        if (! $product) {
            return $this->errorResponse(__('Product not found'), 404);
        }

        $product->delete();

        return $this->successResponse(null, __('Product deleted successfully'));
    }

    public function byExternalId(Request $request, string $externalId): JsonResponse
    {
        $store = $this->getStore($request);

        if (! $store) {
            return $this->errorResponse(__('Store authentication required'), 401);
        }

        $mapping = ProductStoreMapping::where('store_id', $store->id)
            ->where('external_id', $externalId)
            ->with('product')
            ->first();

        if (! $mapping || ! $mapping->product) {
            return $this->errorResponse(__('Product not found'), 404);
        }

        return $this->successResponse([
            'product' => $mapping->product,
            'store_mapping' => $mapping,
        ], __('Product retrieved successfully'));
    }
}
