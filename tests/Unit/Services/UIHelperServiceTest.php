<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\UIHelperService;
use Tests\TestCase;

class UIHelperServiceTest extends TestCase
{
    private UIHelperService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new UIHelperService;
    }

    /** @test */
    public function it_formats_bytes_using_binary_boundaries(): void
    {
        $this->assertSame('1 KB', $this->service->formatBytes(1024));
        $this->assertSame('1.5 KB', $this->service->formatBytes(1536, precision: 1));
        $this->assertSame('999 B', $this->service->formatBytes(999, precision: 0));
    }

    /** @test */
    public function it_removes_trailing_zeros_in_formatted_bytes(): void
    {
        // Test that values like 1.00 KB become 1 KB
        $this->assertSame('1 KB', $this->service->formatBytes(1024, precision: 2));
        $this->assertSame('2 MB', $this->service->formatBytes(2 * 1024 * 1024, precision: 2));
    }

    /** @test */
    public function it_handles_rounding_near_unit_boundaries(): void
    {
        // Test value just under 1024 KB (1 MB boundary)
        $result = $this->service->formatBytes(1023 * 1024, precision: 2);
        $this->assertStringContainsString('KB', $result);
        
        // Test value just over 1024 KB (1 MB boundary)
        $result = $this->service->formatBytes(1025 * 1024, precision: 2);
        $this->assertStringContainsString('MB', $result);
    }

    /** @test */
    public function it_promotes_to_next_unit_when_rounding_reaches_1024(): void
    {
        // Regression test: (1024*1024 - 1) bytes should format as "1 MB"
        // This is 1048575 bytes = 1023.999... KB, which rounds to 1024 KB
        // Should be promoted to 1 MB
        $this->assertSame('1 MB', $this->service->formatBytes((1024 * 1024) - 1, precision: 2));
        
        // Similar test for GB boundary
        $this->assertSame('1 GB', $this->service->formatBytes((1024 * 1024 * 1024) - 1, precision: 2));
    }

    /** @test */
    public function it_formats_large_values_without_thousand_separators(): void
    {
        // Test that 1023 KB doesn't show thousand separators (e.g., not "1,023 KB")
        $result = $this->service->formatBytes(1023 * 1024, precision: 0);
        $this->assertSame('1023 KB', $result);
        $this->assertStringNotContainsString(',', $result);
    }
}
