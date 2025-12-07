<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\BankAccount;
use App\Models\BankTransaction;
use App\Models\BankReconciliation;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Service for banking and cashflow operations
 */
class BankingService
{
    /**
     * Record a bank transaction
     */
    public function recordTransaction(array $data): BankTransaction
    {
        return DB::transaction(function () use ($data) {
            $bankAccount = BankAccount::lockForUpdate()->findOrFail($data['bank_account_id']);
            
            $transaction = BankTransaction::create($data);
            
            // Update bank account balance using signed amount
            $bankAccount->current_balance += $transaction->getSignedAmount();
            $transaction->balance_after = $bankAccount->current_balance;
            $transaction->save();
            
            $bankAccount->save();
            
            return $transaction;
        });
    }

    /**
     * Start a bank reconciliation
     */
    public function startReconciliation(
        int $bankAccountId,
        int $branchId,
        Carbon $statementDate,
        float $statementBalance
    ): BankReconciliation {
        $bankAccount = BankAccount::findOrFail($bankAccountId);
        
        // Calculate book balance at statement date
        $bookBalance = $this->calculateBookBalanceAt($bankAccountId, $statementDate);
        
        $difference = $statementBalance - $bookBalance;
        
        return BankReconciliation::create([
            'bank_account_id' => $bankAccountId,
            'branch_id' => $branchId,
            'statement_date' => $statementDate,
            'reconciliation_date' => now(),
            'statement_balance' => $statementBalance,
            'book_balance' => $bookBalance,
            'difference' => $difference,
            'status' => 'draft',
            'reconciled_by' => auth()->id(),
        ]);
    }

    /**
     * Mark transactions as reconciled
     */
    public function reconcileTransactions(BankReconciliation $reconciliation, array $transactionIds): void
    {
        DB::transaction(function () use ($reconciliation, $transactionIds) {
            BankTransaction::whereIn('id', $transactionIds)
                ->update([
                    'status' => 'reconciled',
                    'reconciliation_id' => $reconciliation->id,
                ]);
            
            // Recalculate difference
            $reconciledTotal = BankTransaction::where('reconciliation_id', $reconciliation->id)
                ->sum(DB::raw('CASE WHEN type IN ("deposit", "interest") THEN amount ELSE -amount END'));
            
            $newDifference = $reconciliation->statement_balance - $reconciliation->book_balance - $reconciledTotal;
            
            $reconciliation->update(['difference' => $newDifference]);
        });
    }

    /**
     * Complete reconciliation
     */
    public function completeReconciliation(BankReconciliation $reconciliation): void
    {
        if (!$reconciliation->isBalanced()) {
            throw new \Exception('Reconciliation is not balanced. Cannot complete.');
        }
        
        $reconciliation->update([
            'status' => 'completed',
            'reconciliation_date' => now(),
        ]);
    }

    /**
     * Calculate book balance at a specific date
     */
    protected function calculateBookBalanceAt(int $bankAccountId, Carbon $date): float
    {
        $bankAccount = BankAccount::findOrFail($bankAccountId);
        
        $transactions = BankTransaction::where('bank_account_id', $bankAccountId)
            ->where('transaction_date', '<=', $date)
            ->where('status', '!=', 'cancelled')
            ->get();
        
        $balance = $bankAccount->opening_balance;
        
        foreach ($transactions as $transaction) {
            if ($transaction->isDeposit() || $transaction->type === 'interest') {
                $balance += $transaction->amount;
            } else {
                $balance -= $transaction->amount;
            }
        }
        
        return $balance;
    }

    /**
     * Get cashflow summary for a period
     */
    public function getCashflowSummary(int $branchId, Carbon $startDate, Carbon $endDate): array
    {
        $transactions = BankTransaction::where('branch_id', $branchId)
            ->whereBetween('transaction_date', [$startDate, $endDate])
            ->where('status', '!=', 'cancelled')
            ->get();
        
        $inflows = 0;
        $outflows = 0;
        
        foreach ($transactions as $transaction) {
            if ($transaction->isDeposit() || $transaction->type === 'interest') {
                $inflows += $transaction->amount;
            } else {
                $outflows += $transaction->amount;
            }
        }
        
        return [
            'total_inflows' => $inflows,
            'total_outflows' => $outflows,
            'net_cashflow' => $inflows - $outflows,
            'transaction_count' => $transactions->count(),
        ];
    }

    /**
     * Import transactions from CSV/Excel
     */
    public function importTransactions(int $bankAccountId, array $transactions): array
    {
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($transactions as $txn) {
            try {
                // Check if transaction already exists
                $exists = BankTransaction::where('bank_account_id', $bankAccountId)
                    ->where('reference_number', $txn['reference_number'] ?? '')
                    ->exists();
                
                if ($exists) {
                    $skipped++;
                    continue;
                }
                
                $this->recordTransaction([
                    'bank_account_id' => $bankAccountId,
                    'branch_id' => $txn['branch_id'],
                    'reference_number' => $txn['reference_number'] ?? null,
                    'transaction_date' => $txn['transaction_date'],
                    'type' => $txn['type'],
                    'amount' => $txn['amount'],
                    'description' => $txn['description'] ?? null,
                    'payee_payer' => $txn['payee_payer'] ?? null,
                    'status' => 'cleared',
                    'created_by' => auth()->id(),
                ]);
                
                $imported++;
            } catch (\Exception $e) {
                $errors[] = [
                    'reference' => $txn['reference_number'] ?? 'Unknown',
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return [
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
        ];
    }
}
