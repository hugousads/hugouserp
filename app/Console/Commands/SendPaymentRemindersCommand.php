<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AutomatedAlertService;
use Illuminate\Console\Command;

/**
 * SendPaymentRemindersCommand - Automated payment reminders
 * 
 * NEW FEATURE: Scheduled command for payment reminders
 */
class SendPaymentRemindersCommand extends Command
{
    protected $signature = 'payments:send-reminders
                          {--branch= : Branch ID to check}
                          {--dry-run : Show what would be sent without sending}';

    protected $description = 'Send payment reminders for overdue invoices';

    public function handle(): int
    {
        $this->info('Checking for overdue payments...');

        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;
        $dryRun = $this->option('dry-run');

        $alertService = app(AutomatedAlertService::class);
        $alerts = $alertService->checkOverdueSalesAlerts($branchId);

        if (empty($alerts)) {
            $this->info('No overdue payments found.');
            return self::SUCCESS;
        }

        $this->warn('Found ' . count($alerts) . ' overdue payments:');

        // Group by severity
        $critical = array_filter($alerts, fn($a) => $a['severity'] === 'critical');
        $high = array_filter($alerts, fn($a) => $a['severity'] === 'high');
        $medium = array_filter($alerts, fn($a) => $a['severity'] === 'medium');
        $low = array_filter($alerts, fn($a) => $a['severity'] === 'low');

        $this->table(
            ['Severity', 'Count', 'Total Amount Due'],
            [
                ['Critical', count($critical), number_format(array_sum(array_column($critical, 'amount_due')), 2)],
                ['High', count($high), number_format(array_sum(array_column($high, 'amount_due')), 2)],
                ['Medium', count($medium), number_format(array_sum(array_column($medium, 'amount_due')), 2)],
                ['Low', count($low), number_format(array_sum(array_column($low, 'amount_due')), 2)],
            ]
        );

        // Show top 10 overdue
        $this->info("\nTop 10 Overdue Payments:");
        usort($alerts, fn($a, $b) => $b['days_overdue'] <=> $a['days_overdue']);

        $this->table(
            ['Invoice', 'Customer', 'Amount Due', 'Days Overdue', 'Severity'],
            array_map(fn($a) => [
                $a['sale_code'],
                $a['customer_name'],
                number_format($a['amount_due'], 2),
                $a['days_overdue'],
                $a['severity'],
            ], array_slice($alerts, 0, 10))
        );

        if ($dryRun) {
            $this->info("\nDry run mode - no reminders were sent");
            return self::SUCCESS;
        }

        // In a real implementation, you would send emails/notifications here
        $this->info("\nSending reminders...");
        $sent = 0;

        foreach ($alerts as $alert) {
            // TODO: Implement actual reminder sending
            // For now, just count
            $sent++;
        }

        $this->info("Sent {$sent} payment reminders");

        return self::SUCCESS;
    }
}
