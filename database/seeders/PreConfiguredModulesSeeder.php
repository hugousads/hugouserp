<?php

namespace Database\Seeders;

use App\Models\Module;
use App\Models\ModuleProductField;
use App\Models\RentalPeriod;
use App\Models\ReportDefinition;
use Illuminate\Database\Seeder;

class PreConfiguredModulesSeeder extends Seeder
{
    public function run(): void
    {
        $this->createWoodModule();
        $this->createRentalsModule();
        $this->createSparePartsModule();
        $this->createMotorcyclesModule();
    }

    protected function createWoodModule(): void
    {
        $module = Module::updateOrCreate(
            ['key' => 'wood'],
            [
                'slug' => 'wood',
                'name' => 'Wood & Lumber',
                'name_ar' => 'الأخشاب',
                'description' => 'Wood and lumber products management with dimensions and types',
                'description_ar' => 'إدارة منتجات الأخشاب مع الأبعاد والأنواع',
                'icon' => 'tree',
                'color' => '#8B4513',
                'is_core' => false,
                'is_active' => true,
                'pricing_type' => 'buy_sell',
                'has_variations' => true,
                'has_inventory' => true,
                'has_serial_numbers' => false,
                'has_batch_numbers' => true,
                'is_rental' => false,
                'is_service' => false,
                'category' => 'products',
                'sort_order' => 10,
            ]
        );

        $fields = [
            [
                'field_key' => 'wood_type',
                'field_label' => 'Wood Type',
                'field_label_ar' => 'نوع الخشب',
                'field_type' => 'select',
                'field_options' => ['oak' => 'Oak / بلوط', 'pine' => 'Pine / صنوبر', 'cedar' => 'Cedar / أرز', 'mahogany' => 'Mahogany / ماهوجني', 'teak' => 'Teak / تيك', 'walnut' => 'Walnut / جوز', 'beech' => 'Beech / زان', 'ash' => 'Ash / دردار'],
                'is_required' => true,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 1,
                'field_group' => 'specifications',
            ],
            [
                'field_key' => 'length_cm',
                'field_label' => 'Length (cm)',
                'field_label_ar' => 'الطول (سم)',
                'field_type' => 'decimal',
                'is_required' => true,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 2,
                'field_group' => 'dimensions',
            ],
            [
                'field_key' => 'width_cm',
                'field_label' => 'Width (cm)',
                'field_label_ar' => 'العرض (سم)',
                'field_type' => 'decimal',
                'is_required' => true,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 3,
                'field_group' => 'dimensions',
            ],
            [
                'field_key' => 'thickness_cm',
                'field_label' => 'Thickness (cm)',
                'field_label_ar' => 'السُمك (سم)',
                'field_type' => 'decimal',
                'is_required' => true,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 4,
                'field_group' => 'dimensions',
            ],
            [
                'field_key' => 'grade',
                'field_label' => 'Quality Grade',
                'field_label_ar' => 'درجة الجودة',
                'field_type' => 'select',
                'field_options' => ['A' => 'Grade A / درجة أولى', 'B' => 'Grade B / درجة ثانية', 'C' => 'Grade C / درجة ثالثة'],
                'is_required' => false,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 5,
                'field_group' => 'specifications',
            ],
            [
                'field_key' => 'moisture_content',
                'field_label' => 'Moisture Content (%)',
                'field_label_ar' => 'نسبة الرطوبة (%)',
                'field_type' => 'decimal',
                'is_required' => false,
                'is_filterable' => false,
                'show_in_list' => false,
                'sort_order' => 6,
                'field_group' => 'specifications',
            ],
            [
                'field_key' => 'origin_country',
                'field_label' => 'Origin Country',
                'field_label_ar' => 'بلد المنشأ',
                'field_type' => 'text',
                'is_required' => false,
                'is_filterable' => true,
                'show_in_list' => false,
                'sort_order' => 7,
                'field_group' => 'details',
            ],
            [
                'field_key' => 'price_per_meter',
                'field_label' => 'Price per Meter',
                'field_label_ar' => 'السعر لكل متر',
                'field_type' => 'decimal',
                'is_required' => false,
                'is_filterable' => false,
                'show_in_list' => true,
                'sort_order' => 8,
                'field_group' => 'pricing',
            ],
        ];

        foreach ($fields as $field) {
            ModuleProductField::updateOrCreate(
                ['module_id' => $module->id, 'field_key' => $field['field_key']],
                array_merge($field, ['module_id' => $module->id])
            );
        }

        $this->createModuleReports($module, 'wood');
    }

