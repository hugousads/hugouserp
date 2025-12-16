<?php

declare(strict_types=1);

namespace Tests\Feature\Rental;

use App\Models\Branch;
use App\Models\Property;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PropertyCrudTest extends TestCase
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

    public function test_can_create_property(): void
    {
        $this->actingAs($this->user);

        $data = [
            'name' => 'Downtown Tower',
            'address' => '100 Business Ave',
            'notes' => 'Premium location property',
            'branch_id' => $this->branch->id,
        ];

        $property = Property::create($data);

        $this->assertDatabaseHas('properties', [
            'name' => 'Downtown Tower',
            'address' => '100 Business Ave',
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_can_read_property(): void
    {
        $property = Property::create([
            'name' => 'Office Complex',
            'address' => '200 Commerce St',
            'branch_id' => $this->branch->id,
        ]);

        $found = Property::find($property->id);

        $this->assertNotNull($found);
        $this->assertEquals('Office Complex', $found->name);
    }

    public function test_can_update_property(): void
    {
        $property = Property::create([
            'name' => 'Old Building',
            'branch_id' => $this->branch->id,
        ]);

        $property->update([
            'name' => 'Renovated Building',
            'notes' => 'Recently renovated',
        ]);

        $this->assertDatabaseHas('properties', [
            'id' => $property->id,
            'name' => 'Renovated Building',
            'notes' => 'Recently renovated',
        ]);
    }

    public function test_can_delete_property(): void
    {
        $property = Property::create([
            'name' => 'To Delete',
            'branch_id' => $this->branch->id,
        ]);

        $property->delete();

        $this->assertSoftDeleted('properties', [
            'id' => $property->id,
        ]);
    }

    public function test_property_has_units_relationship(): void
    {
        $property = Property::create([
            'name' => 'Property with Units',
            'branch_id' => $this->branch->id,
        ]);

        // Verify relationship exists
        $this->assertTrue(method_exists($property, 'units'));
    }

    public function test_property_has_branch_relationship(): void
    {
        $property = Property::create([
            'name' => 'Property with Branch',
            'branch_id' => $this->branch->id,
        ]);

        $this->assertNotNull($property->branch);
        $this->assertEquals($this->branch->id, $property->branch->id);
    }
}
