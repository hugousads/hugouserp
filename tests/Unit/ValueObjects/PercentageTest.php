<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\Money;
use App\ValueObjects\Percentage;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class PercentageTest extends TestCase
{
    public function test_can_create_percentage_instance(): void
    {
        $percentage = new Percentage(15.5);
        
        $this->assertEquals(15.5, $percentage->value);
    }

    public function test_throws_exception_for_negative_value(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Percentage must be between 0 and 100');
        
        new Percentage(-5);
    }

    public function test_throws_exception_for_value_over_100(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Percentage must be between 0 and 100');
        
        new Percentage(105);
    }

    public function test_can_create_from_decimal(): void
    {
        $percentage = Percentage::fromDecimal(0.15);
        
        $this->assertEquals(15.0, $percentage->value);
    }

    public function test_can_apply_to_money(): void
    {
        $percentage = new Percentage(20);
        $money = Money::from(100, 'EGP');
        
        $result = $percentage->apply($money);
        
        $this->assertEquals('20.00', $result->amount);
    }

    public function test_can_apply_discount(): void
    {
        $percentage = new Percentage(20);
        $money = Money::from(100, 'EGP');
        
        $result = $percentage->applyDiscount($money);
        
        $this->assertEquals('80.00', $result->amount);
    }

    public function test_can_convert_to_decimal(): void
    {
        $percentage = new Percentage(25);
        
        $this->assertEquals(0.25, $percentage->toDecimal());
    }

    public function test_can_format_percentage(): void
    {
        $percentage = new Percentage(15.75);
        
        $this->assertEquals('15.75%', $percentage->format());
    }

    public function test_can_convert_to_string(): void
    {
        $percentage = new Percentage(20);
        
        $this->assertEquals('20.00%', (string) $percentage);
    }

    public function test_can_check_if_zero(): void
    {
        $zero = new Percentage(0);
        $nonZero = new Percentage(10);
        
        $this->assertTrue($zero->isZero());
        $this->assertFalse($nonZero->isZero());
    }

    public function test_can_check_if_full(): void
    {
        $full = new Percentage(100);
        $notFull = new Percentage(50);
        
        $this->assertTrue($full->isFull());
        $this->assertFalse($notFull->isFull());
    }

    public function test_allows_zero_percentage(): void
    {
        $percentage = new Percentage(0);
        
        $this->assertEquals(0.0, $percentage->value);
    }

    public function test_allows_100_percentage(): void
    {
        $percentage = new Percentage(100);
        
        $this->assertEquals(100.0, $percentage->value);
    }

    public function test_percentage_calculation_precision(): void
    {
        $percentage = new Percentage(33.33);
        $money = Money::from(100, 'EGP');
        
        $result = $percentage->apply($money);
        
        // Should maintain 2 decimal precision
        $this->assertEquals('33.33', $result->amount);
    }
}
