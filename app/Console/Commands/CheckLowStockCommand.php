<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AutomatedAlertService;
use App\Services\StockReorderService;
use Illuminate\Console\Command;

/**
 * CheckLowStockCommand - Automated low stock monitoring
 * 
 * NEW FEATURE: Scheduled command for low stock monitoring
 */
class CheckLowStockCommand extends Command
{
    protected $signature = 'stock:check-low
                          {--branch= : Branch ID to check}
                          {--auto-reorder : Automatically generate purchase requisitions}';

    protected $description = 'Check for low stock products and send alerts';

    public function handle(): int
    {
        $this->info('Checking for low stock products...');

        $branchId = $this->option('branch') ? (int) $this->option('branch') : null;
        $autoReorder = $this->option('auto-reorder');

        $alertService = app(AutomatedAlertService::class);
        $alerts = $alertService->checkLowStockAlerts($branchId);

        if (empty($alerts)) {
            $this->info('No low stock alerts found.');
            return self::SUCCESS;
        }

        $this->warn('Found ' . count($alerts) . ' low stock products:');

        $this->table(
            ['Product', 'Code', 'Current Stock', 'Threshold', 'Severity', 'Branch'],
            array_map(fn($a) => [
                $a['product_name'],
                $a['product_code'],
                $a['current_stock'],
                $a['alert_threshold'],
                $a['severity'],
                $a['branch_name'] ?? 'N/A',
            ], array_slice($alerts, 0, 10))
        );

        if (count($alerts) > 10) {
            $this->info('... and ' . (count($alerts) - 10) . ' more');
        }

        // Auto-generate requisitions if requested
        if ($autoReorder) {
            $this->info('Generating purchase requisitions...');
            $reorderService = app(StockReorderService::class);
            $result = $reorderService->autoGenerateRequisitions($branchId, 1); // System user

            if ($result['success']) {
                $this->info($result['message']);
                if (!empty($result['requisitions'])) {
                    $this->table(
                        ['Requisition Code', 'Branch ID', 'Items Count'],
                        array_map(fn($r) => [$r['code'], $r['branch_id'], $r['items_count']], $result['requisitions'])
                    );
                }
            } else {
                $this->error('Failed to generate requisitions');
            }
        }

        return self::SUCCESS;
    }
}
