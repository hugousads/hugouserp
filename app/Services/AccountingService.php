<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Account;
use App\Models\AccountMapping;
use App\Models\FiscalPeriod;
use App\Models\JournalEntry;
use App\Models\JournalEntryLine;
use App\Models\Purchase;
use App\Models\Sale;
use Exception;
use Illuminate\Support\Facades\DB;

class AccountingService
{
    /**
     * Generate journal entry from a sale
     */
    public function generateSaleJournalEntry(Sale $sale): JournalEntry
    {
        if ($sale->journal_entry_id) {
            throw new Exception('Journal entry already generated for this sale');
        }

        return DB::transaction(function () use ($sale) {
            $fiscalPeriod = FiscalPeriod::getCurrentPeriod($sale->branch_id);

            $entry = JournalEntry::create([
                'branch_id' => $sale->branch_id,
                'reference_number' => $this->generateReferenceNumber('SALE', $sale->id),
                'entry_date' => $sale->sale_date,
                'description' => "Sale #{$sale->invoice_number}",
                'status' => 'posted',
                'source_module' => 'sales',
                'source_type' => 'Sale',
                'source_id' => $sale->id,
                'fiscal_year' => $fiscalPeriod?->year,
                'fiscal_period' => $fiscalPeriod?->period,
                'is_auto_generated' => true,
                'created_by' => auth()->id(),
            ]);

            $lines = [];

            // Debit: Cash/Bank or Customer Account (Asset)
            if ($sale->payment_status === 'paid') {
                $cashAccount = AccountMapping::getAccount('sales', 'cash_account', $sale->branch_id);
                if ($cashAccount) {
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $cashAccount->id,
                        'debit' => $sale->grand_total,
                        'credit' => 0,
                        'description' => 'Cash received from sale',
                    ];
                }
            } else {
                $receivableAccount = AccountMapping::getAccount('sales', 'accounts_receivable', $sale->branch_id);
                if ($receivableAccount) {
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $receivableAccount->id,
                        'debit' => $sale->grand_total,
                        'credit' => 0,
                        'description' => "Account receivable - Customer #{$sale->customer_id}",
                    ];
                }
            }

            // Credit: Sales Revenue
            $revenueAccount = AccountMapping::getAccount('sales', 'sales_revenue', $sale->branch_id);
            if ($revenueAccount) {
                $lines[] = [
                    'journal_entry_id' => $entry->id,
                    'account_id' => $revenueAccount->id,
                    'debit' => 0,
                    'credit' => $sale->sub_total,
                    'description' => 'Sales revenue',
                ];
            }

            // Credit: Tax Payable (if applicable)
            if ($sale->tax_total > 0) {
                $taxAccount = AccountMapping::getAccount('sales', 'tax_payable', $sale->branch_id);
                if ($taxAccount) {
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $taxAccount->id,
                        'debit' => 0,
                        'credit' => $sale->tax_total,
                        'description' => 'Tax payable on sales',
                    ];
                }
            }

