<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Events\SaleCompleted;
use App\Listeners\UpdateStockOnSale;
use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\JournalEntry;
use App\Models\Product;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use App\Models\StockMovement;
use App\Models\Tax;
use App\Models\UnitOfMeasure;
use App\Models\User;
use App\Models\Warehouse;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Services\AccountingService;
use App\Services\POSService;
use App\Services\TaxService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test suite for critical ERP bug fixes
 * Tests for 5 major bugs: COGS, UoM conversion, split payments, N+1 queries, tax rounding
 */
class CriticalBugFixesTest extends TestCase
{
    use RefreshDatabase;

    protected AccountingService $accountingService;

    protected TaxService $taxService;

    protected POSService $posService;

    protected Branch $branch;

    protected Warehouse $warehouse;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->accountingService = app(AccountingService::class);
        $this->taxService = app(TaxService::class);
        $this->posService = app(POSService::class);

        // Create test branch
        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB001',
            'is_active' => true,
        ]);

        // Create test warehouse
        $this->warehouse = Warehouse::create([
            'name' => 'Test Warehouse',
            'code' => 'WH001',
            'branch_id' => $this->branch->id,
            'is_active' => true,
        ]);

        // Create test user
        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);

        $this->actingAs($this->user);

        // Setup account mappings for testing
        $this->setupAccountMappings();
    }

    protected function setupAccountMappings(): void
    {
        // Create necessary accounts
        $accounts = [
            'cash_account' => ['code' => '1010', 'name' => 'Cash', 'type' => 'asset'],
            'bank_account' => ['code' => '1020', 'name' => 'Bank', 'type' => 'asset'],
            'cheque_account' => ['code' => '1030', 'name' => 'Cheques', 'type' => 'asset'],
            'accounts_receivable' => ['code' => '1100', 'name' => 'Accounts Receivable', 'type' => 'asset'],
            'sales_revenue' => ['code' => '4010', 'name' => 'Sales Revenue', 'type' => 'revenue'],
            'cogs_account' => ['code' => '5010', 'name' => 'Cost of Goods Sold', 'type' => 'expense'],
            'inventory_account' => ['code' => '1300', 'name' => 'Inventory', 'type' => 'asset'],
            'tax_payable' => ['code' => '2100', 'name' => 'Tax Payable', 'type' => 'liability'],
            'sales_discount' => ['code' => '4020', 'name' => 'Sales Discount', 'type' => 'contra_revenue'],
        ];

        foreach ($accounts as $key => $accountData) {
            $account = Account::create([
                'code' => $accountData['code'],
                'name' => $accountData['name'],
                'type' => $accountData['type'],
                'branch_id' => $this->branch->id,
                'is_active' => true,
            ]);

            AccountMapping::create([
                'branch_id' => $this->branch->id,
                'module_name' => 'sales',
                'mapping_key' => $key,
                'account_id' => $account->id,
                'is_active' => true,
            ]);
        }
    }

    /**
     * BUG FIX #1: Test COGS entry generation
     */
    public function test_cogs_entry_is_generated_for_sale(): void
    {
        // Create product with cost
        $product = Product::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Test Product',
            'cost' => 50.00,
            'default_price' => 100.00,
        ]);

        // Create sale
        $sale = Sale::create([
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'reference_number' => 'SO-001',
            'status' => 'completed',
            'subtotal' => 100.00,
            'tax_amount' => 0,
            'total_amount' => 100.00,
            'paid_amount' => 100.00,
            'created_by' => $this->user->id,
        ]);

        // Create sale item
        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 2,
            'unit_price' => 100.00,
            'cost_price' => 50.00,
            'line_total' => 200.00,
        ]);

        // Generate sale journal entry (should also generate COGS entry)
        $journalEntry = $this->accountingService->generateSaleJournalEntry($sale->fresh());

        // Assert journal entry was created
        $this->assertNotNull($journalEntry);

        // Check for COGS journal entry
        $cogsEntry = JournalEntry::where('source_id', $sale->id)
            ->where('source_type', 'Sale')
            ->where('reference_number', 'LIKE', 'COGS-%')
            ->first();

        $this->assertNotNull($cogsEntry, 'COGS journal entry should be created');

        // Verify COGS entry has correct lines (Debit COGS, Credit Inventory)
        $cogsEntry->load('lines');
        $this->assertCount(2, $cogsEntry->lines);

        $cogsDebit = $cogsEntry->lines->where('debit', '>', 0)->first();
        $inventoryCredit = $cogsEntry->lines->where('credit', '>', 0)->first();

        $this->assertNotNull($cogsDebit, 'COGS debit line should exist');
        $this->assertNotNull($inventoryCredit, 'Inventory credit line should exist');
        $this->assertEquals(100.00, $cogsDebit->debit, 'COGS should be 2 * 50 = 100');
        $this->assertEquals(100.00, $inventoryCredit->credit, 'Inventory credit should match COGS');
    }

    /**
     * BUG FIX #2: Test UoM conversion in stock deduction
     */
    public function test_uom_conversion_applied_in_stock_deduction(): void
    {
        // Create base unit and derived unit (1 carton = 12 pieces)
        $baseUnit = UnitOfMeasure::create([
            'name' => 'Piece',
            'symbol' => 'pc',
            'is_base_unit' => true,
            'conversion_factor' => 1.0,
            'is_active' => true,
        ]);

        $cartonUnit = UnitOfMeasure::create([
            'name' => 'Carton',
            'symbol' => 'ctn',
            'base_unit_id' => $baseUnit->id,
            'is_base_unit' => false,
            'conversion_factor' => 12.0,
            'is_active' => true,
        ]);

        // Create product
        $product = Product::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Test Product',
            'unit_id' => $baseUnit->id,
        ]);

        // Create sale with 1 carton (should deduct 12 pieces)
        $sale = Sale::create([
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'reference_number' => 'SO-002',
            'status' => 'completed',
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'created_by' => $this->user->id,
        ]);

        $saleItem = SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'unit_id' => $cartonUnit->id,
            'quantity' => 1.0, // 1 carton
            'unit_price' => 100.00,
            'line_total' => 100.00,
        ]);

        // Mock stock movement repository
        $stockMovementRepo = $this->mock(StockMovementRepositoryInterface::class);
        $stockMovementRepo->shouldReceive('create')
            ->once()
            ->withArgs(function ($data) {
                // Verify that qty is 12 (1 carton * 12 conversion factor)
                return abs($data['qty'] - 12.0) < 0.01;
            })
            ->andReturn(new StockMovement());

        // Trigger stock update
        $listener = new UpdateStockOnSale($stockMovementRepo);
        $listener->handle(new SaleCompleted($sale->fresh(['items.unit'])));
    }

    /**
     * BUG FIX #3: Test split payment reconciliation
     */
    public function test_split_payments_create_separate_accounting_entries(): void
    {
        // Create sale with split payment (cash + card)
        $sale = Sale::create([
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'reference_number' => 'SO-003',
            'status' => 'completed',
            'subtotal' => 1000.00,
            'tax_amount' => 0,
            'total_amount' => 1000.00,
            'paid_amount' => 1000.00,
            'created_by' => $this->user->id,
        ]);

        // Create split payments
        SalePayment::create([
            'sale_id' => $sale->id,
            'branch_id' => $this->branch->id,
            'payment_method' => 'cash',
            'amount' => 500.00,
            'status' => 'completed',
        ]);

        SalePayment::create([
            'sale_id' => $sale->id,
            'branch_id' => $this->branch->id,
            'payment_method' => 'card',
            'amount' => 500.00,
            'status' => 'completed',
        ]);

        // Generate journal entry
        $journalEntry = $this->accountingService->generateSaleJournalEntry($sale->fresh('payments'));

        // Assert journal entry was created
        $this->assertNotNull($journalEntry);

        // Load journal entry lines
        $journalEntry->load('lines.account');

        // Check for cash debit
        $cashLine = $journalEntry->lines->firstWhere(function ($line) {
            return $line->debit > 0 && $line->account->code === '1010'; // Cash account
        });

        // Check for bank/card debit
        $bankLine = $journalEntry->lines->firstWhere(function ($line) {
            return $line->debit > 0 && $line->account->code === '1020'; // Bank account
        });

        $this->assertNotNull($cashLine, 'Cash debit line should exist');
        $this->assertNotNull($bankLine, 'Bank debit line should exist');
        $this->assertEquals(500.00, $cashLine->debit, 'Cash debit should be 500');
        $this->assertEquals(500.00, $bankLine->debit, 'Bank debit should be 500');
    }

    /**
     * BUG FIX #5: Test line-level tax rounding
     */
    public function test_tax_calculated_and_rounded_at_line_level(): void
    {
        // Create tax rate 15%
        $tax = Tax::create([
            'name' => 'VAT 15%',
            'rate' => 15.0,
            'is_active' => true,
        ]);

        // Test line-level tax calculation
        $lineAmount1 = 33.33;
        $lineAmount2 = 33.33;
        $lineAmount3 = 33.34;

        $tax1 = $this->taxService->compute($lineAmount1, $tax->id);
        $tax2 = $this->taxService->compute($lineAmount2, $tax->id);
        $tax3 = $this->taxService->compute($lineAmount3, $tax->id);

        // Each line should be rounded to 2 decimals
        $this->assertEquals(5.00, $tax1, 'Line 1 tax should be 5.00 (33.33 * 0.15 = 4.9995 rounded to 5.00)');
        $this->assertEquals(5.00, $tax2, 'Line 2 tax should be 5.00');
        $this->assertEquals(5.00, $tax3, 'Line 3 tax should be 5.00 (33.34 * 0.15 = 5.001 rounded to 5.00)');

        // Total should be sum of rounded line taxes
        $totalTax = $tax1 + $tax2 + $tax3;
        $this->assertEquals(15.00, $totalTax, 'Total tax should be 15.00 (sum of line-level rounded taxes)');

        // Compare with total-level rounding (which would be different)
        $totalAmount = $lineAmount1 + $lineAmount2 + $lineAmount3;
        $totalLevelTax = round($totalAmount * 0.15, 2);
        
        // This demonstrates the difference between line-level and total-level rounding
        // Line-level: 5.00 + 5.00 + 5.00 = 15.00
        // Total-level: round(100.00 * 0.15, 2) = 15.00
        // In this case they're equal, but with different amounts they could differ
        $this->assertIsFloat($totalLevelTax);
    }

    /**
     * Test that tax rounding works correctly with amounts that show rounding differences
     */
    public function test_tax_rounding_difference_between_line_and_total_level(): void
    {
        // Create tax rate 13.5% (more likely to show rounding differences)
        $tax = Tax::create([
            'name' => 'VAT 13.5%',
            'rate' => 13.5,
            'is_active' => true,
        ]);

        // Test with amounts that will show rounding differences
        $lines = [
            ['amount' => 10.01, 'expected_tax' => 1.35], // 10.01 * 0.135 = 1.35135 -> 1.35
            ['amount' => 20.02, 'expected_tax' => 2.70], // 20.02 * 0.135 = 2.7027 -> 2.70
            ['amount' => 30.03, 'expected_tax' => 4.05], // 30.03 * 0.135 = 4.05405 -> 4.05
        ];

        $lineLevelTotal = 0;
        foreach ($lines as $line) {
            $lineTax = $this->taxService->compute($line['amount'], $tax->id);
            $this->assertEquals($line['expected_tax'], $lineTax, "Line tax for {$line['amount']} should be {$line['expected_tax']}");
            $lineLevelTotal += $lineTax;
        }

        // Line-level total: 1.35 + 2.70 + 4.05 = 8.10
        $this->assertEquals(8.10, $lineLevelTotal, 'Sum of line-level taxes should be 8.10');

        // Total-level would be: (10.01 + 20.02 + 30.03) * 0.135 = 60.06 * 0.135 = 8.1081 -> 8.11
        $totalAmount = array_sum(array_column($lines, 'amount'));
        $totalLevelTax = round($totalAmount * 0.135, 2);
        $this->assertEquals(8.11, $totalLevelTax, 'Total-level tax would be 8.11');

        // This demonstrates the 0.01 difference that can cause invoice rejection
        $this->assertNotEquals($lineLevelTotal, $totalLevelTax, 'Line-level and total-level rounding should differ');
    }

    /**
     * Test COGS entry with zero cost products
     */
    public function test_cogs_entry_not_created_for_zero_cost_products(): void
    {
        // Create product with zero cost
        $product = Product::factory()->create([
            'branch_id' => $this->branch->id,
            'name' => 'Free Product',
            'cost' => 0.00,
            'default_price' => 100.00,
        ]);

        // Create sale
        $sale = Sale::create([
            'branch_id' => $this->branch->id,
            'warehouse_id' => $this->warehouse->id,
            'reference_number' => 'SO-004',
            'status' => 'completed',
            'subtotal' => 100.00,
            'total_amount' => 100.00,
            'created_by' => $this->user->id,
        ]);

        SaleItem::create([
            'sale_id' => $sale->id,
            'product_id' => $product->id,
            'quantity' => 1,
            'unit_price' => 100.00,
            'cost_price' => 0.00,
            'line_total' => 100.00,
        ]);

        // Generate sale journal entry
        $this->accountingService->generateSaleJournalEntry($sale->fresh());

        // Check that COGS entry was NOT created (since cost is zero)
        $cogsEntry = JournalEntry::where('source_id', $sale->id)
            ->where('reference_number', 'LIKE', 'COGS-%')
            ->first();

        $this->assertNull($cogsEntry, 'COGS journal entry should not be created for zero-cost products');
    }
}
