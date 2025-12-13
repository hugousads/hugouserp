<?php

declare(strict_types=1);

namespace App\Services\Contracts;

use App\Models\Property;
use App\Models\RentalContract;
use App\Models\RentalInvoice;
use App\Models\RentalUnit;
use App\Models\Tenant;

interface RentalServiceInterface
{
    public function createProperty(int $branchId, array $payload): Property;

    public function createUnit(int $propertyId, array $payload): RentalUnit;

    public function setUnitStatus(int $unitId, string $status): RentalUnit;

    public function createTenant(array $payload, ?int $branchId = null): Tenant;

    public function archiveTenant(int $tenantId, ?int $branchId = null): Tenant;

    public function createContract(int $unitId, int $tenantId, array $payload, ?int $branchId = null): RentalContract;

    public function renewContract(int $contractId, array $payload, ?int $branchId = null): RentalContract;

    public function terminateContract(int $contractId, ?int $branchId = null): RentalContract;

    public function runRecurring(?string $forDate = null): int;

    public function collectPayment(int $invoiceId, float $amount, ?string $method = 'cash', ?string $reference = null, ?int $branchId = null): RentalInvoice;

    public function applyPenalty(int $invoiceId, float $penalty, ?int $branchId = null): RentalInvoice;
}