            // Credit: Discount (if applicable)
            if ($sale->discount_total > 0) {
                $discountAccount = AccountMapping::getAccount('sales', 'sales_discount', $sale->branch_id);
                if ($discountAccount) {
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $discountAccount->id,
                        'debit' => $sale->discount_total,
                        'credit' => 0,
                        'description' => 'Discount given',
                    ];
                }
            }

            foreach ($lines as $lineData) {
                JournalEntryLine::create($lineData);
            }

            // Update sale with journal entry
            $sale->update(['journal_entry_id' => $entry->id]);

            return $entry->fresh('lines');
        });
    }

    /**
     * Generate journal entry from a purchase
     */
    public function generatePurchaseJournalEntry(Purchase $purchase): JournalEntry
    {
        if (! empty($purchase->journal_entry_id)) {
            throw new Exception('Journal entry already generated for this purchase');
        }

        return DB::transaction(function () use ($purchase) {
            $fiscalPeriod = FiscalPeriod::getCurrentPeriod($purchase->branch_id);

            $entry = JournalEntry::create([
                'branch_id' => $purchase->branch_id,
                'reference_number' => $this->generateReferenceNumber('PURCH', $purchase->id),
                'entry_date' => $purchase->purchase_date,
                'description' => "Purchase Order #{$purchase->reference_number}",
                'status' => 'posted',
                'source_module' => 'purchases',
                'source_type' => 'Purchase',
                'source_id' => $purchase->id,
                'fiscal_year' => $fiscalPeriod?->year,
                'fiscal_period' => $fiscalPeriod?->period,
                'is_auto_generated' => true,
                'created_by' => auth()->id(),
            ]);

            $lines = [];

            // Debit: Inventory/Expense
            $inventoryAccount = AccountMapping::getAccount('purchases', 'inventory_account', $purchase->branch_id);
            if ($inventoryAccount) {
                $lines[] = [
                    'journal_entry_id' => $entry->id,
                    'account_id' => $inventoryAccount->id,
                    'debit' => $purchase->sub_total,
                    'credit' => 0,
                    'description' => 'Inventory purchased',
                ];
            }

            // Debit: Tax Recoverable
            if ($purchase->tax_total > 0) {
                $taxAccount = AccountMapping::getAccount('purchases', 'tax_recoverable', $purchase->branch_id);
                if ($taxAccount) {
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $taxAccount->id,
                        'debit' => $purchase->tax_total,
                        'credit' => 0,
                        'description' => 'Tax recoverable on purchases',
                    ];
                }
            }

            // Credit: Cash/Bank or Accounts Payable
            if ($purchase->payment_status === 'paid') {
                $cashAccount = AccountMapping::getAccount('purchases', 'cash_account', $purchase->branch_id);
                if ($cashAccount) {
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $cashAccount->id,
                        'debit' => 0,
                        'credit' => $purchase->grand_total,
                        'description' => 'Cash paid for purchase',
                    ];
                }
            } else {
                $payableAccount = AccountMapping::getAccount('purchases', 'accounts_payable', $purchase->branch_id);
                if ($payableAccount) {
                    $lines[] = [
                        'journal_entry_id' => $entry->id,
                        'account_id' => $payableAccount->id,
                        'debit' => 0,
                        'credit' => $purchase->grand_total,
                        'description' => "Account payable - Supplier #{$purchase->supplier_id}",
                    ];
                }
            }

            foreach ($lines as $lineData) {
                JournalEntryLine::create($lineData);
            }

            // Update purchase with journal entry
            $purchase->update(['journal_entry_id' => $entry->id]);

            return $entry->fresh('lines');
        });
    }

    /**
     * Generate reference number
     */
    protected function generateReferenceNumber(string $prefix, int $id): string
    {
        return sprintf('%s-%s-%06d', $prefix, date('Ymd'), $id);
    }

    /**
     * Validate journal entry balance
     */
    public function validateBalance(JournalEntry $entry): bool
    {
        $totalDebit = $entry->lines()->sum('debit');
        $totalCredit = $entry->lines()->sum('credit');

        return abs($totalDebit - $totalCredit) < 0.01;
    }

    /**
     * Post journal entry
     */
    public function postJournalEntry(JournalEntry $entry, int $userId): bool
    {
        if ($entry->status === 'posted') {
            throw new Exception('Journal entry already posted');
        }

        if (! $this->validateBalance($entry)) {
            throw new Exception('Journal entry is not balanced');
        }

        return DB::transaction(function () use ($entry, $userId) {
            $entry->update([
                'status' => 'posted',
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            // Update account balances
            foreach ($entry->lines as $line) {
                $account = $line->account;
                $netChange = $line->debit - $line->credit;

                // For asset and expense accounts, debit increases balance
                // For liability, equity, and revenue accounts, credit increases balance
                if (in_array($account->type, ['asset', 'expense'])) {
                    $account->increment('balance', $netChange);
                } else {
                    $account->decrement('balance', $netChange);
                }
            }

            return true;
        });
    }

    /**
     * Reverse journal entry
     */
    public function reverseJournalEntry(JournalEntry $entry, string $reason, int $userId): JournalEntry
    {
        if ($entry->status !== 'posted') {
            throw new Exception('Can only reverse posted journal entries');
        }

        if (! $entry->is_reversible) {
            throw new Exception('This journal entry cannot be reversed');
        }

        return DB::transaction(function () use ($entry, $reason, $userId) {
            $reversalEntry = JournalEntry::create([
                'branch_id' => $entry->branch_id,
                'reference_number' => $this->generateReferenceNumber('REV', $entry->id),
                'entry_date' => now()->toDateString(),
                'description' => "Reversal of {$entry->reference_number}: {$reason}",
                'status' => 'posted',
                'source_module' => $entry->source_module,
                'source_type' => $entry->source_type,
                'source_id' => $entry->source_id,
                'fiscal_year' => FiscalPeriod::getCurrentPeriod($entry->branch_id)?->year,
                'fiscal_period' => FiscalPeriod::getCurrentPeriod($entry->branch_id)?->period,
                'is_auto_generated' => true,
                'created_by' => $userId,
                'approved_by' => $userId,
                'approved_at' => now(),
            ]);

            // Create reversed lines (swap debit and credit)
            foreach ($entry->lines as $line) {
                JournalEntryLine::create([
                    'journal_entry_id' => $reversalEntry->id,
                    'account_id' => $line->account_id,
                    'debit' => $line->credit,
                    'credit' => $line->debit,
                    'description' => "Reversal: {$line->description}",
                ]);

                // Update account balance
                $account = $line->account;
                $netChange = $line->credit - $line->debit; // Reversed

                if (in_array($account->type, ['asset', 'expense'])) {
                    $account->increment('balance', $netChange);
                } else {
                    $account->decrement('balance', $netChange);
                }
            }

            // Mark original entry as reversed
            $entry->update([
                'reversed_by_entry_id' => $reversalEntry->id,
            ]);

            return $reversalEntry->fresh('lines');
        });
    }
}
