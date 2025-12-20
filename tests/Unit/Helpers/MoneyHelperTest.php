<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use Tests\TestCase;

class MoneyHelperTest extends TestCase
{
    public function test_money_respects_currency_scale_and_rounds_down(): void
    {
        $this->assertSame('0.10 USD', money(0.105, 'USD'));
        $this->assertSame('0.105 KWD', money('0.1054', 'KWD'));
    }
}
