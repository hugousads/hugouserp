<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Models\GRNItem;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GRNItemTest extends TestCase
{
    use RefreshDatabase;

    public function test_discrepancy_percentage_is_always_positive(): void
    {
        $item = new GRNItem([
            'qty_ordered' => 100,
            'qty_received' => 80,
            'qty_rejected' => 0,
        ]);

        $percentage = $item->getDiscrepancyPercentage();
        $this->assertGreaterThanOrEqual(0, $percentage);
        $this->assertEquals(20.0, $percentage);
    }

    public function test_discrepancy_percentage_positive_when_over_received(): void
    {
        $item = new GRNItem([
            'qty_ordered' => 100,
            'qty_received' => 120,
            'qty_rejected' => 0,
        ]);

        $percentage = $item->getDiscrepancyPercentage();
        $this->assertGreaterThanOrEqual(0, $percentage);
        $this->assertEquals(20.0, $percentage);
    }

    public function test_discrepancy_percentage_accounts_for_rejected_items(): void
    {
        $item = new GRNItem([
            'qty_ordered' => 100,
            'qty_received' => 100,
            'qty_rejected' => 20,
        ]);

        $percentage = $item->getDiscrepancyPercentage();
        $this->assertEquals(20.0, $percentage);
    }

    public function test_discrepancy_percentage_zero_when_exact_match(): void
    {
        $item = new GRNItem([
            'qty_ordered' => 100,
            'qty_received' => 100,
            'qty_rejected' => 0,
        ]);

        $percentage = $item->getDiscrepancyPercentage();
        $this->assertEquals(0.0, $percentage);
    }

    public function test_discrepancy_percentage_zero_when_ordered_is_zero(): void
    {
        $item = new GRNItem([
            'qty_ordered' => 0,
            'qty_received' => 10,
            'qty_rejected' => 0,
        ]);

        $percentage = $item->getDiscrepancyPercentage();
        $this->assertEquals(0.0, $percentage);
    }

    public function test_has_discrepancy_when_quantities_differ(): void
    {
        $item = new GRNItem([
            'qty_ordered' => 100,
            'qty_received' => 80,
            'qty_rejected' => 0,
        ]);

        $this->assertTrue($item->hasDiscrepancy());
    }

    public function test_has_discrepancy_when_items_rejected(): void
    {
        $item = new GRNItem([
            'qty_ordered' => 100,
            'qty_received' => 100,
            'qty_rejected' => 10,
        ]);

        $this->assertTrue($item->hasDiscrepancy());
    }

    public function test_no_discrepancy_when_fully_received(): void
    {
        $item = new GRNItem([
            'qty_ordered' => 100,
            'qty_received' => 100,
            'qty_rejected' => 0,
        ]);

        $this->assertFalse($item->hasDiscrepancy());
    }
}
