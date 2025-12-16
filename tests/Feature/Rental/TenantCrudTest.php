<?php

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Models\Branch;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TenantCrudTest extends TestCase
{
    use RefreshDatabase;

    protected User $user;
    protected Branch $branch;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::create([
            'name' => 'Test Branch',
            'code' => 'TB001',
        ]);

        $this->user = User::factory()->create([
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_can_create_tenant(): void
    {
        $this->actingAs($this->user);

        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'address' => '123 Main St',
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ];

        $tenant = Tenant::create($data);

        $this->assertDatabaseHas('tenants', [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_can_read_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Jane Doe',
            'email' => 'jane@example.com',
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $found = Tenant::find($tenant->id);

        $this->assertNotNull($found);
        $this->assertEquals('Jane Doe', $found->name);
    }

    public function test_can_update_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'Original Name',
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $tenant->update([
            'name' => 'Updated Name',
            'phone' => '+9876543210',
        ]);

        $this->assertDatabaseHas('tenants', [
            'id' => $tenant->id,
            'name' => 'Updated Name',
            'phone' => '+9876543210',
        ]);
    }

    public function test_can_delete_tenant(): void
    {
        $tenant = Tenant::create([
            'name' => 'To Delete',
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $tenant->delete();

        $this->assertSoftDeleted('tenants', [
            'id' => $tenant->id,
        ]);
    }

    public function test_tenant_has_contracts_relationship(): void
    {
        $tenant = Tenant::create([
            'name' => 'Tenant with Contracts',
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        // Verify relationship exists
        $this->assertTrue(method_exists($tenant, 'contracts'));
    }
}
