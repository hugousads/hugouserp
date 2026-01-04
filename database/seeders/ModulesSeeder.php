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
        // Module Types:
        // 1. Data Modules (supports_items=true): These modules have their own items/products
        //    - inventory: General products that can be sold/purchased
        //    - motorcycle: Motorcycle products (bikes, accessories)
        //    - spares: Spare parts with vehicle compatibility
        //    - wood: Wood/lumber products
        //    - rental: Rental units/properties
        //    - manufacturing: Raw materials and finished goods
        //
        // 2. Operational Modules (supports_items=false): These modules USE products from data modules
        //    - sales: Uses products from inventory/motorcycle/spares/wood to create sales
        //    - purchases: Purchases products for inventory/motorcycle/spares/wood
        //    - pos: Point of sale - uses products from data modules
        //    - hrm: Employee management - no products
        //    - reports: Reporting - no products
        //    - accounting: Financial management - no products
        //    - projects: Project management - no products
        //    - documents: Document storage - no products
        //    - helpdesk: Support tickets - no products
        
        $modules = [
            // === DATA MODULES (have their own products/items) ===
            ['key' => 'inventory',      'name' => 'Inventory',          'name_ar' => 'المخزون',           'version' => '1.0.0', 'is_core' => true,  'supports_items' => true,  'module_type' => 'data',       'icon' => '📦'],
            ['key' => 'motorcycle',     'name' => 'Motorcycle',         'name_ar' => 'الدراجات النارية',  'version' => '1.0.0', 'is_core' => false, 'supports_items' => true,  'module_type' => 'data',       'icon' => '🏍️'],
            ['key' => 'spares',         'name' => 'Spare Parts',        'name_ar' => 'قطع الغيار',        'version' => '1.0.0', 'is_core' => false, 'supports_items' => true,  'module_type' => 'data',       'icon' => '🔧'],
            ['key' => 'wood',           'name' => 'Wood',               'name_ar' => 'الأخشاب',           'version' => '1.0.0', 'is_core' => false, 'supports_items' => true,  'module_type' => 'data',       'icon' => '🪵'],
            ['key' => 'rental',         'name' => 'Rental',             'name_ar' => 'الإيجارات',         'version' => '1.0.0', 'is_core' => false, 'supports_items' => true,  'module_type' => 'data',       'icon' => '🏠'],
            ['key' => 'manufacturing',  'name' => 'Manufacturing',      'name_ar' => 'التصنيع',           'version' => '1.0.0', 'is_core' => false, 'supports_items' => true,  'module_type' => 'data',       'icon' => '🏭'],
            
            // === OPERATIONAL MODULES (use products from data modules) ===
            ['key' => 'sales',          'name' => 'Sales',              'name_ar' => 'المبيعات',          'version' => '1.0.0', 'is_core' => true,  'supports_items' => false, 'module_type' => 'functional', 'icon' => '💰'],
            ['key' => 'purchases',      'name' => 'Purchases',          'name_ar' => 'المشتريات',         'version' => '1.0.0', 'is_core' => true,  'supports_items' => false, 'module_type' => 'functional', 'icon' => '🛒'],
            ['key' => 'pos',            'name' => 'Point of Sale',      'name_ar' => 'نقاط البيع',        'version' => '1.0.0', 'is_core' => true,  'supports_items' => false, 'module_type' => 'functional', 'icon' => '🖥️'],
            
            // === MANAGEMENT MODULES (no products) ===
            ['key' => 'hrm',            'name' => 'Human Resources',    'name_ar' => 'الموارد البشرية',   'version' => '1.0.0', 'is_core' => false, 'supports_items' => false, 'module_type' => 'functional', 'icon' => '👥'],
            ['key' => 'reports',        'name' => 'Reports',            'name_ar' => 'التقارير',          'version' => '1.0.0', 'is_core' => true,  'supports_items' => false, 'module_type' => 'functional', 'icon' => '📊'],
            ['key' => 'accounting',     'name' => 'Accounting',         'name_ar' => 'المحاسبة',          'version' => '1.0.0', 'is_core' => true,  'supports_items' => false, 'module_type' => 'functional', 'icon' => '🧮'],
            ['key' => 'projects',       'name' => 'Projects',           'name_ar' => 'المشاريع',          'version' => '1.0.0', 'is_core' => false, 'supports_items' => false, 'module_type' => 'functional', 'icon' => '📋'],
            ['key' => 'documents',      'name' => 'Documents',          'name_ar' => 'المستندات',         'version' => '1.0.0', 'is_core' => false, 'supports_items' => false, 'module_type' => 'functional', 'icon' => '📁'],
            ['key' => 'helpdesk',       'name' => 'Helpdesk',           'name_ar' => 'الدعم الفني',       'version' => '1.0.0', 'is_core' => false, 'supports_items' => false, 'module_type' => 'functional', 'icon' => '🎫'],
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
                    'module_type' => $row['module_type'] ?? 'functional',
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
