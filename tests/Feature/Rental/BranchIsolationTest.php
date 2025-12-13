<?php

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Models\Branch;
use App\Models\Property;
use App\Models\RentalContract;
use App\Models\RentalInvoice;
use App\Models\RentalUnit;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BranchIsolationTest extends TestCase
{
    use RefreshDatabase;

    protected Branch $branchA;
    protected Branch $branchB;
    protected User $userA;
    protected User $userB;
    protected Tenant $tenantA;
    protected Tenant $tenantB;
    protected Property $propertyA;
    protected Property $propertyB;
    protected RentalUnit $unitA;
    protected RentalUnit $unitB;
    protected RentalContract $contractA;
    protected RentalContract $contractB;
    protected RentalInvoice $invoiceA;
    protected RentalInvoice $invoiceB;

    protected function setUp(): void
    {
        parent::setUp();

        // Allow all permissions for tests
        \Illuminate\Support\Facades\Gate::before(function () {
            return true;
        });

        // Disable middleware that requires complex setup for these tests
        $this->withoutMiddleware([
            \App\Http\Middleware\EnsureModuleEnabled::class,
            \App\Http\Middleware\EnsurePermission::class,
            \App\Http\Middleware\Require2FA::class,
            \App\Http\Middleware\SetBranchContext::class,
            \App\Http\Middleware\EnsureBranchAccess::class,
        ]);

        // Create Branch A
        $this->branchA = Branch::create([
            'name' => 'Branch A',
            'code' => 'BA001',
            'is_active' => true,
        ]);

        // Create Branch B
        $this->branchB = Branch::create([
            'name' => 'Branch B',
            'code' => 'BB001',
            'is_active' => true,
        ]);

        // Create users with permissions
        $this->userA = User::factory()->create([
            'name' => 'User A',
            'email' => 'usera@example.com',
        ]);

        $this->userB = User::factory()->create([
            'name' => 'User B',
            'email' => 'userb@example.com',
        ]);

        // Create tenant in Branch A
        $this->tenantA = Tenant::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Tenant A',
            'phone' => '1111111111',
            'email' => 'tenanta@example.com',
        ]);

        // Create tenant in Branch B
        $this->tenantB = Tenant::create([
            'branch_id' => $this->branchB->id,
            'name' => 'Tenant B',
            'phone' => '2222222222',
            'email' => 'tenantb@example.com',
        ]);

        // Create property in Branch A
        $this->propertyA = Property::create([
            'branch_id' => $this->branchA->id,
            'name' => 'Property A',
            'address' => '123 Branch A St',
        ]);

        // Create property in Branch B
        $this->propertyB = Property::create([
            'branch_id' => $this->branchB->id,
            'name' => 'Property B',
            'address' => '456 Branch B Ave',
        ]);

        // Create unit in Branch A
        $this->unitA = RentalUnit::create([
            'property_id' => $this->propertyA->id,
            'code' => 'UNIT-A-001',
            'status' => 'occupied',
        ]);

        // Create unit in Branch B
        $this->unitB = RentalUnit::create([
            'property_id' => $this->propertyB->id,
            'code' => 'UNIT-B-001',
            'status' => 'occupied',
        ]);

        // Create contract in Branch A
        $this->contractA = RentalContract::create([
            'branch_id' => $this->branchA->id,
            'unit_id' => $this->unitA->id,
            'tenant_id' => $this->tenantA->id,
            'start_date' => now()->subMonths(1),
            'end_date' => now()->addMonths(11),
            'rent' => 5000,
            'status' => 'active',
        ]);

        // Create contract in Branch B
        $this->contractB = RentalContract::create([
            'branch_id' => $this->branchB->id,
            'unit_id' => $this->unitB->id,
            'tenant_id' => $this->tenantB->id,
            'start_date' => now()->subMonths(1),
            'end_date' => now()->addMonths(11),
            'rent' => 7000,
            'status' => 'active',
        ]);

        // Create invoice in Branch A (via contract)
        $this->invoiceA = RentalInvoice::create([
            'contract_id' => $this->contractA->id,
            'code' => 'INV-A-001',
            'period' => now()->format('Y-m'),
            'amount' => 5000,
            'paid_total' => 0,
            'status' => 'pending',
            'due_date' => now()->addDays(5),
        ]);

        // Create invoice in Branch B (via contract)
        $this->invoiceB = RentalInvoice::create([
            'contract_id' => $this->contractB->id,
            'code' => 'INV-B-001',
            'period' => now()->format('Y-m'),
            'amount' => 7000,
            'paid_total' => 0,
            'status' => 'pending',
            'due_date' => now()->addDays(5),
        ]);

        // Authenticate as userA by default
        $this->actingAs($this->userA);
    }

    /** @test */
    public function test_tenant_index_only_shows_branch_tenants(): void
    {
        $response = $this->getJson("/api/v1/branches/{$this->branchA->id}/modules/rental/tenants");

        $response->assertOk();
        $data = $response->json('data');

        $tenantIds = array_column($data, 'id');

        $this->assertContains($this->tenantA->id, $tenantIds);
        $this->assertNotContains($this->tenantB->id, $tenantIds);
    }

    /** @test */
    public function test_tenant_show_returns_404_for_wrong_branch(): void
    {
        $response = $this->getJson("/api/v1/branches/{$this->branchA->id}/modules/rental/tenants/{$this->tenantB->id}");

        $response->assertNotFound();
    }

    /** @test */
    public function test_tenant_show_succeeds_for_correct_branch(): void
    {
        $response = $this->getJson("/api/v1/branches/{$this->branchA->id}/modules/rental/tenants/{$this->tenantA->id}");

        $response->assertOk();
        $this->assertEquals($this->tenantA->id, $response->json('data.id'));
    }

    /** @test */
    public function test_tenant_update_returns_404_for_wrong_branch(): void
    {
        $response = $this->patchJson("/api/v1/branches/{$this->branchA->id}/modules/rental/tenants/{$this->tenantB->id}", [
            'name' => 'Updated Name',
        ]);

        $response->assertNotFound();

        // Verify data wasn't modified
        $this->tenantB->refresh();
        $this->assertEquals('Tenant B', $this->tenantB->name);
    }

    /** @test */
    public function test_tenant_update_succeeds_for_correct_branch(): void
    {
        $response = $this->patchJson("/api/v1/branches/{$this->branchA->id}/modules/rental/tenants/{$this->tenantA->id}", [
            'name' => 'Updated Tenant A',
        ]);

        $response->assertOk();
        $this->tenantA->refresh();
        $this->assertEquals('Updated Tenant A', $this->tenantA->name);
    }

    /** @test */
    public function test_tenant_archive_returns_404_for_wrong_branch(): void
    {
        $response = $this->postJson("/api/v1/branches/{$this->branchA->id}/modules/rental/tenants/{$this->tenantB->id}/archive");

        $response->assertNotFound();

        // Verify data wasn't modified
        $this->tenantB->refresh();
        $this->assertFalse($this->tenantB->is_archived ?? false);
    }

    /** @test */
    public function test_invoice_index_only_shows_branch_invoices(): void
    {
        $response = $this->getJson("/api/v1/branches/{$this->branchA->id}/modules/rental/invoices");

        $response->assertOk();
        $data = $response->json('data');

        $invoiceIds = array_column($data, 'id');

        $this->assertContains($this->invoiceA->id, $invoiceIds);
        $this->assertNotContains($this->invoiceB->id, $invoiceIds);
    }

    /** @test */
    public function test_invoice_show_returns_404_for_wrong_branch(): void
    {
        $response = $this->getJson("/api/v1/branches/{$this->branchA->id}/modules/rental/invoices/{$this->invoiceB->id}");

        $response->assertNotFound();
    }

    /** @test */
    public function test_invoice_show_succeeds_for_correct_branch(): void
    {
        $response = $this->getJson("/api/v1/branches/{$this->branchA->id}/modules/rental/invoices/{$this->invoiceA->id}");

        $response->assertOk();
        $this->assertEquals($this->invoiceA->id, $response->json('data.id'));
    }

    /** @test */
    public function test_invoice_collect_payment_returns_404_for_wrong_branch(): void
    {
        $response = $this->postJson("/api/v1/branches/{$this->branchA->id}/modules/rental/invoices/{$this->invoiceB->id}/collect", [
            'amount' => 1000,
            'method' => 'cash',
        ]);

        $response->assertNotFound();

        // Verify invoice wasn't modified
        $this->invoiceB->refresh();
        $this->assertEquals(0, $this->invoiceB->paid_total);
    }

    /** @test */
    public function test_invoice_collect_payment_succeeds_for_correct_branch(): void
    {
        $response = $this->postJson("/api/v1/branches/{$this->branchA->id}/modules/rental/invoices/{$this->invoiceA->id}/collect", [
            'amount' => 2500,
            'method' => 'cash',
        ]);

        $response->assertOk();
        $this->invoiceA->refresh();
        $this->assertEquals(2500, $this->invoiceA->paid_total);
    }

    /** @test */
    public function test_invoice_apply_penalty_returns_404_for_wrong_branch(): void
    {
        $originalAmount = $this->invoiceB->amount;

        $response = $this->postJson("/api/v1/branches/{$this->branchA->id}/modules/rental/invoices/{$this->invoiceB->id}/penalty", [
            'penalty' => 500,
        ]);

        $response->assertNotFound();

        // Verify invoice wasn't modified
        $this->invoiceB->refresh();
        $this->assertEquals($originalAmount, $this->invoiceB->amount);
    }

    /** @test */
    public function test_invoice_apply_penalty_succeeds_for_correct_branch(): void
    {
        $originalAmount = $this->invoiceA->amount;

        $response = $this->postJson("/api/v1/branches/{$this->branchA->id}/modules/rental/invoices/{$this->invoiceA->id}/penalty", [
            'penalty' => 500,
        ]);

        $response->assertOk();
        $this->invoiceA->refresh();
        $this->assertEquals($originalAmount + 500, $this->invoiceA->amount);
    }

    /** @test */
    public function test_contract_index_only_shows_branch_contracts(): void
    {
        $response = $this->getJson("/api/v1/branches/{$this->branchA->id}/modules/rental/contracts");

        $response->assertOk();
        $data = $response->json('data');

        $contractIds = array_column($data, 'id');

        $this->assertContains($this->contractA->id, $contractIds);
        $this->assertNotContains($this->contractB->id, $contractIds);
    }

    /** @test */
    public function test_contract_show_returns_404_for_wrong_branch(): void
    {
        $response = $this->getJson("/api/v1/branches/{$this->branchA->id}/modules/rental/contracts/{$this->contractB->id}");

        $response->assertNotFound();
    }

    /** @test */
    public function test_contract_show_succeeds_for_correct_branch(): void
    {
        $response = $this->getJson("/api/v1/branches/{$this->branchA->id}/modules/rental/contracts/{$this->contractA->id}");

        $response->assertOk();
        $this->assertEquals($this->contractA->id, $response->json('data.id'));
    }

    /** @test */
    public function test_contract_update_returns_404_for_wrong_branch(): void
    {
        $response = $this->patchJson("/api/v1/branches/{$this->branchA->id}/modules/rental/contracts/{$this->contractB->id}", [
            'rent' => 8000,
        ]);

        $response->assertNotFound();

        // Verify contract wasn't modified
        $this->contractB->refresh();
        $this->assertEquals(7000, $this->contractB->rent);
    }

    /** @test */
    public function test_contract_update_succeeds_for_correct_branch(): void
    {
        $response = $this->patchJson("/api/v1/branches/{$this->branchA->id}/modules/rental/contracts/{$this->contractA->id}", [
            'rent' => 5500,
        ]);

        $response->assertOk();
        $this->contractA->refresh();
        $this->assertEquals(5500, $this->contractA->rent);
    }

    /** @test */
    public function test_contract_renew_returns_404_for_wrong_branch(): void
    {
        $originalEndDate = $this->contractB->end_date;

        $response = $this->postJson("/api/v1/branches/{$this->branchA->id}/modules/rental/contracts/{$this->contractB->id}/renew", [
            'end_date' => now()->addMonths(24)->format('Y-m-d'),
            'rent' => 8000,
        ]);

        $response->assertNotFound();

        // Verify contract wasn't modified
        $this->contractB->refresh();
        $this->assertEquals($originalEndDate->format('Y-m-d'), $this->contractB->end_date->format('Y-m-d'));
    }

    /** @test */
    public function test_contract_terminate_returns_404_for_wrong_branch(): void
    {
        $response = $this->postJson("/api/v1/branches/{$this->branchA->id}/modules/rental/contracts/{$this->contractB->id}/terminate");

        $response->assertNotFound();

        // Verify contract wasn't terminated
        $this->contractB->refresh();
        $this->assertEquals('active', $this->contractB->status);
    }

    /** @test */
    public function test_contract_validation_rejects_cross_branch_unit_and_tenant(): void
    {
        // Try to create contract in Branch A with Unit from B and Tenant from A
        $response = $this->postJson("/api/v1/branches/{$this->branchA->id}/modules/rental/contracts", [
            'unit_id' => $this->unitB->id,
            'tenant_id' => $this->tenantA->id,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(12)->format('Y-m-d'),
            'rent' => 6000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('unit_id');
    }

    /** @test */
    public function test_contract_validation_rejects_cross_branch_tenant(): void
    {
        // Try to create contract in Branch A with Unit from A and Tenant from B
        $response = $this->postJson("/api/v1/branches/{$this->branchA->id}/modules/rental/contracts", [
            'unit_id' => $this->unitA->id,
            'tenant_id' => $this->tenantB->id,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(12)->format('Y-m-d'),
            'rent' => 6000,
        ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('tenant_id');
    }

    /** @test */
    public function test_contract_creation_succeeds_with_same_branch_unit_and_tenant(): void
    {
        $response = $this->postJson("/api/v1/branches/{$this->branchA->id}/modules/rental/contracts", [
            'unit_id' => $this->unitA->id,
            'tenant_id' => $this->tenantA->id,
            'start_date' => now()->format('Y-m-d'),
            'end_date' => now()->addMonths(12)->format('Y-m-d'),
            'rent' => 6000,
        ]);

        $response->assertStatus(201);
        $this->assertDatabaseHas('rental_contracts', [
            'branch_id' => $this->branchA->id,
            'unit_id' => $this->unitA->id,
            'tenant_id' => $this->tenantA->id,
        ]);
    }

    /** @test */
    public function test_property_index_only_shows_branch_properties(): void
    {
        $response = $this->getJson("/api/v1/branches/{$this->branchA->id}/modules/rental/properties");

        $response->assertOk();
        $data = $response->json('data');

        $propertyIds = array_column($data, 'id');

        $this->assertContains($this->propertyA->id, $propertyIds);
        $this->assertNotContains($this->propertyB->id, $propertyIds);
    }

    /** @test */
    public function test_property_show_returns_404_for_wrong_branch(): void
    {
        $response = $this->getJson("/api/v1/branches/{$this->branchA->id}/modules/rental/properties/{$this->propertyB->id}");

        $response->assertNotFound();
    }

    /** @test */
    public function test_unit_index_only_shows_branch_units(): void
    {
        $response = $this->getJson("/api/v1/branches/{$this->branchA->id}/modules/rental/units");

        $response->assertOk();
        $data = $response->json('data');

        $unitIds = array_column($data, 'id');

        $this->assertContains($this->unitA->id, $unitIds);
        $this->assertNotContains($this->unitB->id, $unitIds);
    }

    /** @test */
    public function test_unit_show_returns_404_for_wrong_branch(): void
    {
        $response = $this->getJson("/api/v1/branches/{$this->branchA->id}/modules/rental/units/{$this->unitB->id}");

        $response->assertNotFound();
    }
}
