<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Vehicle;
use App\Models\VehicleContract;
use App\Models\Warranty;
use App\Services\Contracts\MotorcycleServiceInterface;
use App\Traits\HandlesServiceErrors;
use Illuminate\Support\Facades\DB;

class MotorcycleService implements MotorcycleServiceInterface
{
    use HandlesServiceErrors;

    public function vehicles()
    {
        return $this->handleServiceOperation(
            callback: fn () => Vehicle::query()->orderByDesc('id')->get(),
            operation: 'vehicles',
            context: []
        );
    }

    public function createContract(int $vehicleId, int $customerId, string $startDate, string $endDate): VehicleContract
    {
        return $this->handleServiceOperation(
            callback: function () use ($vehicleId, $customerId, $startDate, $endDate) {
                return DB::transaction(function () use ($vehicleId, $customerId, $startDate, $endDate) {
                    $contract = VehicleContract::create([
                        'vehicle_id' => $vehicleId,
                        'customer_id' => $customerId,
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                        'status' => 'active',
                    ]);

                    return $contract;
                });
            },
            operation: 'createContract',
            context: ['vehicle_id' => $vehicleId, 'customer_id' => $customerId]
        );
    }

    public function deliverContract(int $contractId): VehicleContract
    {
        return $this->handleServiceOperation(
            callback: function () use ($contractId) {
                $c = VehicleContract::findOrFail($contractId);
                $c->status = 'delivered';
                $c->delivered_at = now();
                $c->save();

                return $c;
            },
            operation: 'deliverContract',
            context: ['contract_id' => $contractId]
        );
    }

    public function upsertWarranty(int $vehicleId, array $payload): Warranty
    {
        return $this->handleServiceOperation(
            callback: function () use ($vehicleId, $payload) {
                return Warranty::updateOrCreate(
                    ['vehicle_id' => $vehicleId],
                    [
                        'provider' => $payload['provider'] ?? 'default',
                        'start_date' => $payload['start_date'] ?? $payload['start_at'] ?? now(),
                        'end_date' => $payload['end_date'] ?? $payload['end_at'] ?? now()->addYear(),
                        'notes' => $payload['notes'] ?? null,
                    ]
                );
            },
            operation: 'upsertWarranty',
            context: ['vehicle_id' => $vehicleId]
        );
    }
}
