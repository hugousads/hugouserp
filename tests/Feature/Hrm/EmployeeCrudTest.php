<?php

declare(strict_types=1);

namespace Tests\Feature\Hrm;

use App\Models\Branch;
use App\Models\HREmployee;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeCrudTest extends TestCase
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

    public function test_can_create_employee(): void
    {
        $employee = HREmployee::create([
            'code' => 'EMP001',
            'name' => 'John Doe',
            'position' => 'Developer',
            'salary' => 5000,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $this->assertDatabaseHas('hr_employees', ['code' => 'EMP001']);
    }

    public function test_can_read_employee(): void
    {
        $employee = HREmployee::create([
            'code' => 'EMP002',
            'name' => 'John Doe',
            'position' => 'Developer',
            'salary' => 5000,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $found = HREmployee::find($employee->id);
        $this->assertNotNull($found);
    }

    public function test_can_update_employee(): void
    {
        $employee = HREmployee::create([
            'code' => 'EMP003',
            'name' => 'John Doe',
            'position' => 'Developer',
            'salary' => 5000,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $employee->update(['position' => 'Senior Developer']);
        $this->assertDatabaseHas('hr_employees', ['id' => $employee->id, 'position' => 'Senior Developer']);
    }

    public function test_can_delete_employee(): void
    {
        $employee = HREmployee::create([
            'code' => 'EMP004',
            'name' => 'John Doe',
            'position' => 'Developer',
            'salary' => 5000,
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $employee->delete();
        $this->assertSoftDeleted('hr_employees', ['id' => $employee->id]);
    }
}
