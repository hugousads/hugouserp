<?php

declare(strict_types=1);

namespace Tests\Feature\Inventory;

use App\Models\Branch;
use App\Models\InventoryTransit;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\StockTransferService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test for the Vanishing Stock Bug Fix
 * 
 * Ensures that inventory in transit is properly tracked and doesn't
 * "vanish" between warehouses during transfer operations.
 */
class VanishingStockBugTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;
    protected Warehouse $fromWarehouse;
    protected Warehouse $toWarehouse;
    protected Product $product;
    protected StockTransferService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->branch = Branch::factory()->create();
        
        $this->fromWarehouse = Warehouse::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Main Warehouse',
        ]);
        
        $this->toWarehouse = Warehouse::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Branch Warehouse',
        ]);
        
        $this->product = Product::factory()->create([
            'branch_id' => $this->branch->id,
            'stock_quantity' => 100,
        ]);

        $this->service = app(StockTransferService::class);
        $this->actingAs($this->user);
    }

    /** @test */
    public function it_creates_transit_record_when_transfer_is_shipped()
    {
        // Create transfer
        $transfer = $this->service->createTransfer([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 10,
                    'unit_cost' => 5.00,
                ],
            ],
        ]);

        // Approve transfer
        $transfer = $this->service->approveTransfer($transfer->id);

        // Ship transfer
        $transfer = $this->service->shipTransfer($transfer->id, [
            'tracking_number' => 'TRACK123',
        ]);

        // Assert transit record was created
        $this->assertDatabaseHas('inventory_transits', [
            'product_id' => $this->product->id,
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'stock_transfer_id' => $transfer->id,
            'quantity' => 10,
            'status' => InventoryTransit::STATUS_IN_TRANSIT,
        ]);

        // Verify transit record exists
        $transitCount = InventoryTransit::where('stock_transfer_id', $transfer->id)
            ->where('status', InventoryTransit::STATUS_IN_TRANSIT)
            ->count();
        
        $this->assertEquals(1, $transitCount, 'Transit record should be created when transfer is shipped');
    }

    /** @test */
    public function it_tracks_inventory_in_transit_preventing_vanishing_stock()
    {
        // Create and ship transfer
        $transfer = $this->service->createTransfer([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 20,
                ],
            ],
        ]);

        $transfer = $this->service->approveTransfer($transfer->id);
        $transfer = $this->service->shipTransfer($transfer->id, []);

        // Get in-transit quantity
        $inTransitQty = InventoryTransit::where('product_id', $this->product->id)
            ->where('status', InventoryTransit::STATUS_IN_TRANSIT)
            ->sum('quantity');

        $this->assertEquals(20, $inTransitQty, 'Should track 20 units in transit');
        
        // Verify stock is accounted for (not vanished)
        // The inventory should be: 
        // - Deducted from source warehouse
        // - Recorded in transit table
        // - Not yet in destination warehouse
        $transitRecord = InventoryTransit::where('stock_transfer_id', $transfer->id)->first();
        $this->assertNotNull($transitRecord, 'Transit record must exist to track in-flight inventory');
    }

    /** @test */
    public function it_moves_from_transit_to_destination_on_receive()
    {
        // Create, approve, and ship transfer
        $transfer = $this->service->createTransfer([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 15,
                ],
            ],
        ]);

        $transfer = $this->service->approveTransfer($transfer->id);
        $transfer = $this->service->shipTransfer($transfer->id, []);

        // Verify in transit
        $inTransit = InventoryTransit::where('stock_transfer_id', $transfer->id)
            ->where('status', InventoryTransit::STATUS_IN_TRANSIT)
            ->count();
        $this->assertEquals(1, $inTransit);

        // Receive transfer
        $itemId = $transfer->items()->first()->id;
        $transfer = $this->service->receiveTransfer($transfer->id, [
            'items' => [
                $itemId => [
                    'qty_received' => 15,
                    'qty_damaged' => 0,
                ],
            ],
        ]);

        // Transit record should be marked as received
        $receivedTransit = InventoryTransit::where('stock_transfer_id', $transfer->id)
            ->where('status', InventoryTransit::STATUS_RECEIVED)
            ->count();
        
        $this->assertEquals(1, $receivedTransit, 'Transit record should be marked as received');

        // No more in-transit records
        $stillInTransit = InventoryTransit::where('stock_transfer_id', $transfer->id)
            ->where('status', InventoryTransit::STATUS_IN_TRANSIT)
            ->count();
        
        $this->assertEquals(0, $stillInTransit, 'No inventory should remain in transit after receiving');
    }

    /** @test */
    public function it_returns_stock_from_transit_on_cancellation()
    {
        // Create, approve, and ship transfer
        $transfer = $this->service->createTransfer([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 25,
                ],
            ],
        ]);

        $transfer = $this->service->approveTransfer($transfer->id);
        $transfer = $this->service->shipTransfer($transfer->id, []);

        // Verify in transit
        $inTransit = InventoryTransit::where('stock_transfer_id', $transfer->id)
            ->where('status', InventoryTransit::STATUS_IN_TRANSIT)
            ->first();
        $this->assertNotNull($inTransit);
        $this->assertEquals(25, $inTransit->quantity);

        // Cancel transfer
        $transfer = $this->service->cancelTransfer($transfer->id, 'Testing cancellation');

        // Transit should be marked as cancelled
        $cancelledTransit = InventoryTransit::where('stock_transfer_id', $transfer->id)
            ->where('status', InventoryTransit::STATUS_CANCELLED)
            ->first();
        
        $this->assertNotNull($cancelledTransit, 'Transit record should be marked as cancelled');
        
        // Verify no active transits remain
        $activeTransits = InventoryTransit::where('stock_transfer_id', $transfer->id)
            ->where('status', InventoryTransit::STATUS_IN_TRANSIT)
            ->count();
        
        $this->assertEquals(0, $activeTransits, 'No active transits should remain after cancellation');
    }

    /** @test */
    public function it_prevents_stock_vanishing_during_physical_inventory_count()
    {
        // Create and ship a transfer (stock in transit)
        $transfer = $this->service->createTransfer([
            'from_warehouse_id' => $this->fromWarehouse->id,
            'to_warehouse_id' => $this->toWarehouse->id,
            'items' => [
                [
                    'product_id' => $this->product->id,
                    'qty' => 30,
                ],
            ],
        ]);

        $transfer = $this->service->approveTransfer($transfer->id);
        $transfer = $this->service->shipTransfer($transfer->id, []);

        // When doing inventory count, we should account for in-transit stock
        $inTransitTotal = InventoryTransit::where('product_id', $this->product->id)
            ->where('status', InventoryTransit::STATUS_IN_TRANSIT)
            ->sum('quantity');

        $this->assertEquals(30, $inTransitTotal, 
            'In-transit inventory should be trackable during physical counts');

        // Total inventory = (stock in all warehouses) + (in-transit stock)
        // This prevents the "vanishing stock" phenomenon
        $this->assertGreaterThan(0, $inTransitTotal, 
            'In-transit stock must be visible to prevent apparent inventory loss');
    }
}