    protected function createRentalsModule(): void
    {
        $module = Module::updateOrCreate(
            ['key' => 'rental'],
            [
                'slug' => 'rental',
                'name' => 'Rental',
                'name_ar' => 'الإيجارات',
                'description' => 'Property and equipment rental management',
                'description_ar' => 'إدارة تأجير العقارات والمعدات',
                'icon' => 'home',
                'color' => '#3B82F6',
                'is_core' => false,
                'is_active' => true,
                'pricing_type' => 'sell_only',
                'has_variations' => false,
                'has_inventory' => false,
                'has_serial_numbers' => true,
                'has_batch_numbers' => false,
                'is_rental' => true,
                'is_service' => true,
                'category' => 'services',
                'sort_order' => 20,
            ]
        );

        $fields = [
            [
                'field_key' => 'rental_type',
                'field_label' => 'Rental Type',
                'field_label_ar' => 'نوع الإيجار',
                'field_type' => 'select',
                'field_options' => ['apartment' => 'Apartment / شقة', 'villa' => 'Villa / فيلا', 'office' => 'Office / مكتب', 'shop' => 'Shop / محل', 'warehouse' => 'Warehouse / مخزن', 'land' => 'Land / أرض', 'equipment' => 'Equipment / معدات', 'vehicle' => 'Vehicle / سيارة'],
                'is_required' => true,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 1,
                'field_group' => 'details',
            ],
            [
                'field_key' => 'area_sqm',
                'field_label' => 'Area (sqm)',
                'field_label_ar' => 'المساحة (م²)',
                'field_type' => 'decimal',
                'is_required' => false,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 2,
                'field_group' => 'specifications',
            ],
            [
                'field_key' => 'location',
                'field_label' => 'Location',
                'field_label_ar' => 'الموقع',
                'field_type' => 'text',
                'is_required' => true,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 3,
                'field_group' => 'details',
            ],
            [
                'field_key' => 'bedrooms',
                'field_label' => 'Bedrooms',
                'field_label_ar' => 'غرف النوم',
                'field_type' => 'number',
                'is_required' => false,
                'is_filterable' => true,
                'show_in_list' => false,
                'sort_order' => 4,
                'field_group' => 'specifications',
            ],
            [
                'field_key' => 'bathrooms',
                'field_label' => 'Bathrooms',
                'field_label_ar' => 'الحمامات',
                'field_type' => 'number',
                'is_required' => false,
                'is_filterable' => true,
                'show_in_list' => false,
                'sort_order' => 5,
                'field_group' => 'specifications',
            ],
            [
                'field_key' => 'furnished',
                'field_label' => 'Furnished',
                'field_label_ar' => 'مفروش',
                'field_type' => 'checkbox',
                'is_required' => false,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 6,
                'field_group' => 'features',
            ],
            [
                'field_key' => 'deposit_amount',
                'field_label' => 'Deposit Amount',
                'field_label_ar' => 'مبلغ التأمين',
                'field_type' => 'decimal',
                'is_required' => false,
                'is_filterable' => false,
                'show_in_list' => true,
                'sort_order' => 7,
                'field_group' => 'pricing',
            ],
            [
                'field_key' => 'amenities',
                'field_label' => 'Amenities',
                'field_label_ar' => 'المرافق',
                'field_type' => 'multiselect',
                'field_options' => ['parking' => 'Parking / موقف', 'pool' => 'Pool / مسبح', 'gym' => 'Gym / صالة رياضية', 'security' => 'Security / حراسة', 'ac' => 'AC / تكييف', 'elevator' => 'Elevator / مصعد'],
                'is_required' => false,
                'is_filterable' => true,
                'show_in_list' => false,
                'sort_order' => 8,
                'field_group' => 'features',
            ],
        ];

        foreach ($fields as $field) {
            ModuleProductField::updateOrCreate(
                ['module_id' => $module->id, 'field_key' => $field['field_key']],
                array_merge($field, ['module_id' => $module->id])
            );
        }

        $rentalPeriods = [
            ['period_key' => 'daily', 'period_name' => 'Daily', 'period_name_ar' => 'يومي', 'period_type' => 'daily', 'duration_value' => 1, 'duration_unit' => 'days', 'price_multiplier' => 1, 'is_default' => false, 'sort_order' => 1],
            ['period_key' => 'weekly', 'period_name' => 'Weekly', 'period_name_ar' => 'أسبوعي', 'period_type' => 'weekly', 'duration_value' => 1, 'duration_unit' => 'weeks', 'price_multiplier' => 6, 'is_default' => false, 'sort_order' => 2],
            ['period_key' => 'monthly', 'period_name' => 'Monthly', 'period_name_ar' => 'شهري', 'period_type' => 'monthly', 'duration_value' => 1, 'duration_unit' => 'months', 'price_multiplier' => 25, 'is_default' => true, 'sort_order' => 3],
            ['period_key' => 'quarterly', 'period_name' => 'Quarterly', 'period_name_ar' => 'ربع سنوي', 'period_type' => 'quarterly', 'duration_value' => 3, 'duration_unit' => 'months', 'price_multiplier' => 70, 'is_default' => false, 'sort_order' => 4],
            ['period_key' => 'semi_annual', 'period_name' => 'Semi-Annual', 'period_name_ar' => 'نصف سنوي', 'period_type' => 'custom', 'duration_value' => 6, 'duration_unit' => 'months', 'price_multiplier' => 135, 'is_default' => false, 'sort_order' => 5],
            ['period_key' => 'yearly', 'period_name' => 'Yearly', 'period_name_ar' => 'سنوي', 'period_type' => 'yearly', 'duration_value' => 1, 'duration_unit' => 'years', 'price_multiplier' => 250, 'is_default' => false, 'sort_order' => 6],
        ];

        foreach ($rentalPeriods as $period) {
            RentalPeriod::updateOrCreate(
                ['module_id' => $module->id, 'period_key' => $period['period_key']],
                array_merge($period, ['module_id' => $module->id])
            );
        }

        $this->createModuleReports($module, 'rental');
    }

