<?php

declare(strict_types=1);

namespace Tests\Feature\Banking;

use App\Models\BankAccount;
use App\Models\Branch;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankAccountCrudTest extends TestCase
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

    protected function createBankAccount(array $overrides = []): BankAccount
    {
        return BankAccount::create(array_merge([
            'account_name' => 'Main Account',
            'account_number' => '1234567890',
            'bank_name' => 'Test Bank',
            'currency' => 'EGP',
            'current_balance' => 10000,
            'opening_balance' => 10000,
            'opening_date' => now(),
            'branch_id' => $this->branch->id,
        ], $overrides));
    }

    public function test_can_create_bank_account(): void
    {
        $account = $this->createBankAccount();

        $this->assertDatabaseHas('bank_accounts', ['account_name' => 'Main Account']);
    }

    public function test_can_read_bank_account(): void
    {
        $account = $this->createBankAccount();

        $found = BankAccount::find($account->id);
        $this->assertNotNull($found);
    }

    public function test_can_update_bank_account(): void
    {
        $account = $this->createBankAccount();

        $account->update(['current_balance' => 15000]);
        $this->assertDatabaseHas('bank_accounts', ['id' => $account->id, 'current_balance' => 15000]);
    }

    public function test_can_delete_bank_account(): void
    {
        $account = $this->createBankAccount();

        $account->delete();
        $this->assertSoftDeleted('bank_accounts', ['id' => $account->id]);
    }
}
