<?php

declare(strict_types=1);

namespace Tests\Feature\Expenses;

use App\Models\Branch;
use App\Models\ExpenseCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExpenseCategoryCrudTest extends TestCase
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

    public function test_can_create_expense_category(): void
    {
        $this->actingAs($this->user);

        $data = [
            'name' => 'Office Supplies',
            'name_ar' => 'مستلزمات مكتبية',
            'description' => 'Expenses for office supplies',
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ];

        $category = ExpenseCategory::create($data);

        $this->assertDatabaseHas('expense_categories', [
            'name' => 'Office Supplies',
            'branch_id' => $this->branch->id,
        ]);
    }

    public function test_can_read_expense_category(): void
    {
        $category = ExpenseCategory::create([
            'name' => 'Travel Expenses',
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $found = ExpenseCategory::find($category->id);

        $this->assertNotNull($found);
        $this->assertEquals('Travel Expenses', $found->name);
    }

    public function test_can_update_expense_category(): void
    {
        $category = ExpenseCategory::create([
            'name' => 'Old Name',
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $category->update(['name' => 'Updated Name']);

        $this->assertDatabaseHas('expense_categories', [
            'id' => $category->id,
            'name' => 'Updated Name',
        ]);
    }

    public function test_can_delete_expense_category(): void
    {
        $category = ExpenseCategory::create([
            'name' => 'To Delete',
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        $category->delete();

        $this->assertDatabaseMissing('expense_categories', [
            'id' => $category->id,
        ]);
    }

    public function test_active_scope_filters_inactive_categories(): void
    {
        ExpenseCategory::create([
            'name' => 'Active Category',
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        ExpenseCategory::create([
            'name' => 'Inactive Category',
            'is_active' => false,
            'branch_id' => $this->branch->id,
        ]);

        $activeCategories = ExpenseCategory::active()->get();

        $this->assertCount(1, $activeCategories);
        $this->assertEquals('Active Category', $activeCategories->first()->name);
    }

    public function test_localized_name_returns_arabic_when_locale_is_ar(): void
    {
        $category = ExpenseCategory::create([
            'name' => 'Office Supplies',
            'name_ar' => 'مستلزمات مكتبية',
            'is_active' => true,
            'branch_id' => $this->branch->id,
        ]);

        app()->setLocale('ar');
        $this->assertEquals('مستلزمات مكتبية', $category->localizedName);

        app()->setLocale('en');
        $this->assertEquals('Office Supplies', $category->localizedName);
    }
}
