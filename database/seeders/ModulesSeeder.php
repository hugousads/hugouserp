<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\BranchModule;
use App\Models\Module;
use Illuminate\Database\Seeder;

class ModulesSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            ['key' => 'inventory',      'name' => 'Inventory',          'version' => '1.0.0', 'is_core' => true,  'supports_items' => true],
            ['key' => 'sales',          'name' => 'Sales',              'version' => '1.0.0', 'is_core' => true,  'supports_items' => true],
            ['key' => 'purchases',      'name' => 'Purchases',          'version' => '1.0.0', 'is_core' => true,  'supports_items' => true],
            ['key' => 'pos',            'name' => 'Point of Sale',      'version' => '1.0.0', 'is_core' => true,  'supports_items' => true],
            ['key' => 'manufacturing',  'name' => 'Manufacturing',      'version' => '1.0.0', 'is_core' => false, 'supports_items' => true],
            ['key' => 'rental',         'name' => 'Rental',             'version' => '1.0.0', 'is_core' => false, 'supports_items' => true],
            ['key' => 'motorcycle',     'name' => 'Motorcycle',         'version' => '1.0.0', 'is_core' => false, 'supports_items' => true],
            ['key' => 'spares',         'name' => 'Spares',             'version' => '1.0.0', 'is_core' => false, 'supports_items' => true],
            ['key' => 'wood',           'name' => 'Wood',               'version' => '1.0.0', 'is_core' => false, 'supports_items' => true],
            ['key' => 'hrm',            'name' => 'HRM',                'version' => '1.0.0', 'is_core' => false, 'supports_items' => false],
            ['key' => 'reports',        'name' => 'Reports',            'version' => '1.0.0', 'is_core' => true,  'supports_items' => false],
        ];

        $createdModules = [];

        foreach ($modules as $row) {
            $module = Module::query()->updateOrCreate(
                ['key' => $row['key']],
                [
                    'name' => $row['name'],
                    'version' => $row['version'],
                    'is_core' => $row['is_core'],
                    'is_active' => true,
                    'supports_items' => $row['supports_items'] ?? false,
                    'description' => $row['name'].' module',
                ]
            );

            $createdModules[$row['key']] = $module;
        }

        /** @var Branch|null $branch */
        $branch = Branch::query()->where('is_main', true)->first() ?? Branch::query()->first();

        if (! $branch) {
            return;
        }

        foreach ($createdModules as $key => $module) {
            BranchModule::query()->updateOrCreate(
                [
                    'branch_id' => $branch->id,
                    'module_key' => $key,
                ],
                [
                    'module_id' => $module->id,
                    'enabled' => true,
                    'settings' => [],
                ]
            );
        }
    }
}
