<?php

declare(strict_types=1);

namespace Tests\Unit\Rules;

use App\Rules\ValidDiscountPercentage;
use PHPUnit\Framework\TestCase;

class ValidDiscountPercentageTest extends TestCase
{
    public function test_rejects_decimal_separator_with_zero_decimal_places(): void
    {
        $rule = new ValidDiscountPercentage(maxDiscount: 100.0, decimalPlaces: 0);
        $failed = false;

        $rule->validate('discount', '10.', function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Should reject "10." when decimalPlaces is 0');
    }

    public function test_accepts_whole_number_with_zero_decimal_places(): void
    {
        $rule = new ValidDiscountPercentage(maxDiscount: 100.0, decimalPlaces: 0);
        $failed = false;

        $rule->validate('discount', '10', function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Should accept "10" when decimalPlaces is 0');
    }

    public function test_accepts_decimal_with_two_decimal_places(): void
    {
        $rule = new ValidDiscountPercentage(maxDiscount: 100.0, decimalPlaces: 2);
        $failed = false;

        $rule->validate('discount', '10.50', function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertFalse($failed, 'Should accept "10.50" when decimalPlaces is 2');
    }

    public function test_rejects_value_exceeding_max_discount(): void
    {
        $rule = new ValidDiscountPercentage(maxDiscount: 50.0, decimalPlaces: 2);
        $failed = false;

        $rule->validate('discount', '75.00', function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Should reject value exceeding max discount');
    }

    public function test_rejects_negative_value(): void
    {
        $rule = new ValidDiscountPercentage(maxDiscount: 100.0, decimalPlaces: 2);
        $failed = false;

        $rule->validate('discount', '-10', function ($message) use (&$failed) {
            $failed = true;
        });

        $this->assertTrue($failed, 'Should reject negative values');
    }
}
