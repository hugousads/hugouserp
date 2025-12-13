<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Models\CurrencyRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CurrencyRateTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_does_not_cache_missing_rates(): void
    {
        $date = now()->toDateString();

        Cache::flush();

        $this->assertNull(CurrencyRate::getRate('USD', 'EUR', $date));

        CurrencyRate::create([
            'from_currency' => 'USD',
            'to_currency' => 'EUR',
            'rate' => 1.23,
            'effective_date' => $date,
            'is_active' => true,
        ]);

        $this->assertSame(1.23, CurrencyRate::getRate('USD', 'EUR', $date));
    }
}
