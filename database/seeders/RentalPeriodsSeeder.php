<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Module;
use App\Models\RentalPeriod;
use Illuminate\Database\Seeder;

class RentalPeriodsSeeder extends Seeder
{
    public function run(): void
    {
        // Find the rental module
        $rentalModule = Module::where('key', 'rental')->first();

        if (! $rentalModule) {
            $this->command->warn('Rental module not found. Skipping rental periods seeder.');

            return;
        }

        $periods = [
            [
                'period_key' => 'hourly',
                'period_name' => 'Hourly',
                'period_name_ar' => 'بالساعة',
                'period_type' => 'hourly',
                'duration_value' => 1,
                'duration_unit' => 'hours',
                'price_multiplier' => 0.005,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 1,
            ],
            [
                'period_key' => 'daily',
                'period_name' => 'Daily',
                'period_name_ar' => 'يومي',
                'period_type' => 'daily',
                'duration_value' => 1,
                'duration_unit' => 'days',
                'price_multiplier' => 0.05,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 2,
            ],
            [
                'period_key' => 'weekly',
                'period_name' => 'Weekly',
                'period_name_ar' => 'أسبوعي',
                'period_type' => 'weekly',
                'duration_value' => 1,
                'duration_unit' => 'weeks',
                'price_multiplier' => 0.25,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 3,
            ],
            [
                'period_key' => 'monthly',
                'period_name' => 'Monthly',
                'period_name_ar' => 'شهري',
                'period_type' => 'monthly',
                'duration_value' => 1,
                'duration_unit' => 'months',
                'price_multiplier' => 1.0,
                'is_default' => true,
                'is_active' => true,
                'sort_order' => 4,
            ],
            [
                'period_key' => 'quarterly',
                'period_name' => 'Quarterly',
                'period_name_ar' => 'ربع سنوي',
                'period_type' => 'quarterly',
                'duration_value' => 3,
                'duration_unit' => 'months',
                'price_multiplier' => 2.8,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 5,
            ],
            [
                'period_key' => 'semi_annual',
                'period_name' => 'Semi-Annual',
                'period_name_ar' => 'نصف سنوي',
                'period_type' => 'custom',
                'duration_value' => 6,
                'duration_unit' => 'months',
                'price_multiplier' => 5.5,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 6,
            ],
            [
                'period_key' => 'yearly',
                'period_name' => 'Yearly',
                'period_name_ar' => 'سنوي',
                'period_type' => 'yearly',
                'duration_value' => 1,
                'duration_unit' => 'years',
                'price_multiplier' => 11.0,
                'is_default' => false,
                'is_active' => true,
                'sort_order' => 7,
            ],
        ];

        foreach ($periods as $period) {
            // Ensure module_id is included in the data to be created/updated
            $period['module_id'] = $rentalModule->id;
            
            RentalPeriod::updateOrCreate(
                [
                    'module_id' => $rentalModule->id,
                    'period_key' => $period['period_key'],
                ],
                $period
            );
        }

        $this->command->info('Rental periods seeded successfully.');
    }
}
