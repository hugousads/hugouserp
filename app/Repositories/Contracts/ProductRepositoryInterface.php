<?php

declare(strict_types=1);

namespace App\Repositories\Contracts;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductRepositoryInterface extends BaseRepositoryInterface
{
    public function paginateForBranch(int $branchId, array $filters = [], int $perPage = 20): LengthAwarePaginator;

    public function createForBranch(int $branchId, array $data);

    public function search(int $branchId, string $query = '', int $perPage = 15): LengthAwarePaginator;

    public function findBySku(string $sku, int $branchId): ?Product;

    public function getAllChunked(int $chunkSize, callable $callback): void;
}
