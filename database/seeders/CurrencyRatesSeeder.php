<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\CurrencyRate;
use Illuminate\Database\Seeder;

class CurrencyRatesSeeder extends Seeder
{
    public function run(): void
    {
        // Updated indicative FX rates as of 2025-11-27 (static seed, no external API).
        // These should be treated as baseline seed data only – real production systems
        // should update rates via scheduled jobs or integrations.
        // Use startOfDay() to ensure consistent datetime format for unique constraint matching
        $effectiveDate = now()->startOfDay();

        $rates = [
            // EGP base vs other currencies
            ['from_currency' => 'USD', 'to_currency' => 'EGP', 'rate' => 47.6854],
            ['from_currency' => 'EGP', 'to_currency' => 'USD', 'rate' => 0.02097],

            ['from_currency' => 'EUR', 'to_currency' => 'EGP', 'rate' => 55.352],
            ['from_currency' => 'EGP', 'to_currency' => 'EUR', 'rate' => 0.01807],

            ['from_currency' => 'GBP', 'to_currency' => 'EGP', 'rate' => 63.2168],
            ['from_currency' => 'EGP', 'to_currency' => 'GBP', 'rate' => 0.01582],

            ['from_currency' => 'SAR', 'to_currency' => 'EGP', 'rate' => 12.72],
            ['from_currency' => 'EGP', 'to_currency' => 'SAR', 'rate' => 0.0786],

            ['from_currency' => 'AED', 'to_currency' => 'EGP', 'rate' => 13.00],
            ['from_currency' => 'EGP', 'to_currency' => 'AED', 'rate' => 0.0769],

            ['from_currency' => 'KWD', 'to_currency' => 'EGP', 'rate' => 155.37],
            ['from_currency' => 'EGP', 'to_currency' => 'KWD', 'rate' => 0.00643],
        ];

        foreach ($rates as $data) {
            CurrencyRate::query()->updateOrCreate(
                [
                    'from_currency' => $data['from_currency'],
                    'to_currency' => $data['to_currency'],
                    'effective_date' => $effectiveDate,
                ],
                [
                    'rate' => $data['rate'],
                    'is_active' => true,
                    'created_by' => 1,
                ]
            );
        }
    }
}
