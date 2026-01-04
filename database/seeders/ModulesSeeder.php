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
            ['key' => 'inventory',      'name' => 'Inventory',          'name_ar' => 'المخزون',           'version' => '1.0.0', 'is_core' => true,  'supports_items' => true,  'icon' => 'cube'],
            ['key' => 'sales',          'name' => 'Sales',              'name_ar' => 'المبيعات',          'version' => '1.0.0', 'is_core' => true,  'supports_items' => true,  'icon' => 'shopping-cart'],
            ['key' => 'purchases',      'name' => 'Purchases',          'name_ar' => 'المشتريات',         'version' => '1.0.0', 'is_core' => true,  'supports_items' => true,  'icon' => 'truck'],
            ['key' => 'pos',            'name' => 'Point of Sale',      'name_ar' => 'نقاط البيع',        'version' => '1.0.0', 'is_core' => true,  'supports_items' => true,  'icon' => 'cash-register'],
            ['key' => 'manufacturing',  'name' => 'Manufacturing',      'name_ar' => 'التصنيع',           'version' => '1.0.0', 'is_core' => false, 'supports_items' => true,  'icon' => 'cog'],
            ['key' => 'rental',         'name' => 'Rental',             'name_ar' => 'الإيجارات',         'version' => '1.0.0', 'is_core' => false, 'supports_items' => true,  'icon' => 'key'],
            ['key' => 'motorcycle',     'name' => 'Motorcycle',         'name_ar' => 'الدراجات النارية',  'version' => '1.0.0', 'is_core' => false, 'supports_items' => true,  'icon' => 'motorcycle'],
            ['key' => 'spares',         'name' => 'Spare Parts',        'name_ar' => 'قطع الغيار',        'version' => '1.0.0', 'is_core' => false, 'supports_items' => true,  'icon' => 'tools'],
            ['key' => 'wood',           'name' => 'Wood',               'name_ar' => 'الأخشاب',           'version' => '1.0.0', 'is_core' => false, 'supports_items' => true,  'icon' => 'tree'],
            ['key' => 'hrm',            'name' => 'Human Resources',    'name_ar' => 'الموارد البشرية',   'version' => '1.0.0', 'is_core' => false, 'supports_items' => false, 'icon' => 'users'],
            ['key' => 'reports',        'name' => 'Reports',            'name_ar' => 'التقارير',          'version' => '1.0.0', 'is_core' => true,  'supports_items' => false, 'icon' => 'chart-bar'],
            ['key' => 'accounting',     'name' => 'Accounting',         'name_ar' => 'المحاسبة',          'version' => '1.0.0', 'is_core' => true,  'supports_items' => false, 'icon' => 'calculator'],
            ['key' => 'projects',       'name' => 'Projects',           'name_ar' => 'المشاريع',          'version' => '1.0.0', 'is_core' => false, 'supports_items' => false, 'icon' => 'briefcase'],
            ['key' => 'documents',      'name' => 'Documents',          'name_ar' => 'المستندات',         'version' => '1.0.0', 'is_core' => false, 'supports_items' => false, 'icon' => 'document'],
            ['key' => 'helpdesk',       'name' => 'Helpdesk',           'name_ar' => 'الدعم الفني',       'version' => '1.0.0', 'is_core' => false, 'supports_items' => false, 'icon' => 'ticket'],
        ];

        $createdModules = [];

        foreach ($modules as $row) {
            $module = Module::query()->updateOrCreate(
                ['key' => $row['key']],
                [
                    'name' => $row['name'],
                    'name_ar' => $row['name_ar'] ?? null,
                    'version' => $row['version'],
                    'is_core' => $row['is_core'],
                    'is_active' => true,
                    'supports_items' => $row['supports_items'] ?? false,
                    'description' => $row['name'] . ' module',
                    'description_ar' => 'وحدة ' . ($row['name_ar'] ?? $row['name']),
                    'icon' => $row['icon'] ?? null,
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
