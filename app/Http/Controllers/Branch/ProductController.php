<?php

declare(strict_types=1);

namespace App\Http\Controllers\Branch;

use App\Http\Controllers\Controller;
use App\Http\Requests\ProductImageRequest;
use App\Http\Requests\ProductImportRequest;
use App\Models\Branch;
use App\Models\Product;
use App\Services\Contracts\ProductServiceInterface as Products;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductController extends Controller
{
    public function __construct(protected Products $products) {}

    /**
     * List products for the current branch.
     */
    public function index(Request $request)
    {
        $branchId = $this->resolveBranchId($request);
        $per = min(max($request->integer('per_page', 20), 1), 100);

        $query = Product::where('branch_id', $branchId);

        // Search filter
        if ($request->filled('q')) {
            $query->where(function ($q) use ($request) {
                $q->where('name', 'like', '%'.$request->q.'%')
                    ->orWhere('sku', 'like', '%'.$request->q.'%')
                    ->orWhere('barcode', 'like', '%'.$request->q.'%');
            });
        }

        // Category filter
        if ($request->filled('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        // Status filter
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $products = $query->orderByDesc('id')->paginate($per);

        return $this->ok($products);
    }

    /**
     * Get a single product.
     */
    public function show(Product $product)
    {
        // Security: Ensure product belongs to current branch
        $branchId = $this->resolveBranchId(request());
        abort_if($product->branch_id !== $branchId, 404, 'Product not found in this branch');

        return $this->ok($product->load(['category', 'tax']));
    }

    /**
     * Create a new product.
     */
    public function store(Request $request)
    {
        // Note: Using Request instead of ProductStoreRequest until it's created
        $branchId = $this->resolveBranchId($request);

        // Basic validation
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'category_id' => 'nullable|exists:product_categories,id',
            'tax_id' => 'nullable|exists:taxes,id',
            'default_price' => 'required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
            'reorder_point' => 'nullable|integer|min:0',
            'unit_id' => 'nullable|exists:units_of_measure,id',
        ]);

        $product = Product::create($data + ['branch_id' => $branchId]);

        return $this->ok($product, __('Product created successfully'), 201);
    }

    /**
     * Update an existing product.
     */
    public function update(Request $request, Product $product)
    {
        // Security: Ensure product belongs to current branch
        $branchId = $this->resolveBranchId($request);
        abort_if($product->branch_id !== $branchId, 404, 'Product not found in this branch');

        // Basic validation
        $data = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'sku' => 'nullable|string|max:100',
            'barcode' => 'nullable|string|max:100',
            'category_id' => 'nullable|exists:product_categories,id',
            'tax_id' => 'nullable|exists:taxes,id',
            'default_price' => 'sometimes|required|numeric|min:0',
            'cost' => 'nullable|numeric|min:0',
            'description' => 'nullable|string',
            'status' => 'nullable|in:active,inactive',
            'reorder_point' => 'nullable|integer|min:0',
            'unit_id' => 'nullable|exists:units_of_measure,id',
        ]);

        $product->fill($data)->save();

        return $this->ok($product, __('Product updated successfully'));
    }

    public function search(Request $request)
    {
        $q = (string) $request->query('q', '');
        $perPage = (int) $request->query('per_page', 15);

        $branchId = $this->resolveBranchId($request);

        $results = $this->products->search($branchId, $q, $perPage);

        return $this->ok($results);
    }

    public function import(ProductImportRequest $request)
    {
        $file = $request->file('file');
        $path = $file->store('imports', 'local');

        $branchId = $this->resolveBranchId($request);
        $count = $this->products->importCsv($branchId, 'local', $path);

        return $this->ok(['imported' => $count], __('Imported'));
    }

    public function export()
    {
        $path = $this->products->exportCsv('local', 'exports/products.csv');

        return $this->ok([
            'path' => $path,
            'url' => Storage::disk('local')->url($path),
        ], __('Export generated'));
    }

    public function uploadImage(ProductImageRequest $request, Product $product)
    {
        $this->authorize('products.manage');

        $branchId = $this->resolveBranchId($request);
        abort_if($product->branch_id !== $branchId, 404, 'Product not found in this branch');

        $path = $request->file('image')->store('product-images', 'public');
        $product->image_path = $path;
        $product->save();

        return $this->ok(['path' => $path]);
    }

    public function destroy(Product $product)
    {
        $this->authorize('products.delete');

        // Security: Ensure product belongs to current branch
        $branchId = $this->resolveBranchId(request());
        abort_if($product->branch_id !== $branchId, 404, 'Product not found in this branch');

        $product->delete();

        return $this->ok(null, __('Product deleted successfully'));
    }

    protected function resolveBranchId(Request $request): int
    {
        $branchFromAttributes = $request->attributes->get('branch');
        if ($branchFromAttributes instanceof Branch) {
            return (int) $branchFromAttributes->getKey();
        }

        $attributeId = $request->attributes->get('branch_id');
        if ($attributeId !== null) {
            return (int) $attributeId;
        }

        $routeBranch = $request->route('branch');
        if ($routeBranch instanceof Branch) {
            return (int) $routeBranch->getKey();
        }

        if (is_numeric($routeBranch)) {
            return (int) $routeBranch;
        }

        return app()->has('req.branch_id') ? (int) app('req.branch_id') : 0;
    }
}
