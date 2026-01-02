<?php

declare(strict_types=1);

namespace Tests\Feature\Suppliers;

use App\Models\Branch;
use App\Models\Supplier;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create(['name' => 'Test Branch', 'code' => 'TB001']);
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);
    }

    public function test_can_create_supplier_with_english_fields(): void
    {
        $supplier = Supplier::create([
            'name' => 'ABC Suppliers Ltd',
            'company_name' => 'ABC Company',
            'email' => 'abc@example.com',
            'phone' => '+1-555-1234',
            'address' => '123 Main Street',
            'city' => 'New York',
            'country' => 'United States',
            'tax_number' => 'TAX123456',
            'contact_person' => 'John Smith',
            'notes' => 'Premium supplier',
            'branch_id' => $this->branch->id,
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'ABC Suppliers Ltd',
            'company_name' => 'ABC Company',
            'city' => 'New York',
            'country' => 'United States',
            'email' => 'abc@example.com',
            'phone' => '+1-555-1234',
            'address' => '123 Main Street',
            'tax_number' => 'TAX123456',
            'contact_person' => 'John Smith',
            'notes' => 'Premium supplier',
        ]);
    }

    public function test_can_create_supplier_with_arabic_text(): void
    {
        $supplier = Supplier::create([
            'name' => 'مورد الخليج التجاري',
            'company_name' => 'شركة الخليج للتجارة',
            'email' => 'gulf@example.com',
            'phone' => '+966501234567',
            'address' => 'شارع الملك فهد، حي العليا',
            'city' => 'الرياض',
            'country' => 'المملكة العربية السعودية',
            'tax_number' => 'TAX-SA-12345',
            'contact_person' => 'أحمد محمد',
            'notes' => 'مورد موثوق ولديه خبرة طويلة في السوق',
            'branch_id' => $this->branch->id,
        ]);

        // Verify all Arabic text is stored correctly
        $this->assertDatabaseHas('suppliers', [
            'name' => 'مورد الخليج التجاري',
            'company_name' => 'شركة الخليج للتجارة',
            'city' => 'الرياض',
            'country' => 'المملكة العربية السعودية',
            'contact_person' => 'أحمد محمد',
        ]);

        // Verify we can retrieve the supplier and Arabic text is intact
        $retrieved = Supplier::find($supplier->id);
        $this->assertEquals('مورد الخليج التجاري', $retrieved->name);
        $this->assertEquals('شركة الخليج للتجارة', $retrieved->company_name);
        $this->assertEquals('الرياض', $retrieved->city);
        $this->assertEquals('المملكة العربية السعودية', $retrieved->country);
        $this->assertEquals('شارع الملك فهد، حي العليا', $retrieved->address);
        $this->assertEquals('أحمد محمد', $retrieved->contact_person);
        $this->assertEquals('مورد موثوق ولديه خبرة طويلة في السوق', $retrieved->notes);
    }

    public function test_can_create_supplier_with_mixed_arabic_and_english(): void
    {
        $supplier = Supplier::create([
            'name' => 'ABC Suppliers - مورد إيه بي سي',
            'company_name' => 'ABC Trading Co. - شركة إيه بي سي للتجارة',
            'email' => 'abc-arabic@example.com',
            'phone' => '+966501234567',
            'address' => 'King Fahd Road - شارع الملك فهد',
            'city' => 'Riyadh - الرياض',
            'country' => 'Saudi Arabia - المملكة العربية السعودية',
            'contact_person' => 'Ahmed Ahmed - أحمد أحمد',
            'notes' => 'Bilingual supplier handling both Arabic and English transactions',
            'branch_id' => $this->branch->id,
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'ABC Suppliers - مورد إيه بي سي',
            'company_name' => 'ABC Trading Co. - شركة إيه بي سي للتجارة',
            'city' => 'Riyadh - الرياض',
            'country' => 'Saudi Arabia - المملكة العربية السعودية',
        ]);
    }

    public function test_required_fields_city_country_company_name_persist(): void
    {
        // Test specifically for the bug where city, country, company_name were not persisting
        $supplier = Supplier::create([
            'name' => 'Test Supplier',
            'company_name' => 'Test Company Name',
            'city' => 'Test City',
            'country' => 'Test Country',
            'branch_id' => $this->branch->id,
        ]);

        // Explicitly verify these three fields persist
        $retrieved = Supplier::find($supplier->id);
        $this->assertNotNull($retrieved->company_name, 'company_name should not be null');
        $this->assertNotNull($retrieved->city, 'city should not be null');
        $this->assertNotNull($retrieved->country, 'country should not be null');
        
        $this->assertEquals('Test Company Name', $retrieved->company_name);
        $this->assertEquals('Test City', $retrieved->city);
        $this->assertEquals('Test Country', $retrieved->country);
    }

    public function test_can_read_supplier(): void
    {
        $supplier = Supplier::create([
            'name' => 'Test Supplier',
            'branch_id' => $this->branch->id,
        ]);

        $found = Supplier::find($supplier->id);
        $this->assertNotNull($found);
    }

    public function test_can_update_supplier_with_arabic(): void
    {
        $supplier = Supplier::create([
            'name' => 'Original Name',
            'company_name' => 'Original Company',
            'city' => 'Original City',
            'country' => 'Original Country',
            'branch_id' => $this->branch->id,
        ]);

        $supplier->update([
            'name' => 'مورد محدث',
            'company_name' => 'شركة محدثة',
            'city' => 'مدينة محدثة',
            'country' => 'دولة محدثة',
            'notes' => 'ملاحظات باللغة العربية',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'id' => $supplier->id,
            'name' => 'مورد محدث',
            'company_name' => 'شركة محدثة',
            'city' => 'مدينة محدثة',
            'country' => 'دولة محدثة',
            'notes' => 'ملاحظات باللغة العربية',
        ]);
    }

    public function test_can_delete_supplier(): void
    {
        $supplier = Supplier::create([
            'name' => 'To Be Deleted',
            'branch_id' => $this->branch->id,
        ]);

        $supplier->delete();
        $this->assertSoftDeleted('suppliers', ['id' => $supplier->id]);
    }

    public function test_supplier_with_all_financial_fields(): void
    {
        $supplier = Supplier::create([
            'name' => 'Financial Supplier',
            'branch_id' => $this->branch->id,
            'minimum_order_value' => 1000.50,
            'supplier_rating' => 'Excellent',
            'quality_rating' => 4.5,
            'delivery_rating' => 4.8,
            'service_rating' => 4.7,
            'payment_terms' => 'net30',
        ]);

        $this->assertDatabaseHas('suppliers', [
            'name' => 'Financial Supplier',
            'minimum_order_value' => 1000.50,
            'supplier_rating' => 'Excellent',
            'quality_rating' => 4.5,
            'delivery_rating' => 4.8,
            'service_rating' => 4.7,
            'payment_terms' => 'net30',
        ]);
    }

    public function test_arabic_characters_in_all_text_fields(): void
    {
        // Comprehensive test ensuring Arabic works in ALL text fields
        $supplier = Supplier::create([
            'name' => 'اسم المورد',
            'company_name' => 'اسم الشركة',
            'email' => 'arabic@test.com',
            'phone' => '0501234567',
            'address' => 'العنوان الكامل',
            'city' => 'المدينة',
            'country' => 'الدولة',
            'tax_number' => 'TAX-AR-123',
            'contact_person' => 'شخص الاتصال',
            'notes' => 'ملاحظات إضافية',
            'supplier_rating' => 'ممتاز',
            'payment_terms' => 'net30',
            'branch_id' => $this->branch->id,
        ]);

        $retrieved = Supplier::find($supplier->id);
        
        // Verify every Arabic field
        $this->assertEquals('اسم المورد', $retrieved->name);
        $this->assertEquals('اسم الشركة', $retrieved->company_name);
        $this->assertEquals('العنوان الكامل', $retrieved->address);
        $this->assertEquals('المدينة', $retrieved->city);
        $this->assertEquals('الدولة', $retrieved->country);
        $this->assertEquals('شخص الاتصال', $retrieved->contact_person);
        $this->assertEquals('ملاحظات إضافية', $retrieved->notes);
        $this->assertEquals('ممتاز', $retrieved->supplier_rating);
    }
}