    protected function createSparePartsModule(): void
    {
        $module = Module::updateOrCreate(
            ['key' => 'spares'],
            [
                'slug' => 'spares',
                'name' => 'Spare Parts',
                'name_ar' => 'قطع الغيار',
                'description' => 'Automotive and machinery spare parts management',
                'description_ar' => 'إدارة قطع غيار السيارات والآلات',
                'icon' => 'cog',
                'color' => '#EF4444',
                'is_core' => false,
                'is_active' => true,
                'pricing_type' => 'buy_sell',
                'has_variations' => true,
                'has_inventory' => true,
                'has_serial_numbers' => true,
                'has_batch_numbers' => true,
                'is_rental' => false,
                'is_service' => false,
                'category' => 'products',
                'sort_order' => 30,
            ]
        );

        $fields = [
            [
                'field_key' => 'part_number',
                'field_label' => 'Part Number',
                'field_label_ar' => 'رقم القطعة',
                'field_type' => 'text',
                'is_required' => true,
                'is_searchable' => true,
                'is_filterable' => false,
                'show_in_list' => true,
                'sort_order' => 1,
                'field_group' => 'identification',
            ],
            [
                'field_key' => 'oem_number',
                'field_label' => 'OEM Number',
                'field_label_ar' => 'رقم OEM',
                'field_type' => 'text',
                'is_required' => false,
                'is_searchable' => true,
                'is_filterable' => false,
                'show_in_list' => true,
                'sort_order' => 2,
                'field_group' => 'identification',
            ],
            [
                'field_key' => 'brand',
                'field_label' => 'Brand',
                'field_label_ar' => 'الماركة',
                'field_type' => 'text',
                'is_required' => false,
                'is_searchable' => true,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 3,
                'field_group' => 'details',
            ],
            [
                'field_key' => 'compatible_vehicles',
                'field_label' => 'Compatible Vehicles',
                'field_label_ar' => 'السيارات المتوافقة',
                'field_type' => 'textarea',
                'is_required' => false,
                'is_searchable' => true,
                'is_filterable' => false,
                'show_in_list' => false,
                'sort_order' => 4,
                'field_group' => 'compatibility',
            ],
            [
                'field_key' => 'compatible_years',
                'field_label' => 'Compatible Years',
                'field_label_ar' => 'سنوات الصنع المتوافقة',
                'field_type' => 'text',
                'placeholder' => '2018-2023',
                'placeholder_ar' => '2018-2023',
                'is_required' => false,
                'is_filterable' => true,
                'show_in_list' => false,
                'sort_order' => 5,
                'field_group' => 'compatibility',
            ],
            [
                'field_key' => 'condition',
                'field_label' => 'Condition',
                'field_label_ar' => 'الحالة',
                'field_type' => 'select',
                'field_options' => ['new' => 'New / جديد', 'refurbished' => 'Refurbished / مجدد', 'used' => 'Used / مستعمل'],
                'is_required' => true,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 6,
                'field_group' => 'details',
            ],
            [
                'field_key' => 'warranty_months',
                'field_label' => 'Warranty (Months)',
                'field_label_ar' => 'الضمان (شهور)',
                'field_type' => 'number',
                'is_required' => false,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 7,
                'field_group' => 'warranty',
            ],
            [
                'field_key' => 'weight_kg',
                'field_label' => 'Weight (kg)',
                'field_label_ar' => 'الوزن (كجم)',
                'field_type' => 'decimal',
                'is_required' => false,
                'is_filterable' => false,
                'show_in_list' => false,
                'sort_order' => 8,
                'field_group' => 'specifications',
            ],
        ];

        foreach ($fields as $field) {
            ModuleProductField::updateOrCreate(
                ['module_id' => $module->id, 'field_key' => $field['field_key']],
                array_merge($field, ['module_id' => $module->id])
            );
        }

        $this->createModuleReports($module, 'spares');
    }

