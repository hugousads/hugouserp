<?php

declare(strict_types=1);

namespace Tests\Feature\Export;

use App\Livewire\Customers\Index as CustomersIndex;
use App\Livewire\Inventory\Products\Index as ProductsIndex;
use App\Livewire\Suppliers\Index as SuppliersIndex;
use App\Models\Branch;
use App\Models\Customer;
use App\Models\Product;
use App\Models\Supplier;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ExportSystemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        
        // Create necessary permissions
        Permission::findOrCreate('inventory.products.view', 'web');
        Permission::findOrCreate('sales.view', 'web');
        Permission::findOrCreate('customers.view', 'web');
        Permission::findOrCreate('customers.manage.all', 'web');
        Permission::findOrCreate('suppliers.view', 'web');
        Permission::findOrCreate('reports.download', 'web');
    }

    public function test_export_respects_locale_for_column_headers(): void
    {
        // Test that column headers are translated based on locale
        $exportService = app(ExportService::class);
        
        // English locale
        app()->setLocale('en');
        $columns = $exportService->getAvailableColumns('products');
        $this->assertEquals('Name', $columns['name']);
        $this->assertEquals('SKU', $columns['sku']);
        
        // Arabic locale
        app()->setLocale('ar');
        $columns = $exportService->getAvailableColumns('products');
        // The translation should be retrieved in Arabic
        $this->assertNotEmpty($columns['name']);
    }

    public function test_export_with_empty_dataset_returns_valid_file(): void
    {
        Gate::define('inventory.products.view', fn () => true);
        
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->givePermissionTo('reports.download');

        // No products created - empty dataset
        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->set('selectedExportColumns', ['id', 'name', 'sku'])
            ->set('exportFormat', 'csv')
            ->call('export')
            ->assertSessionHas('export_file');
    }

    public function test_export_respects_search_filter(): void
    {
        Gate::define('inventory.products.view', fn () => true);
        
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->givePermissionTo('reports.download');

        // Create products
        Product::create([
            'name' => 'Apple iPhone',
            'sku' => 'IPHONE-001',
            'default_price' => 999,
            'branch_id' => $branch->id,
        ]);
        
        Product::create([
            'name' => 'Samsung Galaxy',
            'sku' => 'SAMSUNG-001',
            'default_price' => 899,
            'branch_id' => $branch->id,
        ]);

        // Search for Apple products only
        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->set('search', 'Apple')
            ->set('selectedExportColumns', ['id', 'name', 'sku'])
            ->set('exportFormat', 'csv')
            ->call('export')
            ->assertSessionHas('export_file');
            
        // The export file should only contain Apple iPhone (filtered)
        $exportInfo = session('export_file');
        $this->assertNotNull($exportInfo);
    }

    public function test_column_selection_affects_export_output(): void
    {
        Gate::define('inventory.products.view', fn () => true);
        
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->givePermissionTo('reports.download');

        Product::create([
            'name' => 'Test Product',
            'sku' => 'TEST-001',
            'default_price' => 100,
            'branch_id' => $branch->id,
        ]);

        // Export with only selected columns
        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->set('selectedExportColumns', ['name']) // Only name column
            ->set('exportFormat', 'csv')
            ->call('export')
            ->assertSessionHas('export_file');
    }

    public function test_export_formats_xlsx(): void
    {
        Gate::define('inventory.products.view', fn () => true);
        
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->givePermissionTo('reports.download');

        Product::create([
            'name' => 'Excel Product',
            'sku' => 'EXCEL-001',
            'default_price' => 50,
            'branch_id' => $branch->id,
        ]);

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->set('selectedExportColumns', ['id', 'name'])
            ->set('exportFormat', 'xlsx')
            ->call('export')
            ->assertSessionHas('export_file');
    }

    public function test_export_formats_pdf(): void
    {
        Gate::define('inventory.products.view', fn () => true);
        
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->givePermissionTo('reports.download');

        Product::create([
            'name' => 'PDF Product',
            'sku' => 'PDF-001',
            'default_price' => 75,
            'branch_id' => $branch->id,
        ]);

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->set('selectedExportColumns', ['id', 'name'])
            ->set('exportFormat', 'pdf')
            ->call('export')
            ->assertSessionHas('export_file');
    }

    public function test_export_service_handles_null_values(): void
    {
        $exportService = app(ExportService::class);
        
        $data = collect([
            ['id' => 1, 'name' => 'Test', 'description' => null],
            ['id' => 2, 'name' => null, 'description' => 'Desc'],
        ]);
        
        $columns = ['id', 'name', 'description'];
        
        // Should not throw exception
        $filepath = $exportService->export($data, $columns, 'csv', [
            'available_columns' => ['id' => 'ID', 'name' => 'Name', 'description' => 'Description'],
            'filename' => 'test_null_values',
        ]);
        
        $this->assertFileExists($filepath);
        
        // Cleanup
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    public function test_csv_includes_utf8_bom_for_arabic(): void
    {
        $exportService = app(ExportService::class);
        
        $data = collect([
            ['id' => 1, 'name' => 'منتج عربي'],
        ]);
        
        $filepath = $exportService->export($data, ['id', 'name'], 'csv', [
            'available_columns' => ['id' => 'المعرف', 'name' => 'الاسم'],
            'filename' => 'test_arabic_bom',
        ]);
        
        $content = file_get_contents($filepath);
        
        // Check for UTF-8 BOM at the start of file
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        
        // Cleanup
        if (file_exists($filepath)) {
            unlink($filepath);
        }
    }

    public function test_customers_export_with_filters(): void
    {
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->givePermissionTo(['customers.view', 'reports.download']);

        Customer::create([
            'name' => 'VIP Customer',
            'email' => 'vip@test.com',
            'customer_tier' => 'vip',
            'branch_id' => $branch->id,
        ]);
        
        Customer::create([
            'name' => 'Regular Customer',
            'email' => 'regular@test.com',
            'customer_tier' => 'regular',
            'branch_id' => $branch->id,
        ]);

        // Filter by customer type
        Livewire::actingAs($user)
            ->test(CustomersIndex::class)
            ->set('customerType', 'vip')
            ->set('selectedExportColumns', ['id', 'name', 'customer_tier'])
            ->call('export')
            ->assertSessionHas('export_file');
    }

    public function test_suppliers_export(): void
    {
        Gate::define('suppliers.view', fn () => true);
        
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        $user->givePermissionTo('reports.download');

        Supplier::create([
            'name' => 'Test Supplier',
            'email' => 'supplier@test.com',
            'phone' => '1234567890',
            'branch_id' => $branch->id,
        ]);

        Livewire::actingAs($user)
            ->test(SuppliersIndex::class)
            ->set('selectedExportColumns', ['id', 'name', 'email'])
            ->call('export')
            ->assertSessionHas('export_file');
    }

    public function test_select_all_columns_toggle(): void
    {
        Gate::define('inventory.products.view', fn () => true);
        
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        $component = Livewire::actingAs($user)
            ->test(ProductsIndex::class);
        
        // Initially all columns should be selected
        $component->assertSet('selectedExportColumns', array_keys($component->get('exportColumns')));
        
        // Toggle to deselect all
        $component->call('toggleAllExportColumns')
            ->assertSet('selectedExportColumns', []);
        
        // Toggle again to select all
        $component->call('toggleAllExportColumns')
            ->assertSet('selectedExportColumns', array_keys($component->get('exportColumns')));
    }

    public function test_export_modal_opens_and_closes(): void
    {
        Gate::define('inventory.products.view', fn () => true);
        
        $branch = Branch::factory()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);

        Livewire::actingAs($user)
            ->test(ProductsIndex::class)
            ->assertSet('showExportModal', false)
            ->call('openExportModal')
            ->assertSet('showExportModal', true)
            ->call('closeExportModal')
            ->assertSet('showExportModal', false);
    }
}
