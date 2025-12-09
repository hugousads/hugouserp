<?php

declare(strict_types=1);

namespace Tests\Unit\Exceptions;

use App\Exceptions\BusinessException;
use App\Exceptions\InsufficientStockException;
use App\Exceptions\InvalidDiscountException;
use App\Exceptions\NoBranchSelectedException;
use PHPUnit\Framework\TestCase;

class BusinessExceptionTest extends TestCase
{
    public function test_business_exception_has_default_message(): void
    {
        $exception = new BusinessException();
        
        $this->assertEquals('A business logic error occurred', $exception->getMessage());
        $this->assertEquals(422, $exception->getCode());
    }

    public function test_business_exception_accepts_custom_message(): void
    {
        $exception = new BusinessException('Custom error', 400);
        
        $this->assertEquals('Custom error', $exception->getMessage());
        $this->assertEquals(400, $exception->getCode());
    }

    public function test_business_exception_returns_http_status_code(): void
    {
        $exception = new BusinessException('Error', 404);
        
        $this->assertEquals(404, $exception->getStatusCode());
    }

    public function test_business_exception_should_not_report(): void
    {
        $exception = new BusinessException();
        
        $this->assertFalse($exception->shouldReport());
    }

    public function test_insufficient_stock_exception_formats_message(): void
    {
        $exception = new InsufficientStockException('Product A', 10, 15);
        
        $this->assertStringContainsString('Product A', $exception->getMessage());
        $this->assertStringContainsString('10', $exception->getMessage());
        $this->assertStringContainsString('15', $exception->getMessage());
    }

    public function test_invalid_discount_exception_formats_percent_message(): void
    {
        $exception = new InvalidDiscountException(60, 50, 'percent');
        
        $this->assertStringContainsString('60', $exception->getMessage());
        $this->assertStringContainsString('50', $exception->getMessage());
        $this->assertStringContainsString('%', $exception->getMessage());
    }

    public function test_invalid_discount_exception_formats_amount_message(): void
    {
        $exception = new InvalidDiscountException(1500, 1000, 'amount');
        
        $this->assertStringContainsString('1500', $exception->getMessage());
        $this->assertStringContainsString('1000', $exception->getMessage());
        $this->assertStringContainsString('EGP', $exception->getMessage());
    }

    public function test_no_branch_selected_exception_has_default_message(): void
    {
        $exception = new NoBranchSelectedException();
        
        $this->assertStringContainsString('branch', strtolower($exception->getMessage()));
    }

    public function test_no_branch_selected_exception_accepts_custom_message(): void
    {
        $exception = new NoBranchSelectedException('Custom branch message');
        
        $this->assertEquals('Custom branch message', $exception->getMessage());
    }

    public function test_all_business_exceptions_extend_base_exception(): void
    {
        $this->assertInstanceOf(BusinessException::class, new InsufficientStockException('P', 1, 2));
        $this->assertInstanceOf(BusinessException::class, new InvalidDiscountException(10, 5));
        $this->assertInstanceOf(BusinessException::class, new NoBranchSelectedException());
    }
}
