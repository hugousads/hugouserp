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
        // Module Architecture for ERP System:
        //
        // === PRODUCT/DATA MODULES (supports_items=true) ===
        // These are SPECIALIZED modules where products/items are CREATED.
        // Each has its own custom fields and business logic.
        //   - motorcycle: Motorcycles, bikes, accessories (engine_cc, frame_number, etc.)
        //   - spares: Spare parts with vehicle compatibility (OEM, fitment, etc.)
        //   - wood: Wood/lumber products (dimensions, type, grade)
        //   - rental: Rental units/properties (location, rental_period, deposit)
        //   - manufacturing: Raw materials and finished goods (BOM, recipes)
        //   - general: General products (default for misc items)
        //
        // === STOCK MANAGEMENT MODULE (supports_items=false) ===
        // "Inventory" is NOT a product type - it's for STOCK TRACKING.
        // It shows ALL products from ALL data modules and tracks:
        //   - Stock quantities, movements, adjustments
        //   - Low stock alerts, reorder points
        //   - Warehouse locations, batch tracking
        //
        // === OPERATIONAL MODULES (supports_items=false) ===
        // These modules USE products from data modules:
        //   - sales: Sell products from any data module
        //   - purchases: Buy products for any data module
        //   - pos: Point of sale - uses any products
        //
        // === MANAGEMENT MODULES (no products) ===
        //   - hrm, accounting, reports, projects, documents, helpdesk
        
        $modules = [
            // === PRODUCT/DATA MODULES (create products/items here) ===
            ['key' => 'general',        'name' => 'General Products',   'name_ar' => 'المنتجات العامة',   'version' => '1.0.0', 'is_core' => true,  'supports_items' => true,  'module_type' => 'data',       'icon' => '📦', 'description' => 'General products and items', 'description_ar' => 'المنتجات والعناصر العامة'],
            ['key' => 'motorcycle',     'name' => 'Motorcycles',        'name_ar' => 'الدراجات النارية',  'version' => '1.0.0', 'is_core' => false, 'supports_items' => true,  'module_type' => 'data',       'icon' => '🏍️', 'description' => 'Motorcycles, bikes and accessories', 'description_ar' => 'الدراجات النارية والإكسسوارات'],
            ['key' => 'spares',         'name' => 'Spare Parts',        'name_ar' => 'قطع الغيار',        'version' => '1.0.0', 'is_core' => false, 'supports_items' => true,  'module_type' => 'data',       'icon' => '🔧', 'description' => 'Vehicle spare parts with compatibility', 'description_ar' => 'قطع غيار السيارات والمركبات'],
            ['key' => 'wood',           'name' => 'Wood & Lumber',      'name_ar' => 'الأخشاب',           'version' => '1.0.0', 'is_core' => false, 'supports_items' => true,  'module_type' => 'data',       'icon' => '🪵', 'description' => 'Wood, lumber and timber products', 'description_ar' => 'منتجات الأخشاب والأحطاب'],
            ['key' => 'rental',         'name' => 'Rental Units',       'name_ar' => 'وحدات الإيجار',     'version' => '1.0.0', 'is_core' => false, 'supports_items' => true,  'module_type' => 'data',       'icon' => '🏠', 'description' => 'Rental properties and units', 'description_ar' => 'العقارات والوحدات المؤجرة'],
            ['key' => 'manufacturing',  'name' => 'Manufacturing',      'name_ar' => 'التصنيع',           'version' => '1.0.0', 'is_core' => false, 'supports_items' => true,  'module_type' => 'data',       'icon' => '🏭', 'description' => 'Raw materials and manufactured goods', 'description_ar' => 'المواد الخام والمنتجات المصنعة'],
            
            // === STOCK MANAGEMENT MODULE (tracks ALL products from data modules) ===
            ['key' => 'inventory',      'name' => 'Inventory',          'name_ar' => 'المخزون',           'version' => '1.0.0', 'is_core' => true,  'supports_items' => false, 'module_type' => 'functional', 'icon' => '📊', 'description' => 'Stock tracking and management', 'description_ar' => 'تتبع وإدارة المخزون'],
            
            // === OPERATIONAL MODULES (use products from data modules) ===
            ['key' => 'sales',          'name' => 'Sales',              'name_ar' => 'المبيعات',          'version' => '1.0.0', 'is_core' => true,  'supports_items' => false, 'module_type' => 'functional', 'icon' => '💰', 'description' => 'Sales management', 'description_ar' => 'إدارة المبيعات'],
            ['key' => 'purchases',      'name' => 'Purchases',          'name_ar' => 'المشتريات',         'version' => '1.0.0', 'is_core' => true,  'supports_items' => false, 'module_type' => 'functional', 'icon' => '🛒', 'description' => 'Purchase management', 'description_ar' => 'إدارة المشتريات'],
            ['key' => 'pos',            'name' => 'Point of Sale',      'name_ar' => 'نقاط البيع',        'version' => '1.0.0', 'is_core' => true,  'supports_items' => false, 'module_type' => 'functional', 'icon' => '🖥️', 'description' => 'Point of sale', 'description_ar' => 'نقاط البيع'],
            
            // === MANAGEMENT MODULES (no products) ===
            ['key' => 'hrm',            'name' => 'Human Resources',    'name_ar' => 'الموارد البشرية',   'version' => '1.0.0', 'is_core' => false, 'supports_items' => false, 'module_type' => 'functional', 'icon' => '👥', 'description' => 'HR management', 'description_ar' => 'إدارة الموارد البشرية'],
            ['key' => 'reports',        'name' => 'Reports',            'name_ar' => 'التقارير',          'version' => '1.0.0', 'is_core' => true,  'supports_items' => false, 'module_type' => 'functional', 'icon' => '📊', 'description' => 'System reports', 'description_ar' => 'تقارير النظام'],
            ['key' => 'accounting',     'name' => 'Accounting',         'name_ar' => 'المحاسبة',          'version' => '1.0.0', 'is_core' => true,  'supports_items' => false, 'module_type' => 'functional', 'icon' => '🧮', 'description' => 'Financial accounting', 'description_ar' => 'المحاسبة المالية'],
            ['key' => 'projects',       'name' => 'Projects',           'name_ar' => 'المشاريع',          'version' => '1.0.0', 'is_core' => false, 'supports_items' => false, 'module_type' => 'functional', 'icon' => '📋', 'description' => 'Project management', 'description_ar' => 'إدارة المشاريع'],
            ['key' => 'documents',      'name' => 'Documents',          'name_ar' => 'المستندات',         'version' => '1.0.0', 'is_core' => false, 'supports_items' => false, 'module_type' => 'functional', 'icon' => '📁', 'description' => 'Document management', 'description_ar' => 'إدارة المستندات'],
            ['key' => 'helpdesk',       'name' => 'Helpdesk',           'name_ar' => 'الدعم الفني',       'version' => '1.0.0', 'is_core' => false, 'supports_items' => false, 'module_type' => 'functional', 'icon' => '🎫', 'description' => 'Support tickets', 'description_ar' => 'تذاكر الدعم الفني'],
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
