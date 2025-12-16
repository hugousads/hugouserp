<?php

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Models\Module;
use App\Models\RentalPeriod;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RentalPeriodCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Module $module;

    protected function setUp(): void
    {
        parent::setUp();

        $this->module = Module::create([
            'key' => 'rental',
            'name' => 'Rental',
            'is_active' => true,
            'is_rental' => true,
            'supports_items' => true,
        ]);

        $this->user = User::factory()->create();
    }

    public function test_can_create_rental_period(): void
    {
        $this->actingAs($this->user);

        $data = [
            'module_id' => $this->module->id,
            'period_key' => 'monthly',
            'period_name' => 'Monthly',
            'period_name_ar' => 'شهري',
            'period_type' => 'monthly',
            'duration_value' => 1,
            'duration_unit' => 'months',
            'price_multiplier' => 1.0,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 1,
        ];

        $period = RentalPeriod::create($data);

        $this->assertDatabaseHas('rental_periods', [
            'period_key' => 'monthly',
            'period_name' => 'Monthly',
            'module_id' => $this->module->id,
        ]);
    }

    public function test_can_read_rental_period(): void
    {
        $period = RentalPeriod::create([
            'module_id' => $this->module->id,
            'period_key' => 'weekly',
            'period_name' => 'Weekly',
            'period_type' => 'weekly',
            'duration_value' => 1,
            'duration_unit' => 'weeks',
            'price_multiplier' => 0.25,
            'is_active' => true,
        ]);

        $found = RentalPeriod::find($period->id);

        $this->assertNotNull($found);
        $this->assertEquals('Weekly', $found->period_name);
    }

    public function test_can_update_rental_period(): void
    {
        $period = RentalPeriod::create([
            'module_id' => $this->module->id,
            'period_key' => 'daily',
            'period_name' => 'Daily',
            'period_type' => 'daily',
            'duration_value' => 1,
            'duration_unit' => 'days',
            'price_multiplier' => 0.05,
            'is_active' => true,
        ]);

        $period->update([
            'price_multiplier' => 0.04,
            'period_name' => 'Daily Rate',
        ]);

        $this->assertDatabaseHas('rental_periods', [
            'id' => $period->id,
            'period_name' => 'Daily Rate',
        ]);
    }

    public function test_can_delete_rental_period(): void
    {
        $period = RentalPeriod::create([
            'module_id' => $this->module->id,
            'period_key' => 'to_delete',
            'period_name' => 'To Delete',
            'period_type' => 'custom',
            'duration_value' => 1,
            'duration_unit' => 'days',
            'price_multiplier' => 1.0,
            'is_active' => true,
        ]);

        $period->delete();

        $this->assertDatabaseMissing('rental_periods', [
            'id' => $period->id,
        ]);
    }

    public function test_calculate_days_for_various_units(): void
    {
        $dayPeriod = RentalPeriod::create([
            'module_id' => $this->module->id,
            'period_key' => 'day',
            'period_name' => 'Day',
            'period_type' => 'daily',
            'duration_value' => 7,
            'duration_unit' => 'days',
            'price_multiplier' => 1.0,
            'is_active' => true,
        ]);

        $weekPeriod = RentalPeriod::create([
            'module_id' => $this->module->id,
            'period_key' => 'week',
            'period_name' => 'Week',
            'period_type' => 'weekly',
            'duration_value' => 2,
            'duration_unit' => 'weeks',
            'price_multiplier' => 1.0,
            'is_active' => true,
        ]);

        $monthPeriod = RentalPeriod::create([
            'module_id' => $this->module->id,
            'period_key' => 'month',
            'period_name' => 'Month',
            'period_type' => 'monthly',
            'duration_value' => 1,
            'duration_unit' => 'months',
            'price_multiplier' => 1.0,
            'is_active' => true,
        ]);

        $this->assertEquals(7, $dayPeriod->calculateDays());
        $this->assertEquals(14, $weekPeriod->calculateDays());
        $this->assertEquals(30, $monthPeriod->calculateDays());
    }

    public function test_calculate_price_with_multiplier(): void
    {
        $period = RentalPeriod::create([
            'module_id' => $this->module->id,
            'period_key' => 'quarterly',
            'period_name' => 'Quarterly',
            'period_type' => 'quarterly',
            'duration_value' => 3,
            'duration_unit' => 'months',
            'price_multiplier' => 2.8,
            'is_active' => true,
        ]);

        $basePrice = 100.0;
        $calculatedPrice = $period->calculatePrice($basePrice);

        $this->assertEquals(280.0, $calculatedPrice);
    }

    public function test_active_scope_filters_inactive_periods(): void
    {
        RentalPeriod::create([
            'module_id' => $this->module->id,
            'period_key' => 'active_period',
            'period_name' => 'Active',
            'period_type' => 'monthly',
            'duration_value' => 1,
            'duration_unit' => 'months',
            'price_multiplier' => 1.0,
            'is_active' => true,
        ]);

        RentalPeriod::create([
            'module_id' => $this->module->id,
            'period_key' => 'inactive_period',
            'period_name' => 'Inactive',
            'period_type' => 'monthly',
            'duration_value' => 1,
            'duration_unit' => 'months',
            'price_multiplier' => 1.0,
            'is_active' => false,
        ]);

        $activePeriods = RentalPeriod::active()->get();

        $this->assertCount(1, $activePeriods);
        $this->assertEquals('Active', $activePeriods->first()->period_name);
    }

    public function test_localized_name_returns_arabic_when_locale_is_ar(): void
    {
        $period = RentalPeriod::create([
            'module_id' => $this->module->id,
            'period_key' => 'yearly',
            'period_name' => 'Yearly',
            'period_name_ar' => 'سنوي',
            'period_type' => 'yearly',
            'duration_value' => 1,
            'duration_unit' => 'years',
            'price_multiplier' => 11.0,
            'is_active' => true,
        ]);

        app()->setLocale('ar');
        $this->assertEquals('سنوي', $period->localizedName);

        app()->setLocale('en');
        $this->assertEquals('Yearly', $period->localizedName);
    }
}
