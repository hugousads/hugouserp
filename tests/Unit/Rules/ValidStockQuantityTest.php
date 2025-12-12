<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\ValidStockQuantity;
use PHPUnit\Framework\TestCase;

class ValidStockQuantityTest extends TestCase
{
    public function test_rejects_decimal_separator_with_zero_decimal_places(): void
    {
        $rule = new ValidStockQuantity(maxQuantity: 999999.99, decimalPlaces: 0);
        $failed = false;

        $rule->validate('quantity', '100.', function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Should reject "100." when decimalPlaces is 0');
    }

    public function test_accepts_whole_number_with_zero_decimal_places(): void
    {
        $rule = new ValidStockQuantity(maxQuantity: 999999.99, decimalPlaces: 0);
        $failed = false;

        $rule->validate('quantity', '100', function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Should accept "100" when decimalPlaces is 0');
    }

    public function test_accepts_decimal_with_two_decimal_places(): void
    {
        $rule = new ValidStockQuantity(maxQuantity: 999999.99, decimalPlaces: 2);
        $failed = false;

        $rule->validate('quantity', '100.50', function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Should accept "100.50" when decimalPlaces is 2');
    }

    public function test_rejects_zero_when_not_allowed(): void
    {
        $rule = new ValidStockQuantity(maxQuantity: 999999.99, decimalPlaces: 2, allowZero: false);
        $failed = false;

        $rule->validate('quantity', '0', function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Should reject zero when allowZero is false');
    }

    public function test_accepts_zero_when_allowed(): void
    {
        $rule = new ValidStockQuantity(maxQuantity: 999999.99, decimalPlaces: 2, allowZero: true);
        $failed = false;

        $rule->validate('quantity', '0', function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Should accept zero when allowZero is true');
    }

    public function test_rejects_negative_value(): void
    {
        $rule = new ValidStockQuantity(maxQuantity: 999999.99, decimalPlaces: 2);
        $failed = false;

        $rule->validate('quantity', '-10', function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Should reject negative values');
    }
}
