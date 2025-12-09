<?php

declare(strict_types=1);

namespace Tests\Unit\ValueObjects;

use App\ValueObjects\Money;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class MoneyTest extends TestCase
{
    public function test_can_create_money_instance(): void
    {
        $money = new Money('100.50', 'EGP');
        
        $this->assertEquals('100.50', $money->amount);
        $this->assertEquals('EGP', $money->currency);
    }

    public function test_can_create_from_float(): void
    {
        $money = Money::from(100.5, 'USD');
        
        $this->assertEquals('100.50', $money->amount);
        $this->assertEquals('USD', $money->currency);
    }

    public function test_throws_exception_for_non_numeric_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Amount must be numeric');
        
        new Money('invalid', 'EGP');
    }

    public function test_can_add_money(): void
    {
        $money1 = Money::from(100, 'EGP');
        $money2 = Money::from(50, 'EGP');
        
        $result = $money1->add($money2);
        
        $this->assertEquals('150.00', $result->amount);
    }

    public function test_can_subtract_money(): void
    {
        $money1 = Money::from(100, 'EGP');
        $money2 = Money::from(30, 'EGP');
        
        $result = $money1->subtract($money2);
        
        $this->assertEquals('70.00', $result->amount);
    }

    public function test_can_multiply_money(): void
    {
        $money = Money::from(100, 'EGP');
        
        $result = $money->multiply(1.5);
        
        $this->assertEquals('150.00', $result->amount);
    }

    public function test_throws_exception_when_adding_different_currencies(): void
    {
        $money1 = Money::from(100, 'EGP');
        $money2 = Money::from(50, 'USD');
        
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot perform operation on different currencies');
        
        $money1->add($money2);
    }

    public function test_can_format_money(): void
    {
        $money = Money::from(1234.56, 'EGP');
        
        $this->assertEquals('1,234.56 EGP', $money->format());
    }

    public function test_can_convert_to_float(): void
    {
        $money = Money::from(123.45, 'EGP');
        
        $this->assertEquals(123.45, $money->toFloat());
    }

    public function test_can_check_if_zero(): void
    {
        $zeroMoney = Money::from(0, 'EGP');
        $nonZeroMoney = Money::from(100, 'EGP');
        
        $this->assertTrue($zeroMoney->isZero());
        $this->assertFalse($nonZeroMoney->isZero());
    }

    public function test_can_check_if_positive(): void
    {
        $positive = Money::from(100, 'EGP');
        $negative = new Money('-50.00', 'EGP');
        
        $this->assertTrue($positive->isPositive());
        $this->assertFalse($negative->isPositive());
    }

    public function test_can_check_if_negative(): void
    {
        $negative = new Money('-50.00', 'EGP');
        $positive = Money::from(100, 'EGP');
        
        $this->assertTrue($negative->isNegative());
        $this->assertFalse($positive->isNegative());
    }

    public function test_can_convert_to_string(): void
    {
        $money = Money::from(100, 'EGP');
        
        $this->assertEquals('100.00 EGP', (string) $money);
    }
}
