<?php

declare(strict_types=1);

namespace Tests\Feature\Sales;

use App\Models\Branch;
use App\Models\Customer;
use App\Models\Sale;
use App\Models\StoreOrder;
use App\Models\Warehouse;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SaleFinancialFieldsTest extends TestCase
{
    use RefreshDatabase;

    public function test_sale_financial_and_shipping_fields_persist_and_cast(): void
    {
        $branch = Branch::create(['name' => 'Main', 'code' => 'BR-01']);
        $warehouse = Warehouse::create(['name' => 'HQ', 'code' => 'WH-01', 'branch_id' => $branch->id]);
        $customer = Customer::create(['name' => 'Customer One', 'branch_id' => $branch->id]);
        $storeOrder = StoreOrder::create(['branch_id' => $branch->id]);

        $sale = Sale::create([
            'branch_id' => $branch->id,
            'warehouse_id' => $warehouse->id,
            'customer_id' => $customer->id,
            'status' => 'posted',
            'sub_total' => 100,
            'discount_total' => 5,
            'discount_type' => 'percentage',
            'discount_value' => 5.5,
            'tax_total' => 10,
            'shipping_total' => 7.25,
            'shipping_method' => 'Ground',
            'shipping_carrier' => 'DHL',
            'tracking_number' => 'TRK12345',
            'grand_total' => 111.75,
            'estimated_profit_margin' => 12.3456,
            'paid_total' => 50,
            'due_total' => 61.75,
            'amount_paid' => 50,
            'amount_due' => 61.75,
            'payment_status' => 'partial',
            'payment_due_date' => '2025-02-01',
            'delivery_date' => '2025-01-20',
            'expected_delivery_date' => '2025-01-22',
            'actual_delivery_date' => '2025-01-23',
            'reference_no' => 'REF-123',
            'posted_at' => now(),
            'sales_person' => 'Agent A',
            'store_order_id' => $storeOrder->id,
            'notes' => 'General note',
            'customer_notes' => 'Visible to customer',
            'internal_notes' => 'Internal only',
        ]);

        $sale = $sale->fresh();

        $this->assertDatabaseHas('sales', [
            'id' => $sale->id,
            'shipping_carrier' => 'DHL',
            'tracking_number' => 'TRK12345',
            'payment_status' => 'partial',
            'store_order_id' => $storeOrder->id,
        ]);

        $this->assertSame('percentage', $sale->discount_type);
        $this->assertSame(5.5, (float) $sale->discount_value);
        $this->assertSame(7.25, (float) $sale->shipping_total);
        $this->assertSame('2025-02-01', $sale->payment_due_date->format('Y-m-d'));
        $this->assertSame('2025-01-22', $sale->expected_delivery_date->format('Y-m-d'));
        $this->assertSame(12.3456, (float) $sale->estimated_profit_margin);
        $this->assertSame(61.75, (float) $sale->amount_due);
        $this->assertSame('Agent A', $sale->sales_person);
        $this->assertSame('Visible to customer', $sale->customer_notes);
        $this->assertSame('Internal only', $sale->internal_notes);
    }
}
