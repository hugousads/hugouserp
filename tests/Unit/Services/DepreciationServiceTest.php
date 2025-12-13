<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Models\FixedAsset;
use App\Services\DepreciationService;
use Tests\TestCase;

class DepreciationServiceTest extends TestCase
{
    public function test_get_depreciation_schedule_returns_empty_when_no_start_date(): void
    {
        $service = new DepreciationService();
        $asset = new FixedAsset([
            'purchase_cost' => 1000,
            'salvage_value' => 100,
            'useful_life_years' => 5,
            'useful_life_months' => 0,
            'depreciation_start_date' => null,
        ]);

        $this->assertSame([], $service->getDepreciationSchedule($asset));
    }
}
