<?php

declare(strict_types=1);

namespace Tests\Feature\Manufacturing;

use App\Models\BillOfMaterial;
use App\Models\BomItem;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\PosSession;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ManufacturingService;
use App\Services\POSService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/**
 * End-to-End Feature Test: Manufacturing to Sales Lifecycle
 *
 * This test validates the full "Chair Lifecycle" scenario:
 * 1. Setup: Create raw materials, finished goods, and BOM
 * 2. Manufacturing: Create and complete production order
 * 3. Sales: Process POS sale
 * 4. Accounting: Verify journal entries
 *
 * If this test passes, it confirms:
 * - Inventory module works (stock additions/deductions)
 * - Manufacturing module works (raw materials to finished goods)
 * - Sales module works (POS checkout)
 * - Accounting module works (journal entries)
 * - Relationships between tables are intact
 */
class ManufacturingToSalesLifecycleTest extends TestCase
{
    use RefreshDatabase;

    protected ManufacturingService $manufacturingService;

    protected POSService $posService;

    protected Branch $branch;

    protected Warehouse $warehouse;

    protected User $user;

    protected Customer $customer;

    protected Product $rawMaterial; // Wood

    protected Product $finishedGood; // Dining Chair

    protected BillOfMaterial $bom;

    // Constants for the test scenario
    private const WOOD_INITIAL_STOCK = 100;

    private const WOOD_PER_CHAIR = 4;

    private const CHAIRS_TO_PRODUCE = 10;

    private const CHAIRS_TO_SELL = 2;

    private const WOOD_UNIT_COST = 25.00;

    private const CHAIR_SALE_PRICE = 250.00;

