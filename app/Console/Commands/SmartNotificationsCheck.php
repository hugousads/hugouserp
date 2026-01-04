<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\SmartNotificationsService;
use Illuminate\Console\Command;

class SmartNotificationsCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'erp:notifications:check {--branch= : Branch ID to check (optional)} {--type= : Notification type (low_stock, overdue, reminders, all)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check and send smart notifications for low stock, overdue invoices, and payment reminders';

    /**
     * Execute the console command.
     */
    public function handle(SmartNotificationsService $service): int
    {
        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;
        $type = $this->option('type') ?? 'all';

        $this->info('Running smart notifications check...');

        $results = [];

        switch ($type) {
            case 'low_stock':
                $results['low_stock'] = $service->checkLowStockAlerts($branchId);
                break;

            case 'overdue':
                $results['overdue_invoices'] = $service->checkOverdueInvoices($branchId);
                break;

            case 'reminders':
                $results['payment_reminders'] = $service->checkPaymentReminders($branchId);
                break;

            case 'all':
            default:
                $results = $service->runAllChecks($branchId);
                break;
        }

        // Output results
        $totalNotifications = 0;

        foreach ($results as $checkType => $items) {
            $count = count($items);
            $totalNotifications += $count;

            if ($count > 0) {
                $this->info(sprintf('  %s: %d notifications sent', ucfirst(str_replace('_', ' ', $checkType)), $count));
                
                if ($this->getOutput()->isVerbose()) {
                    foreach ($items as $item) {
                        $this->line("    - {$item}");
                    }
                }
            } else {
                $this->line(sprintf('  %s: No notifications needed', ucfirst(str_replace('_', ' ', $checkType))));
            }
        }

        $this->newLine();
        $this->info("Total notifications sent: {$totalNotifications}");

        return Command::SUCCESS;
    }
}