    protected function createMotorcyclesModule(): void
    {
        $module = Module::updateOrCreate(
            ['key' => 'motorcycle'],
            [
                'slug' => 'motorcycle',
                'name' => 'Motorcycles',
                'name_ar' => 'الموتوسيكلات',
                'description' => 'Motorcycle sales and inventory management',
                'description_ar' => 'إدارة مبيعات ومخزون الموتوسيكلات',
                'icon' => 'motorcycle',
                'color' => '#F59E0B',
                'is_core' => false,
                'is_active' => true,
                'pricing_type' => 'buy_sell',
                'has_variations' => true,
                'has_inventory' => true,
                'has_serial_numbers' => true,
                'has_batch_numbers' => false,
                'is_rental' => false,
                'is_service' => false,
                'category' => 'products',
                'sort_order' => 40,
            ]
        );

        $fields = [
            [
                'field_key' => 'brand',
                'field_label' => 'Brand',
                'field_label_ar' => 'الماركة',
                'field_type' => 'select',
                'field_options' => ['honda' => 'Honda / هوندا', 'yamaha' => 'Yamaha / ياماها', 'suzuki' => 'Suzuki / سوزوكي', 'kawasaki' => 'Kawasaki / كاواساكي', 'bmw' => 'BMW / بي إم دبليو', 'ducati' => 'Ducati / دوكاتي', 'harley' => 'Harley-Davidson / هارلي', 'ktm' => 'KTM / كي تي إم', 'benelli' => 'Benelli / بينيلي', 'other' => 'Other / أخرى'],
                'is_required' => true,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 1,
                'field_group' => 'identification',
            ],
            [
                'field_key' => 'model',
                'field_label' => 'Model',
                'field_label_ar' => 'الموديل',
                'field_type' => 'text',
                'is_required' => true,
                'is_searchable' => true,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 2,
                'field_group' => 'identification',
            ],
            [
                'field_key' => 'year',
                'field_label' => 'Year',
                'field_label_ar' => 'سنة الصنع',
                'field_type' => 'number',
                'is_required' => true,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 3,
                'field_group' => 'identification',
            ],
            [
                'field_key' => 'chassis_number',
                'field_label' => 'Chassis Number',
                'field_label_ar' => 'رقم الشاسيه',
                'field_type' => 'text',
                'is_required' => true,
                'is_searchable' => true,
                'is_filterable' => false,
                'show_in_list' => true,
                'sort_order' => 4,
                'field_group' => 'identification',
            ],
            [
                'field_key' => 'engine_number',
                'field_label' => 'Engine Number',
                'field_label_ar' => 'رقم المحرك',
                'field_type' => 'text',
                'is_required' => false,
                'is_searchable' => true,
                'is_filterable' => false,
                'show_in_list' => false,
                'sort_order' => 5,
                'field_group' => 'identification',
            ],
            [
                'field_key' => 'engine_cc',
                'field_label' => 'Engine CC',
                'field_label_ar' => 'سعة المحرك (سي سي)',
                'field_type' => 'number',
                'is_required' => true,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 6,
                'field_group' => 'specifications',
            ],
            [
                'field_key' => 'color',
                'field_label' => 'Color',
                'field_label_ar' => 'اللون',
                'field_type' => 'color',
                'is_required' => true,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 7,
                'field_group' => 'appearance',
            ],
            [
                'field_key' => 'mileage_km',
                'field_label' => 'Mileage (km)',
                'field_label_ar' => 'عداد الكيلومترات',
                'field_type' => 'number',
                'is_required' => false,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 8,
                'field_group' => 'specifications',
            ],
            [
                'field_key' => 'condition',
                'field_label' => 'Condition',
                'field_label_ar' => 'الحالة',
                'field_type' => 'select',
                'field_options' => ['new' => 'New / جديد', 'excellent' => 'Excellent / ممتاز', 'good' => 'Good / جيد', 'fair' => 'Fair / مقبول'],
                'is_required' => true,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 9,
                'field_group' => 'details',
            ],
            [
                'field_key' => 'warranty_months',
                'field_label' => 'Warranty (Months)',
                'field_label_ar' => 'الضمان (شهور)',
                'field_type' => 'number',
                'is_required' => false,
                'is_filterable' => true,
                'show_in_list' => true,
                'sort_order' => 10,
                'field_group' => 'warranty',
            ],
            [
                'field_key' => 'fuel_type',
                'field_label' => 'Fuel Type',
                'field_label_ar' => 'نوع الوقود',
                'field_type' => 'select',
                'field_options' => ['petrol' => 'Petrol / بنزين', 'electric' => 'Electric / كهربائي', 'hybrid' => 'Hybrid / هجين'],
                'is_required' => false,
                'is_filterable' => true,
                'show_in_list' => false,
                'sort_order' => 11,
                'field_group' => 'specifications',
            ],
        ];

        foreach ($fields as $field) {
            ModuleProductField::updateOrCreate(
                ['module_id' => $module->id, 'field_key' => $field['field_key']],
                array_merge($field, ['module_id' => $module->id])
            );
        }

        $this->createModuleReports($module, 'motorcycle');
    }

