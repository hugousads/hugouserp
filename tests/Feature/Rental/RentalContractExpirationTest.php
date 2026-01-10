<?php

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Models\Branch;
use App\Models\Property;
use App\Models\RentalContract;
use App\Models\RentalUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

class RentalContractExpirationTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;

    protected Branch $branch;

    protected Property $property;

    protected RentalUnit $unit;

    protected Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::factory()->create([
            'name' => 'Test Branch',
            'is_active' => true,
        ]);

        $this->property = Property::create([
            'branch_id' => $this->branch->id,
            'name' => 'Test Property',
            'address' => '123 Test St',
        ]);

        $this->unit = RentalUnit::create([
            'property_id' => $this->property->id,
            'code' => 'UNIT-001',
            'type' => 'apartment',
            'status' => 'occupied',
            'rent' => 1000,
        ]);

        $this->tenant = Tenant::create([
            'branch_id' => $this->branch->id,
            'name' => 'Test Tenant',
            'phone' => '1234567890',
            'email' => 'tenant@test.com',
        ]);

        $this->user = User::factory()->create([
            'email' => 'admin@test.com',
            'password' => bcrypt('password'),
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_expired_contracts_are_detected_and_expired(): void
    {
        // Create an active contract that expired yesterday
        $contract = RentalContract::create([
            'branch_id' => $this->branch->id,
            'unit_id' => $this->unit->id,
            'tenant_id' => $this->tenant->id,
            'start_date' => now()->subMonths(6),
            'end_date' => now()->subDay(),
            'rent_amount' => 1000,
            'status' => 'active',
        ]);

        $this->assertEquals('active', $contract->status);
        $this->assertEquals('occupied', $this->unit->fresh()->status);

        // Run the expiration command
        Artisan::call('rental:expire-contracts');

        // Contract should be expired
        $contract->refresh();
        $this->assertEquals('expired', $contract->status);

        // Unit should be available
        $this->unit->refresh();
        $this->assertEquals('available', $this->unit->status);
    }

    public function test_active_contracts_with_future_end_date_are_not_expired(): void
    {
        // Create an active contract that expires in the future
        $contract = RentalContract::create([
            'branch_id' => $this->branch->id,
            'unit_id' => $this->unit->id,
            'tenant_id' => $this->tenant->id,
            'start_date' => now()->subMonth(),
            'end_date' => now()->addMonth(),
            'rent_amount' => 1000,
            'status' => 'active',
        ]);

        $this->assertEquals('active', $contract->status);

        // Run the expiration command
        Artisan::call('rental:expire-contracts');

        // Contract should still be active
        $contract->refresh();
        $this->assertEquals('active', $contract->status);

        // Unit should still be occupied
        $this->unit->refresh();
        $this->assertEquals('occupied', $this->unit->status);
    }

    public function test_dry_run_does_not_change_data(): void
    {
        // Create an expired contract
        $contract = RentalContract::create([
            'branch_id' => $this->branch->id,
            'unit_id' => $this->unit->id,
            'tenant_id' => $this->tenant->id,
            'start_date' => now()->subMonths(6),
            'end_date' => now()->subDay(),
            'rent_amount' => 1000,
            'status' => 'active',
        ]);

        // Run in dry-run mode
        Artisan::call('rental:expire-contracts', ['--dry-run' => true]);

        // Contract should still be active (no changes)
        $contract->refresh();
        $this->assertEquals('active', $contract->status);

        // Unit should still be occupied
        $this->unit->refresh();
        $this->assertEquals('occupied', $this->unit->status);
    }

    public function test_only_occupied_or_rented_units_are_released(): void
    {
        // Create unit with 'maintenance' status
        $maintenanceUnit = RentalUnit::create([
            'property_id' => $this->property->id,
            'code' => 'UNIT-002',
            'type' => 'apartment',
            'status' => 'maintenance',
            'rent' => 1000,
        ]);

        // Create expired contract for maintenance unit
        $contract = RentalContract::create([
            'branch_id' => $this->branch->id,
            'unit_id' => $maintenanceUnit->id,
            'tenant_id' => $this->tenant->id,
            'start_date' => now()->subMonths(6),
            'end_date' => now()->subDay(),
            'rent_amount' => 1000,
            'status' => 'active',
        ]);

        // Run the expiration command
        Artisan::call('rental:expire-contracts');

        // Contract should be expired
        $contract->refresh();
        $this->assertEquals('expired', $contract->status);

        // Unit should remain in maintenance (not changed to available)
        $maintenanceUnit->refresh();
        $this->assertEquals('maintenance', $maintenanceUnit->status);
    }

    public function test_multiple_expired_contracts_are_processed(): void
    {
        // Create multiple units and contracts
        $units = [];
        $contracts = [];

        for ($i = 0; $i < 3; $i++) {
            $unit = RentalUnit::create([
                'property_id' => $this->property->id,
                'code' => 'UNIT-00'.($i + 2),
                'type' => 'apartment',
                'status' => 'occupied',
                'rent' => 1000,
            ]);
            $units[] = $unit;

            $contracts[] = RentalContract::create([
                'branch_id' => $this->branch->id,
                'unit_id' => $unit->id,
                'tenant_id' => $this->tenant->id,
                'start_date' => now()->subMonths(6),
                'end_date' => now()->subDay(),
                'rent_amount' => 1000,
                'status' => 'active',
            ]);
        }

        // Run the expiration command
        Artisan::call('rental:expire-contracts');

        // All contracts should be expired
        foreach ($contracts as $contract) {
            $contract->refresh();
            $this->assertEquals('expired', $contract->status);
        }

        // All units should be available
        foreach ($units as $unit) {
            $unit->refresh();
            $this->assertEquals('available', $unit->status);
        }
    }
}
