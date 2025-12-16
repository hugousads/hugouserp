<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            SystemSettingsSeeder::class,
            BranchesSeeder::class,
            ModulesSeeder::class,
            ModuleArchitectureSeeder::class,
            ModuleNavigationSeeder::class,
            RolesAndPermissionsSeeder::class,
            UsersSeeder::class,
            AdvancedReportPermissionsSeeder::class,
            CurrencySeeder::class,
            CurrencyRatesSeeder::class,
            ChartOfAccountsSeeder::class,
            VehicleModelsSeeder::class,
            ReportTemplatesSeeder::class,
            PreConfiguredModulesSeeder::class,
            RentalPeriodsSeeder::class,
        ]);
    }
}