    protected function createModuleReports(Module $module, string $moduleKey): void
    {
        $reports = [
            [
                'report_key' => "{$moduleKey}_inventory",
                'report_name' => ucfirst(str_replace('_', ' ', $moduleKey)).' Inventory Report',
                'report_name_ar' => 'تقرير مخزون '.$module->name_ar,
                'description' => 'Stock levels and inventory status',
                'description_ar' => 'مستويات المخزون وحالة الجرد',
                'report_type' => 'table',
                'is_branch_specific' => true,
                'supports_export' => true,
                'export_formats' => ['xlsx', 'pdf', 'csv'],
            ],
            [
                'report_key' => "{$moduleKey}_sales",
                'report_name' => ucfirst(str_replace('_', ' ', $moduleKey)).' Sales Report',
                'report_name_ar' => 'تقرير مبيعات '.$module->name_ar,
                'description' => 'Sales transactions and revenue',
                'description_ar' => 'معاملات البيع والإيرادات',
                'report_type' => 'chart',
                'is_branch_specific' => true,
                'supports_export' => true,
                'export_formats' => ['xlsx', 'pdf'],
            ],
            [
                'report_key' => "{$moduleKey}_purchases",
                'report_name' => ucfirst(str_replace('_', ' ', $moduleKey)).' Purchases Report',
                'report_name_ar' => 'تقرير مشتريات '.$module->name_ar,
                'description' => 'Purchase orders and costs',
                'description_ar' => 'طلبات الشراء والتكاليف',
                'report_type' => 'table',
                'is_branch_specific' => true,
                'supports_export' => true,
                'export_formats' => ['xlsx', 'pdf', 'csv'],
            ],
        ];

        foreach ($reports as $report) {
            ReportDefinition::updateOrCreate(
                ['report_key' => $report['report_key']],
                array_merge($report, ['module_id' => $module->id])
            );
        }
    }
}
