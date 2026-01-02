<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\SystemSetting;
use App\Enums\SaleStatus;
use App\Enums\PurchaseStatus;
use App\Enums\RentalContractStatus;
use App\Enums\TicketStatus;
use App\Services\SettingsService;
use App\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;

class ERPEnhancementsTest extends TestCase
{
    use RefreshDatabase;

    private User $user;
    private SettingsService $settingsService;
    private DashboardService $dashboardService;

    protected function setUp(): void
    {
        parent::setUp();
        
        // Create a test user with Super Admin role
        $this->user = User::factory()->create();
        $role = Role::create(['name' => 'Super Admin']);
        $this->user->assignRole($role);
        
        $this->settingsService = app(SettingsService::class);
        $this->dashboardService = app(DashboardService::class);
    }

    /** @test */
    public function setting_helper_works()
    {
        // Test setting() helper function
        $this->settingsService->set('test.key', 'test_value');
        
        $value = setting('test.key', 'default');
        $this->assertEquals('test_value', $value);
        
        // Test with non-existent key
        $defaultValue = setting('non.existent.key', 'my_default');
        $this->assertEquals('my_default', $defaultValue);
        
        echo "✓ setting() helper works correctly\n";
    }

    /** @test */
    public function settings_config_exists()
    {
        $config = config('settings');
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('general', $config);
        $this->assertArrayHasKey('pos', $config);
        $this->assertArrayHasKey('inventory', $config);
        $this->assertArrayHasKey('sales', $config);
        $this->assertArrayHasKey('purchases', $config);
        $this->assertArrayHasKey('rental', $config);
        $this->assertArrayHasKey('hrm', $config);
        $this->assertArrayHasKey('accounting', $config);
        $this->assertArrayHasKey('integrations', $config);
        
        echo "✓ Settings config exists with all groups\n";
    }

    /** @test */
    public function quick_actions_config_exists()
    {
        $config = config('quick-actions');
        
        $this->assertIsArray($config);
        $this->assertArrayHasKey('sales', $config);
        $this->assertArrayHasKey('purchases', $config);
        $this->assertArrayHasKey('manager', $config);
        $this->assertArrayHasKey('inventory', $config);
        $this->assertArrayHasKey('hrm', $config);
        $this->assertArrayHasKey('admin', $config);
        
        echo "✓ Quick actions config exists with all role groups\n";
    }

    /** @test */
    public function quick_action_strings_are_translated_to_arabic()
    {
        $originalLocale = app()->getLocale();
        app()->setLocale('ar');

        $this->assertSame('بيع جديد / نقاط البيع', __('New Sale / POS'));
        $this->assertSame('افتح نقطة البيع لعملية بيع جديدة', __('Open POS terminal for new sale'));
        $this->assertSame('تقرير مبيعات اليوم', __("Today's Sales Report"));
        $this->assertSame('إنشاء أمر شراء', __('Create Purchase Order'));

        app()->setLocale($originalLocale);
    }

    /** @test */
    public function sale_status_enum_validates_transitions()
    {
        $draft = SaleStatus::DRAFT;
        
        // Valid transitions
        $this->assertTrue($draft->canTransitionTo(SaleStatus::CONFIRMED));
        $this->assertTrue($draft->canTransitionTo(SaleStatus::CANCELLED));
        
        // Invalid transitions
        $this->assertFalse($draft->canTransitionTo(SaleStatus::PAID));
        $this->assertFalse($draft->canTransitionTo(SaleStatus::REFUNDED));
        
        // Test final status
        $paid = SaleStatus::PAID;
        $this->assertTrue($paid->isFinal());
        $this->assertFalse($draft->isFinal());
        
        echo "✓ SaleStatus enum validates transitions correctly\n";
    }

    /** @test */
    public function purchase_status_enum_validates_transitions()
    {
        $draft = PurchaseStatus::DRAFT;
        
        $this->assertTrue($draft->canTransitionTo(PurchaseStatus::APPROVED));
        $this->assertFalse($draft->canTransitionTo(PurchaseStatus::RECEIVED));
        
        echo "✓ PurchaseStatus enum validates transitions correctly\n";
    }

    /** @test */
    public function rental_contract_status_enum_validates_transitions()
    {
        $active = RentalContractStatus::ACTIVE;
        
        $this->assertTrue($active->canTransitionTo(RentalContractStatus::SUSPENDED));
        $this->assertTrue($active->canTransitionTo(RentalContractStatus::TERMINATED));
        $this->assertFalse($active->canTransitionTo(RentalContractStatus::DRAFT));
        
        echo "✓ RentalContractStatus enum validates transitions correctly\n";
    }

    /** @test */
    public function ticket_status_enum_validates_transitions()
    {
        $open = TicketStatus::OPEN;
        
        $this->assertTrue($open->canTransitionTo(TicketStatus::IN_PROGRESS));
        $this->assertFalse($open->canTransitionTo(TicketStatus::RESOLVED));
        
        echo "✓ TicketStatus enum validates transitions correctly\n";
    }

    /** @test */
    public function status_enums_have_labels_and_colors()
    {
        $this->assertIsString(SaleStatus::DRAFT->label());
        $this->assertIsString(SaleStatus::DRAFT->color());
        
        $this->assertIsString(PurchaseStatus::APPROVED->label());
        $this->assertIsString(PurchaseStatus::APPROVED->color());
        
        $this->assertIsString(RentalContractStatus::ACTIVE->label());
        $this->assertIsString(RentalContractStatus::ACTIVE->color());
        
        $this->assertIsString(TicketStatus::OPEN->label());
        $this->assertIsString(TicketStatus::OPEN->color());
        
        echo "✓ All status enums have labels and colors\n";
    }

    /** @test */
    public function ui_components_exist()
    {
        $components = [
            'resources/views/components/ui/card.blade.php',
            'resources/views/components/ui/button.blade.php',
            'resources/views/components/ui/empty-state.blade.php',
            'resources/views/components/ui/form/input.blade.php',
            'resources/views/components/ui/form/select.blade.php',
            'resources/views/components/ui/form/textarea.blade.php',
        ];
        
        foreach ($components as $component) {
            $this->assertFileExists(base_path($component));
        }
        
        echo "✓ All UI components exist\n";
    }

    /** @test */
    public function dashboard_components_exist()
    {
        $this->assertFileExists(base_path('resources/views/components/dashboard/quick-actions.blade.php'));
        $this->assertFileExists(base_path('resources/views/components/attachments/uploader.blade.php'));
        
        echo "✓ Dashboard components exist\n";
    }

    /** @test */
    public function sidebar_organized_exists()
    {
        $this->assertFileExists(base_path('resources/views/layouts/sidebar-organized.blade.php'));
        
        echo "✓ Organized sidebar exists\n";
    }

    /** @test */
    public function performance_indexes_migration_exists()
    {
        $migrations = scandir(base_path('database/migrations'));
        $indexMigration = array_filter($migrations, function($file) {
            return str_contains($file, 'add_performance_indexes_to_tables');
        });
        
        $this->assertNotEmpty($indexMigration);
        
        echo "✓ Performance indexes migration exists\n";
    }

    /** @test */
    public function documentation_exists()
    {
        $this->assertFileExists(base_path('ERP_ENHANCEMENTS_SUMMARY.md'));
        
        echo "✓ Documentation exists\n";
    }
}
