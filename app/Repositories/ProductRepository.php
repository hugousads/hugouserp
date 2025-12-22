<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Models\Product;
use App\Repositories\Contracts\ProductRepositoryInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

final class ProductRepository extends EloquentBaseRepository implements ProductRepositoryInterface
{
    public function __construct(Product $model)
    {
        parent::__construct($model);
    }

    protected function baseBranchQuery(int $branchId): Builder
    {
        return $this->query()->where('branch_id', $branchId);
    }

    public function paginateForBranch(int $branchId, array $filters = [], int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->baseBranchQuery($branchId);

        if (! empty($filters['q'])) {
            $q = (string) $filters['q'];
            $query->where(function (Builder $qq) use ($q): void {
                $qq->where('name', 'like', '%'.$q.'%')
                    ->orWhere('sku', 'like', '%'.$q.'%')
                    ->orWhere('barcode', 'like', '%'.$q.'%');
            });
        }

        if (! empty($filters['type'])) {
            $query->where('type', $filters['type']);
        }

        if (array_key_exists('is_active', $filters) && $filters['is_active'] !== null && $filters['is_active'] !== '') {
            $query->where('is_active', (bool) $filters['is_active']);
        }

        return $query->orderByDesc('id')->paginate($perPage);
    }

    public function createForBranch(int $branchId, array $data): Product
    {
        $data['branch_id'] = $branchId;

        /** @var Product $product */
        $product = $this->create($data);

        return $product;
    }

    /**
     * Search products by query string
     */
    public function search(int $branchId, string $query = '', int $perPage = 15): LengthAwarePaginator
    {
        $builder = $this->query()->where('branch_id', $branchId);

        if ($query !== '') {
            $like = '%'.$query.'%';
            $builder->where(function ($inner) use ($like): void {
                $inner->where('name', 'like', $like)
                    ->orWhere('sku', 'like', $like)
                    ->orWhere('barcode', 'like', $like);
            });
        }

        return $builder->orderBy('name')->paginate($perPage);
    }

    /**
     * Find product by SKU
     */
    public function findBySku(string $sku, int $branchId): ?Product
    {
        return $this->query()
            ->where('branch_id', $branchId)
            ->where('sku', $sku)
            ->first();
    }

    /**
     * Get all products for export (chunked)
     */
    public function getAllChunked(int $chunkSize, callable $callback): void
    {
        $this->query()->orderBy('id')->chunk($chunkSize, $callback);
    }
}
