<?php

declare(strict_types=1);

namespace Tests\Unit;

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
    public function it_generates_initials_from_name(): void
    {
        $this->assertEquals('JD', $this->service->getInitials('John Doe'));
        $this->assertEquals('AB', $this->service->getInitials('Alice Bob'));
        $this->assertEquals('J', $this->service->getInitials('John', 1));
        $this->assertEquals('JDS', $this->service->getInitials('John Doe Smith', 3));
    }

    /** @test */
    public function it_generates_status_badge_classes(): void
    {
        $activeClass = $this->service->getStatusBadgeClass('active');
        $this->assertStringContainsString('emerald', $activeClass);

        $inactiveClass = $this->service->getStatusBadgeClass('inactive');
        $this->assertStringContainsString('gray', $inactiveClass);

        $pendingClass = $this->service->getStatusBadgeClass('pending');
        $this->assertStringContainsString('amber', $pendingClass);
    }

    /** @test */
    public function it_formats_currency_correctly(): void
    {
        $formatted = $this->service->formatCurrency(1234.56, 'USD');
        $this->assertStringContainsString('1,234.56', $formatted);
        $this->assertStringContainsString('$', $formatted);

        $formatted = $this->service->formatCurrency(1234.56, 'EUR');
        $this->assertStringContainsString('€', $formatted);
    }

    /** @test */
    public function it_formats_currency_without_symbol(): void
    {
        $formatted = $this->service->formatCurrency(1234.56, 'USD', showSymbol: false);
        $this->assertEquals('1,234.56', $formatted);
        $this->assertStringNotContainsString('$', $formatted);
    }

    /** @test */
    public function it_generates_avatar_color(): void
    {
        $color1 = $this->service->getAvatarColor('John Doe');
        $color2 = $this->service->getAvatarColor('Jane Smith');

        $this->assertStringStartsWith('bg-', $color1);
        $this->assertStringStartsWith('bg-', $color2);

        // Same name should give same color
        $this->assertEquals(
            $this->service->getAvatarColor('John Doe'),
            $this->service->getAvatarColor('John Doe')
        );
    }

    /** @test */
    public function it_formats_bytes(): void
    {
        $this->assertEquals('1024 B', $this->service->formatBytes(1024));
        $this->assertEquals('1 KB', $this->service->formatBytes(1025));
        $this->assertEquals('1 MB', $this->service->formatBytes(1024 * 1024 + 1));
        $this->assertEquals('1 GB', $this->service->formatBytes(1024 * 1024 * 1024 + 1));
        $this->assertEquals('500 B', $this->service->formatBytes(500));
    }

    /** @test */
    public function it_truncates_text(): void
    {
        $text = 'This is a very long text that needs to be truncated';
        $truncated = $this->service->truncate($text, 20);

        $this->assertLessThanOrEqual(20, mb_strlen($truncated));
        $this->assertStringEndsWith('...', $truncated);
    }

    /** @test */
    public function it_does_not_truncate_short_text(): void
    {
        $text = 'Short text';
        $truncated = $this->service->truncate($text, 20);

        $this->assertEquals($text, $truncated);
        $this->assertStringEndsNotWith('...', $truncated);
    }

    /** @test */
    public function it_handles_lengths_shorter_than_ellipsis(): void
    {
        $text = 'Sample';

        $this->assertEquals('Sa', $this->service->truncate($text, 2));
    }

    /** @test */
    public function it_formats_pagination_summary(): void
    {
        $summary = $this->service->getPaginationSummary(1, 15, 100);

        $this->assertStringContainsString('1', $summary);
        $this->assertStringContainsString('15', $summary);
        $this->assertStringContainsString('100', $summary);
    }

    /** @test */
    public function it_generates_data_attributes(): void
    {
        $data = [
            'id' => '123',
            'name' => 'Test Name',
        ];

        $attributes = $this->service->dataAttributes($data);

        $this->assertStringContainsString('data-id="123"', $attributes);
        $this->assertStringContainsString('data-name="Test Name"', $attributes);
    }

    /** @test */
    public function it_escapes_data_attributes(): void
    {
        $data = [
            'value' => '<script>alert("xss")</script>',
        ];

        $attributes = $this->service->dataAttributes($data);

        $this->assertStringNotContainsString('<script>', $attributes);
        $this->assertStringContainsString('&lt;', $attributes);
    }
}
