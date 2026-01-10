<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\Branch;
use Illuminate\Database\Seeder;

class ChartOfAccountsSeeder extends Seeder
{
    /**
     * Helper method to get account ID by account number
     * Uses DB::table to avoid Eloquent attribute access issues during seeding
     */
    protected function getAccountId(int $branchId, string $accountNumber): ?int
    {
        return \DB::table('accounts')
            ->where('branch_id', $branchId)
            ->where('account_number', $accountNumber)
            ->value('id');
    }

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get branch ID directly from database to avoid Eloquent issues
        $branchId = \DB::table('branches')
            ->where('is_main', true)
            ->value('id');
        
        if (! $branchId) {
            $branchId = \DB::table('branches')->value('id');
        }

        if (! $branchId) {
            $this->command->warn('No branch found. Please create a branch first.');

            return;
        }

        $this->createAssetAccounts($branchId);
        $this->createLiabilityAccounts($branchId);
        $this->createEquityAccounts($branchId);
        $this->createRevenueAccounts($branchId);
        $this->createExpenseAccounts($branchId);

        $this->createAccountMappings($branchId);

        $this->command->info('Chart of accounts and account mappings created successfully!');
    }

    /**
     * Create asset accounts
     */
    protected function createAssetAccounts(int $branchId): void
    {
        // Current Assets
        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '1000',
            ],
            [
                'name' => 'Current Assets',
                'name_ar' => 'الأصول المتداولة',
                'type' => 'asset',
                'account_category' => 'current',
                'is_active' => true,
            ]
        );
        
        // Get ID from database to avoid Eloquent attribute issues
        $currentAssetsId = $this->getAccountId($branchId, '1000');

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '1010',
            ],
            [
                'name' => 'Cash',
                'name_ar' => 'النقدية',
                'type' => 'asset',
                'account_category' => 'current',
                'parent_id' => $currentAssetsId,
                'is_active' => true,
            ]
        );

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '1020',
            ],
            [
                'name' => 'Bank Accounts',
                'name_ar' => 'الحسابات البنكية',
                'type' => 'asset',
                'account_category' => 'current',
                'parent_id' => $currentAssetsId,
                'is_active' => true,
            ]
        );

        // BUG FIX: Add Cheques Receivable account for cheque payments (Bug #3)
        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '1030',
            ],
            [
                'name' => 'Cheques Receivable',
                'name_ar' => 'شيكات تحت التحصيل',
                'type' => 'asset',
                'account_category' => 'current',
                'parent_id' => $currentAssetsId,
                'is_active' => true,
            ]
        );

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '1100',
            ],
            [
                'name' => 'Accounts Receivable',
                'name_ar' => 'العملاء (المدينون)',
                'type' => 'asset',
                'account_category' => 'current',
                'parent_id' => $currentAssetsId,
                'is_active' => true,
            ]
        );

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '1200',
            ],
            [
                'name' => 'Inventory',
                'name_ar' => 'المخزون',
                'type' => 'asset',
                'account_category' => 'current',
                'parent_id' => $currentAssetsId,
                'is_active' => true,
            ]
        );

        // Fixed Assets
        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '1500',
            ],
            [
                'name' => 'Fixed Assets',
                'name_ar' => 'الأصول الثابتة',
                'type' => 'asset',
                'account_category' => 'fixed',
                'is_active' => true,
            ]
        );
        
        $fixedAssetsId = $this->getAccountId($branchId, '1500');

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '1510',
            ],
            [
                'name' => 'Property & Equipment',
                'name_ar' => 'الممتلكات والمعدات',
                'type' => 'asset',
                'account_category' => 'fixed',
                'parent_id' => $fixedAssetsId,
                'is_active' => true,
            ]
        );
    }

    /**
     * Create liability accounts
     */
    protected function createLiabilityAccounts(int $branchId): void
    {
        // Current Liabilities
        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '2000',
            ],
            [
                'name' => 'Current Liabilities',
                'name_ar' => 'الخصوم المتداولة',
                'type' => 'liability',
                'account_category' => 'current',
                'is_active' => true,
            ]
        );
        
        $currentLiabilitiesId = $this->getAccountId($branchId, '2000');

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '2100',
            ],
            [
                'name' => 'Accounts Payable',
                'name_ar' => 'الموردون (الدائنون)',
                'type' => 'liability',
                'account_category' => 'current',
                'parent_id' => $currentLiabilitiesId,
                'is_active' => true,
            ]
        );

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '2200',
            ],
            [
                'name' => 'Tax Payable',
                'name_ar' => 'الضرائب المستحقة',
                'type' => 'liability',
                'account_category' => 'current',
                'parent_id' => $currentLiabilitiesId,
                'is_active' => true,
            ]
        );

        // Tax Recoverable is an asset (receivable from government)
        // Find the Current Assets parent account
        $currentAssetsId = $this->getAccountId($branchId, '1000');

        if ($currentAssetsId) {
            Account::updateOrCreate(
                [
                    'branch_id' => $branchId,
                    'account_number' => '1150',
                ],
                [
                    'name' => 'Tax Recoverable',
                    'name_ar' => 'الضرائب القابلة للاسترداد',
                    'type' => 'asset',
                    'account_category' => 'current',
                    'parent_id' => $currentAssetsId,
                    'is_active' => true,
                ]
            );
        } else {
            $this->command->warn('Current Assets parent account not found. Skipping Tax Recoverable account creation.');
        }

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '2300',
            ],
            [
                'name' => 'Salaries Payable',
                'name_ar' => 'الرواتب المستحقة',
                'type' => 'liability',
                'account_category' => 'current',
                'parent_id' => $currentLiabilitiesId,
                'is_active' => true,
            ]
        );
    }

    /**
     * Create equity accounts
     */
    protected function createEquityAccounts(int $branchId): void
    {
        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '3000',
            ],
            [
                'name' => 'Equity',
                'name_ar' => 'حقوق الملكية',
                'type' => 'equity',
                'is_active' => true,
            ]
        );
        
        $equityId = $this->getAccountId($branchId, '3000');

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '3100',
            ],
            [
                'name' => 'Capital',
                'name_ar' => 'رأس المال',
                'type' => 'equity',
                'parent_id' => $equityId,
                'is_active' => true,
            ]
        );

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '3200',
            ],
            [
                'name' => 'Retained Earnings',
                'name_ar' => 'الأرباح المحتجزة',
                'type' => 'equity',
                'parent_id' => $equityId,
                'is_active' => true,
            ]
        );
    }

    /**
     * Create revenue accounts
     */
    protected function createRevenueAccounts(int $branchId): void
    {
        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '4000',
            ],
            [
                'name' => 'Revenue',
                'name_ar' => 'الإيرادات',
                'type' => 'revenue',
                'is_active' => true,
            ]
        );
        
        $revenueId = $this->getAccountId($branchId, '4000');

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '4100',
            ],
            [
                'name' => 'Sales Revenue',
                'name_ar' => 'إيرادات المبيعات',
                'type' => 'revenue',
                'parent_id' => $revenueId,
                'is_active' => true,
            ]
        );

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '4200',
            ],
            [
                'name' => 'Rental Revenue',
                'name_ar' => 'إيرادات الإيجار',
                'type' => 'revenue',
                'parent_id' => $revenueId,
                'is_active' => true,
            ]
        );

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '4300',
            ],
            [
                'name' => 'Service Revenue',
                'name_ar' => 'إيرادات الخدمات',
                'type' => 'revenue',
                'parent_id' => $revenueId,
                'is_active' => true,
            ]
        );
    }

    /**
     * Create expense accounts
     */
    protected function createExpenseAccounts(int $branchId): void
    {
        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '5000',
            ],
            [
                'name' => 'Expenses',
                'name_ar' => 'المصروفات',
                'type' => 'expense',
                'is_active' => true,
            ]
        );
        
        $expensesId = $this->getAccountId($branchId, '5000');

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '5100',
            ],
            [
                'name' => 'Cost of Goods Sold',
                'name_ar' => 'تكلفة البضاعة المباعة',
                'type' => 'expense',
                'parent_id' => $expensesId,
                'is_active' => true,
            ]
        );

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '5200',
            ],
            [
                'name' => 'Salaries & Wages',
                'name_ar' => 'الرواتب والأجور',
                'type' => 'expense',
                'parent_id' => $expensesId,
                'is_active' => true,
            ]
        );

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '5300',
            ],
            [
                'name' => 'Rent Expense',
                'name_ar' => 'مصروف الإيجار',
                'type' => 'expense',
                'parent_id' => $expensesId,
                'is_active' => true,
            ]
        );

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '5400',
            ],
            [
                'name' => 'Utilities Expense',
                'name_ar' => 'مصروفات المرافق',
                'type' => 'expense',
                'parent_id' => $expensesId,
                'is_active' => true,
            ]
        );

        Account::updateOrCreate(
            [
                'branch_id' => $branchId,
                'account_number' => '5500',
            ],
            [
                'name' => 'Sales Discount',
                'name_ar' => 'خصم المبيعات',
                'type' => 'expense',
                'parent_id' => $expensesId,
                'is_active' => true,
            ]
        );
    }

    /**
     * Create account mappings for modules
     */
    protected function createAccountMappings(int $branchId): void
    {
        // Sales module mappings
        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'sales',
                'mapping_key' => 'cash_account',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '1010'),
                'is_active' => true,
            ]
        );

        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'sales',
                'mapping_key' => 'accounts_receivable',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '1100'),
                'is_active' => true,
            ]
        );

        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'sales',
                'mapping_key' => 'sales_revenue',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '4100'),
                'is_active' => true,
            ]
        );

        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'sales',
                'mapping_key' => 'tax_payable',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '2200'),
                'is_active' => true,
            ]
        );

        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'sales',
                'mapping_key' => 'sales_discount',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '5500'),
                'is_active' => true,
            ]
        );

        // BUG FIX: Add COGS account mapping for sales (Bug #1)
        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'sales',
                'mapping_key' => 'cogs_account',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '5100'), // Cost of Goods Sold
                'is_active' => true,
            ]
        );

        // BUG FIX: Add inventory account mapping for sales (Bug #1)
        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'sales',
                'mapping_key' => 'inventory_account',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '1200'), // Inventory
                'is_active' => true,
            ]
        );

        // BUG FIX: Add bank account mapping for card/transfer payments (Bug #3)
        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'sales',
                'mapping_key' => 'bank_account',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '1020'), // Bank
                'is_active' => true,
            ]
        );

        // BUG FIX: Add cheque account mapping for cheque payments (Bug #3)
        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'sales',
                'mapping_key' => 'cheque_account',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '1030'), // Cheques Receivable
                'is_active' => true,
            ]
        );

        // Purchase module mappings
        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'purchases',
                'mapping_key' => 'cash_account',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '1010'),
                'is_active' => true,
            ]
        );

        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'purchases',
                'mapping_key' => 'accounts_payable',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '2100'),
                'is_active' => true,
            ]
        );

        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'purchases',
                'mapping_key' => 'inventory_account',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '1200'),
                'is_active' => true,
            ]
        );

        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'purchases',
                'mapping_key' => 'tax_recoverable',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '1150'),
                'is_active' => true,
            ]
        );

        // HRM module mappings
        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'hrm',
                'mapping_key' => 'salaries_expense',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '5200'),
                'is_active' => true,
            ]
        );

        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'hrm',
                'mapping_key' => 'salaries_payable',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '2300'),
                'is_active' => true,
            ]
        );

        // Rental module mappings
        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'rental',
                'mapping_key' => 'rental_revenue',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '4200'),
                'is_active' => true,
            ]
        );

        AccountMapping::updateOrCreate(
            [
                'branch_id' => $branchId,
                'module_name' => 'rental',
                'mapping_key' => 'cash_account',
            ],
            [
                'account_id' => $this->getAccountId($branchId, '1010'),
                'is_active' => true,
            ]
        );
    }
}
