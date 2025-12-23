<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Example unit test for money helper functions.
 * 
 * NOTE: This test does not use RefreshDatabase because it only tests
 * helper functions and does not interact with the database or persist data.
 */
class ExampleTest extends TestCase
{
    /**
     * Test money helper formats USD correctly.
     */
    public function test_money_formats_usd_correctly(): void
    {
        $this->assertEquals('1,234.50 USD', money(1234.5, 'USD'));
    }

    /**
     * Test money helper formats zero amount correctly.
     */
    public function test_money_formats_zero_correctly(): void
    {
        $this->assertEquals('0.00 EGP', money(0, 'EGP'));
    }

    /**
     * Test money helper truncates to two decimal places (bcadd behavior).
     */
    public function test_money_rounds_to_two_decimals(): void
    {
        // bcadd truncates rather than rounds, so 9999.987 becomes 9999.98
        $this->assertEquals('9,999.98 EUR', money(9999.987, 'EUR'));
    }
}
