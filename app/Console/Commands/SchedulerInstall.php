<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

/**
 * Outputs the cron configuration needed for Laravel scheduler.
 * 
 * This command helps administrators set up the scheduler in different environments:
 * - cPanel: Copy the cron line into cPanel's Cron Jobs
 * - Non-cPanel servers: Add to system crontab or use systemd
 */
class SchedulerInstall extends Command
{
    protected $signature = 'erp:scheduler:install 
                            {--show-systemd : Show systemd timer configuration}
                            {--path= : Custom path to the project (defaults to base_path())}';

    protected $description = 'Display the cron/scheduler configuration for this ERP system';

    public function handle(): int
    {
        $projectPath = $this->option('path') ?: base_path();
        $phpPath = PHP_BINARY;

        $this->newLine();
        $this->info('=== ERP Scheduler Setup ===');
        $this->newLine();

        // Main cron line
        $cronLine = "* * * * * cd {$projectPath} && {$phpPath} artisan schedule:run >> /dev/null 2>&1";

        $this->line('<fg=yellow>Cron Configuration (Required for scheduled reports, payroll, POS closing, etc.)</>');
        $this->newLine();
        
        $this->info('Add this line to your crontab or cPanel Cron Jobs:');
        $this->newLine();
        $this->line("<fg=green>{$cronLine}</>");
        $this->newLine();

        // cPanel instructions
        $this->line('<fg=cyan>== cPanel Setup ==</>');
        $this->line('1. Log in to cPanel');
        $this->line('2. Go to "Cron Jobs" under "Advanced"');
        $this->line('3. Set "Common Settings" to "Once Per Minute (* * * * *)"');
        $this->line('4. In the "Command" field, paste:');
        $this->newLine();
        $this->line("   cd {$projectPath} && {$phpPath} artisan schedule:run >> /dev/null 2>&1");
        $this->newLine();
        $this->line('5. Click "Add New Cron Job"');
        $this->newLine();

        // Non-cPanel instructions
        $this->line('<fg=cyan>== Linux Server (non-cPanel) Setup ==</>');
        $this->line('Option 1: Add to crontab');
        $this->line('   Run: crontab -e');
        $this->line('   Add the cron line shown above');
        $this->newLine();

        if ($this->option('show-systemd')) {
            $this->showSystemdConfig($projectPath, $phpPath);
        } else {
            $this->line('Option 2: Use systemd timer (run with --show-systemd to see config)');
        }

        $this->newLine();
        $this->info('=== Scheduled Tasks ===');
        $this->line('The following tasks are scheduled:');
        $this->table(
            ['Task', 'Schedule', 'Description'],
            [
                ['reports:run-scheduled', 'Hourly', 'Run scheduled reports and send via email'],
                ['pos:close-day', 'Daily 23:55', 'Close POS day for all branches'],
                ['rental:generate-recurring', 'Daily 00:30', 'Generate recurring rental invoices'],
                ['system:backup', 'Daily 02:00', 'Run verified system backup'],
                ['hrm:payroll', 'Monthly 1st 01:30', 'Run monthly payroll'],
                ['stock:check-low', 'Daily 07:00', 'Check for low stock alerts'],
            ]
        );

        $this->newLine();
        $this->info('To verify the scheduler is working, run:');
        $this->line('   php artisan schedule:list');
        $this->newLine();

        return Command::SUCCESS;
    }

    protected function showSystemdConfig(string $projectPath, string $phpPath): void
    {
        $this->newLine();
        $this->line('<fg=cyan>== Systemd Timer Setup ==</>');
        $this->newLine();
        
        $serviceName = 'erp-scheduler';
        $user = get_current_user() ?: 'www-data';

        $this->line("Create /etc/systemd/system/{$serviceName}.service:");
        $this->newLine();
        $this->line('<fg=gray>[Unit]');
        $this->line('Description=ERP Laravel Scheduler');
        $this->line('After=network.target');
        $this->newLine();
        $this->line('[Service]');
        $this->line('Type=oneshot');
        $this->line("User={$user}");
        $this->line("WorkingDirectory={$projectPath}");
        $this->line("ExecStart={$phpPath} artisan schedule:run");
        $this->newLine();
        $this->line('[Install]');
        $this->line('WantedBy=multi-user.target</>');
        $this->newLine();

        $this->line("Create /etc/systemd/system/{$serviceName}.timer:");
        $this->newLine();
        $this->line('<fg=gray>[Unit]');
        $this->line('Description=Run ERP Laravel Scheduler every minute');
        $this->newLine();
        $this->line('[Timer]');
        $this->line('OnCalendar=*:*:00');
        $this->line('Persistent=true');
        $this->newLine();
        $this->line('[Install]');
        $this->line('WantedBy=timers.target</>');
        $this->newLine();

        $this->line('Enable and start:');
        $this->line("   sudo systemctl daemon-reload");
        $this->line("   sudo systemctl enable {$serviceName}.timer");
        $this->line("   sudo systemctl start {$serviceName}.timer");
        $this->newLine();
    }
}