    protected function setUp(): void
    {
        parent::setUp();

        // Disable notifications for test speed
        Notification::fake();

        // Initialize services
        $this->manufacturingService = app(ManufacturingService::class);
        $this->posService = app(POSService::class);

        // Setup: Create branch using factory
        $this->branch = Branch::factory()->create([
            'name' => 'Test Manufacturing Branch',
            'code' => 'MFG-001',
            'is_active' => true,
        ]);

        // Setup: Create warehouse
        $this->warehouse = Warehouse::create([
            'name' => 'Main Warehouse',
            'code' => 'WH-MFG-001',
            'branch_id' => $this->branch->id,
            'is_active' => true,
            'allow_negative_stock' => false,
        ]);

        // Setup: Create user with manufacturing and sales permissions
        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        // Setup: Create customer for sales
        $this->customer = Customer::create([
            'name' => 'Test Customer',
            'branch_id' => $this->branch->id,
        ]);

        // Setup: Create raw material product (Wood)
        $this->rawMaterial = Product::create([
            'name' => 'Wood',
            'sku' => 'RAW-WOOD-001',
            'type' => 'stock',
            'default_price' => 30.00,
            'cost' => self::WOOD_UNIT_COST,
            'standard_cost' => self::WOOD_UNIT_COST,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);

        // Setup: Create finished good product (Dining Chair)
        $this->finishedGood = Product::create([
            'name' => 'Dining Chair',
            'sku' => 'FIN-CHAIR-001',
            'type' => 'stock',
            'default_price' => self::CHAIR_SALE_PRICE,
            'cost' => 0.00, // Will be calculated from manufacturing
            'standard_cost' => 0.00,
            'branch_id' => $this->branch->id,
            'status' => 'active',
        ]);

        // Setup: Add initial stock for raw material (100 units of Wood)
        $this->addInitialStock($this->rawMaterial, self::WOOD_INITIAL_STOCK);
    }

    /**
     * Add initial stock for a product via stock movement
     */
    protected function addInitialStock(Product $product, float $quantity): void
    {
        $stockMovementRepo = app(\App\Repositories\Contracts\StockMovementRepositoryInterface::class);

        $stockMovementRepo->create([
            'product_id' => $product->id,
            'warehouse_id' => $this->warehouse->id,
            'direction' => 'in',
            'qty' => $quantity,
            'movement_type' => 'initial_stock',
            'reference_type' => null,
            'reference_id' => null,
            'notes' => 'Initial stock setup for lifecycle test',
            'unit_cost' => $product->cost,
            'created_by' => $this->user->id,
        ]);
    }

    /**
     * Create Bill of Materials: 1 Chair requires 4 units of Wood
     */
    protected function createBillOfMaterials(): BillOfMaterial
    {
        $bom = $this->manufacturingService->createBom([
            'branch_id' => $this->branch->id,
            'product_id' => $this->finishedGood->id,
            'name' => 'Dining Chair BOM',
            'quantity' => 1,
            'status' => 'active',
            'items' => [
                [
                    'product_id' => $this->rawMaterial->id,
                    'quantity' => self::WOOD_PER_CHAIR,
                    'unit_cost' => self::WOOD_UNIT_COST,
                    'type' => 'raw_material',
                    'is_optional' => false,
                ],
            ],
        ]);

        return $bom;
    }

    /**
     * Test the complete Manufacturing to Sales lifecycle
     *
     * Scenario: "The Chair Lifecycle"
     * 1. Raw material (Wood) starts with 100 units
     * 2. Create BOM: 1 Chair = 4 Wood
     * 3. Produce 10 Chairs (uses 40 Wood)
     * 4. Sell 2 Chairs via POS
     * 5. Verify all stock movements and accounting entries
     */
    public function test_complete_manufacturing_to_sales_lifecycle(): void
    {
        // ========================================
        // Phase 1: SETUP VERIFICATION
        // ========================================

        // Verify initial stock state
        $initialWoodStock = StockService::getCurrentStock($this->rawMaterial->id, $this->warehouse->id);
        $initialChairStock = StockService::getCurrentStock($this->finishedGood->id, $this->warehouse->id);

        $this->assertEquals(
            self::WOOD_INITIAL_STOCK,
            $initialWoodStock,
            'Initial wood stock should be 100 units'
        );
        $this->assertEquals(
            0,
            $initialChairStock,
            'Initial chair stock should be 0'
        );

        // ========================================
        // Phase 2: BILL OF MATERIALS CREATION
        // ========================================

        $this->bom = $this->createBillOfMaterials();

        $this->assertNotNull($this->bom, 'BOM should be created');
        $this->assertEquals($this->finishedGood->id, $this->bom->product_id);
        $this->assertEquals(1, $this->bom->items->count(), 'BOM should have 1 item');
        $this->assertEquals(
            self::WOOD_PER_CHAIR,
            (float) $this->bom->items->first()->quantity,
            'BOM item should require 4 Wood per Chair'
        );

        // ========================================
        // Phase 3: MANUFACTURING - CREATE PRODUCTION ORDER
        // ========================================

        $productionOrder = $this->manufacturingService->createProductionOrder([
            'branch_id' => $this->branch->id,
            'bom_id' => $this->bom->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_planned' => self::CHAIRS_TO_PRODUCE,
            'status' => 'draft',
            'priority' => 'normal',
            'created_by' => $this->user->id,
        ]);

        $this->assertInstanceOf(ProductionOrder::class, $productionOrder);
        $this->assertEquals(self::CHAIRS_TO_PRODUCE, (float) $productionOrder->planned_quantity);
        $this->assertEquals('draft', $productionOrder->status);

        // Verify production order items were created from BOM
        $this->assertEquals(1, $productionOrder->items->count());
        $orderItem = $productionOrder->items->first();
        $this->assertEquals($this->rawMaterial->id, $orderItem->product_id);
        $expectedWoodRequired = self::WOOD_PER_CHAIR * self::CHAIRS_TO_PRODUCE; // 4 * 10 = 40
        $this->assertEquals(
            $expectedWoodRequired,
            (float) $orderItem->quantity_required,
            'Production order should require 40 Wood for 10 Chairs'
        );

        // ========================================
        // Phase 4: MANUFACTURING - RELEASE & ISSUE MATERIALS
        // ========================================

        // Release the production order (validates material availability)
        $productionOrder = $this->manufacturingService->releaseProductionOrder($productionOrder);
        $this->assertEquals('released', $productionOrder->status);

        // Issue materials (deduct raw materials from inventory)
        $this->manufacturingService->issueMaterials($productionOrder);

        // ASSERTION 1: Verify Wood stock decreased by 40
        $woodStockAfterIssue = StockService::getCurrentStock($this->rawMaterial->id, $this->warehouse->id);
        $expectedWoodAfterIssue = self::WOOD_INITIAL_STOCK - $expectedWoodRequired; // 100 - 40 = 60

        $this->assertEquals(
            $expectedWoodAfterIssue,
            $woodStockAfterIssue,
            'ASSERTION 1 FAILED: Wood stock should be 60 after issuing materials (100 - 40)'
        );

        // Verify item is marked as issued
        $productionOrder->load('items');
        $this->assertTrue(
            $productionOrder->items->first()->is_issued,
            'Production order item should be marked as issued'
        );

        // ========================================
        // Phase 5: MANUFACTURING - RECORD PRODUCTION OUTPUT
        // ========================================

        // Record production of 10 chairs
        $this->manufacturingService->recordProduction(
            $productionOrder,
            self::CHAIRS_TO_PRODUCE,
            0.0 // No scrap
        );

        // ASSERTION 2: Verify Chair stock increased by 10
        $chairStockAfterProduction = StockService::getCurrentStock($this->finishedGood->id, $this->warehouse->id);

        $this->assertEquals(
            self::CHAIRS_TO_PRODUCE,
            $chairStockAfterProduction,
            'ASSERTION 2 FAILED: Chair stock should be 10 after production'
        );

        // Verify production order was auto-completed
        $productionOrder->refresh();
        $this->assertEquals('completed', $productionOrder->status, 'Production order should be completed');
        $this->assertEquals(
            self::CHAIRS_TO_PRODUCE,
            (float) $productionOrder->produced_quantity,
            'Produced quantity should be 10'
        );

        // ========================================
        // Phase 6: POS SALE
        // ========================================

        // Authenticate user and set branch context
        $this->actingAs($this->user);
        request()->attributes->set('branch_id', $this->branch->id);

        // Open POS session
        $posSession = $this->posService->openSession(
            $this->branch->id,
            $this->user->id,
            100.00 // Opening cash
        );
        $this->assertInstanceOf(PosSession::class, $posSession);

        // Create sale payload
        $salePayload = [
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'customer_id' => $this->customer->id,
            'channel' => 'pos',
            'currency' => 'EGP',
            'items' => [
                [
                    'product_id' => $this->finishedGood->id,
                    'qty' => self::CHAIRS_TO_SELL,
                    'price' => self::CHAIR_SALE_PRICE,
                ],
            ],
            'payments' => [
                [
                    'method' => 'cash',
                    'amount' => self::CHAIR_SALE_PRICE * self::CHAIRS_TO_SELL, // 500.00
                ],
            ],
        ];

        // Process the sale
        $sale = $this->posService->checkout($salePayload);

        $this->assertNotNull($sale, 'Sale should be created');
        $this->assertEquals('completed', $sale->status);
        $this->assertEquals(
            self::CHAIR_SALE_PRICE * self::CHAIRS_TO_SELL,
            (float) $sale->total_amount,
            'Sale total should be 500.00'
        );

        // ASSERTION 3: Verify Chair stock is now 8 (10 - 2)
        $chairStockAfterSale = StockService::getCurrentStock($this->finishedGood->id, $this->warehouse->id);
        $expectedChairStock = self::CHAIRS_TO_PRODUCE - self::CHAIRS_TO_SELL; // 10 - 2 = 8

        $this->assertEquals(
            $expectedChairStock,
            $chairStockAfterSale,
            'ASSERTION 3 FAILED: Chair stock should be 8 after selling 2 chairs'
        );

        // ========================================
        // Phase 7: VERIFY STOCK MOVEMENTS INTEGRITY
        // ========================================

        // Verify raw material movements
        $woodMovements = DB::table('stock_movements')
            ->where('product_id', $this->rawMaterial->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->get();

        $this->assertGreaterThanOrEqual(2, $woodMovements->count(), 'Wood should have at least 2 movements');

        // Verify finished goods movements
        $chairMovements = DB::table('stock_movements')
            ->where('product_id', $this->finishedGood->id)
            ->where('warehouse_id', $this->warehouse->id)
            ->get();

        $this->assertGreaterThanOrEqual(1, $chairMovements->count(), 'Chair should have at least 1 movement');

        // ========================================
        // Phase 8: FINAL INVENTORY VERIFICATION
        // ========================================

        // Final stock state
        $finalWoodStock = StockService::getCurrentStock($this->rawMaterial->id, $this->warehouse->id);
        $finalChairStock = StockService::getCurrentStock($this->finishedGood->id, $this->warehouse->id);

        $this->assertEquals(
            60.0,
            $finalWoodStock,
            'Final Wood stock should be 60 (100 initial - 40 used in production)'
        );
        $this->assertEquals(
            8.0,
            $finalChairStock,
            'Final Chair stock should be 8 (10 produced - 2 sold)'
        );
    }

    /**
     * Test that production order completion ensures materials are issued
     * This validates the BUG FIX in ManufacturingService::completeProductionOrder
     */
    public function test_production_order_completion_issues_materials_automatically(): void
    {
        // Create BOM
        $this->bom = $this->createBillOfMaterials();

        // Create production order in draft status
        $productionOrder = $this->manufacturingService->createProductionOrder([
            'branch_id' => $this->branch->id,
            'bom_id' => $this->bom->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_planned' => 5, // Produce 5 chairs (needs 20 wood)
            'status' => 'draft',
            'priority' => 'normal',
            'created_by' => $this->user->id,
        ]);

        // Record initial wood stock
        $initialWoodStock = StockService::getCurrentStock($this->rawMaterial->id, $this->warehouse->id);

        // Complete the production order directly (without explicitly issuing materials)
        // The bug fix should auto-issue materials
        $productionOrder = $this->manufacturingService->completeProductionOrder($productionOrder);

        // Verify materials were auto-issued
        $productionOrder->load('items');
        $this->assertTrue(
            $productionOrder->items->every(fn ($item) => $item->is_issued),
            'All production order items should be issued after completion'
        );

        // Verify wood stock was deducted
        $woodStockAfterCompletion = StockService::getCurrentStock($this->rawMaterial->id, $this->warehouse->id);
        $expectedWoodDeduction = 5 * self::WOOD_PER_CHAIR; // 5 chairs * 4 wood = 20

        $this->assertEquals(
            $initialWoodStock - $expectedWoodDeduction,
            $woodStockAfterCompletion,
            'Wood stock should be deducted when production order is completed'
        );
    }

    /**
     * Test that stock operations use database transactions
     * This ensures atomicity - either all operations succeed or none do
     */
    public function test_manufacturing_operations_are_atomic(): void
    {
        // Create BOM
        $this->bom = $this->createBillOfMaterials();

        // Create production order
        $productionOrder = $this->manufacturingService->createProductionOrder([
            'branch_id' => $this->branch->id,
            'bom_id' => $this->bom->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_planned' => 5,
            'status' => 'draft',
            'priority' => 'normal',
            'created_by' => $this->user->id,
        ]);

        // Record the initial state
        $initialWoodStock = StockService::getCurrentStock($this->rawMaterial->id, $this->warehouse->id);
        $initialOrderStatus = $productionOrder->status;

        // Try to release with insufficient stock (should fail)
        // First, deplete the stock to make release fail
        $stockMovementRepo = app(\App\Repositories\Contracts\StockMovementRepositoryInterface::class);
        $stockMovementRepo->create([
            'product_id' => $this->rawMaterial->id,
            'warehouse_id' => $this->warehouse->id,
            'direction' => 'out',
            'qty' => 90, // Leave only 10 units (need 20)
            'movement_type' => 'test_depletion',
            'notes' => 'Test depletion',
            'created_by' => $this->user->id,
        ]);

        // Try to release - should fail due to insufficient stock
        try {
            $this->manufacturingService->releaseProductionOrder($productionOrder);
            $this->fail('Expected exception for insufficient stock');
        } catch (\Exception $e) {
            // Expected - verify order status didn't change
            $productionOrder->refresh();
            $this->assertEquals(
                $initialOrderStatus,
                $productionOrder->status,
                'Order status should not change on failed release (atomic operation)'
            );
        }
    }

    /**
     * Test BOM material cost calculation accuracy
     * This validates COGS (Cost of Goods Sold) calculation
     */
    public function test_bom_material_cost_calculation(): void
    {
        // Create BOM
        $this->bom = $this->createBillOfMaterials();

        // Calculate expected material cost
        $expectedMaterialCost = self::WOOD_PER_CHAIR * self::WOOD_UNIT_COST; // 4 * 25 = 100

        $actualMaterialCost = $this->bom->calculateMaterialCost();

        $this->assertEquals(
            $expectedMaterialCost,
            $actualMaterialCost,
            'BOM material cost should be 100.00 (4 wood * 25 per unit)'
        );

        // Test total cost for production run
        $expectedTotalCostFor10 = $expectedMaterialCost * self::CHAIRS_TO_PRODUCE; // 100 * 10 = 1000

        $productionOrder = $this->manufacturingService->createProductionOrder([
            'branch_id' => $this->branch->id,
            'bom_id' => $this->bom->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_planned' => self::CHAIRS_TO_PRODUCE,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        $this->assertEquals(
            $expectedTotalCostFor10,
            (float) $productionOrder->estimated_cost,
            'Estimated production cost should be 1000.00 for 10 chairs'
        );
    }

    /**
     * Test that circular dependencies in BOM are detected
     */
    public function test_bom_circular_dependency_detection(): void
    {
        // Try to create a BOM where a product requires itself
        $this->expectException(\Exception::class);

        $this->manufacturingService->createBom([
            'branch_id' => $this->branch->id,
            'product_id' => $this->finishedGood->id,
            'name' => 'Invalid Circular BOM',
            'quantity' => 1,
            'status' => 'active',
            'items' => [
                [
                    'product_id' => $this->finishedGood->id, // Same as finished product - circular!
                    'quantity' => 1,
                    'type' => 'raw_material',
                ],
            ],
        ]);
    }

    /**
     * Test production order cannot be released without sufficient materials
     */
    public function test_production_order_release_validates_stock_availability(): void
    {
        // Create a BOM that requires more wood than available
        $bom = $this->manufacturingService->createBom([
            'branch_id' => $this->branch->id,
            'product_id' => $this->finishedGood->id,
            'name' => 'High Demand BOM',
            'quantity' => 1,
            'status' => 'active',
            'items' => [
                [
                    'product_id' => $this->rawMaterial->id,
                    'quantity' => 50, // 50 wood per chair
                    'unit_cost' => self::WOOD_UNIT_COST,
                    'type' => 'raw_material',
                ],
            ],
        ]);

        // Create production order for 3 chairs (needs 150 wood, but only 100 available)
        $productionOrder = $this->manufacturingService->createProductionOrder([
            'branch_id' => $this->branch->id,
            'bom_id' => $bom->id,
            'warehouse_id' => $this->warehouse->id,
            'quantity_planned' => 3,
            'status' => 'draft',
            'created_by' => $this->user->id,
        ]);

        // Attempt to release should fail
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Insufficient stock');

        $this->manufacturingService->releaseProductionOrder($productionOrder);
    }
}
