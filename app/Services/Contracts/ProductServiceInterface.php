<?php

declare(strict_types=1);

namespace App\Services\Contracts;

interface ProductServiceInterface
{
    public function search(int $branchId, string $q = '', int $perPage = 15);

    public function importCsv(int $branchId, string $disk, string $path): int;

    public function exportCsv(string $disk, string $path): string;
}
